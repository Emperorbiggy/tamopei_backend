<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KycVerification extends Controller
{
    private $secretKey;
    private $encryption_key = "xPF0rPHQiRSLn3KD3OVq++GtW11ag7R2G8J0owl+xsE=";

    public function __construct()
    {
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function verifyUserByEmail(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'street' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postal_code' => 'required|string',
            'token' => 'required|string',
        ]);

        $token = $validated['token'];
        $street = $validated['street'];
        $city = $validated['city'];
        $state = $validated['state'];
        $postalCode = $validated['postal_code'];

        // Fetch user from token
        $user = DB::table('user_credentials')->where('token', $token)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $firstName = $user->first_name;
        $lastName = $user->last_name;
        $email = $user->email;
        $dob = $user->dob;
        $phone = $user->phone;

        // Check user country and set identification number
        if ($user->country === 'Nigeria') {
            $bvnHash = $user->bvn_hash;
            $identificationNumber = $this->decryptBVN($bvnHash);
        } else {
            $identificationNumber = $user->id_number;
        }

        // Fetch customer data by email
        $url = 'https://sandbox.api.maplerad.com/v1/customers?search=' . urlencode($email) . '&page=1&page_size=5';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
        ])->get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch customers from the API'], 500);
        }

        $responseData = $response->json();

        // If no customer exists, create one
        if (empty($responseData['data'])) {
            list($countryCode, $phoneCountryCode) = $this->getCountryAndPhoneCode($user->country);

            $createResponse = $this->createCustomer($firstName, $lastName, $email, $countryCode);
            if (!$createResponse['success']) {
                return response()->json(['error' => 'Failed to create customer'], 500);
            }

            $customerId = $createResponse['data']['id'];

            // Upgrade new customer to Tier 1
            $this->upgradeToTier1(
                $customerId,
                $dob,
                $phoneCountryCode,
                $phone,
                $street,
                $city,
                $state,
                $countryCode,
                $postalCode,
                $identificationNumber
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Customer created and upgraded to Tier 1',
                'data' => $createResponse['data'],
            ]);
        }

        // If customer exists, check tier
        $customer = $responseData['data'][0];
        if ($customer['tier'] === 0) {
            $this->upgradeToTier1(
                $customer['id'],
                $dob,
                $phoneCountryCode,
                $phone,
                $street,
                $city,
                $state,
                $countryCode,
                $postalCode,
                $identificationNumber
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Existing customer upgraded to Tier 1',
                'data' => $customer,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Customer already at Tier ' . $customer['tier'],
            'data' => $customer,
        ]);
    }

    private function decryptBVN($encrypted_bvn)
    {
        try {
            $cipher = "aes-256-cbc";
            list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_bvn), 2);
            return openssl_decrypt($encrypted_data, $cipher, $this->encryption_key, 0, $iv);
        } catch (\Exception $e) {
            Log::error('Error decrypting BVN.', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    private function getCountryAndPhoneCode($country)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
        ])->get('https://sandbox.api.maplerad.com/v1/countries');

        $countryCode = 'CA'; // Default
        $phoneCountryCode = '+1'; // Default

        if ($response->successful()) {
            $countries = $response->json()['data'];
            foreach ($countries as $item) {
                if ($item['code'] === strtoupper($country)) {
                    $countryCode = $item['code'];
                    $phoneCountryCode = '+' . $item['calling_code'];
                    break;
                }
            }
        }

        return [$countryCode, $phoneCountryCode];
    }

    private function createCustomer($firstName, $lastName, $email, $countryCode)
    {
        $requestData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'country' => $countryCode,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://sandbox.api.maplerad.com/v1/customers', $requestData);

        if ($response->successful()) {
            return ['success' => true, 'data' => $response->json()];
        }

        return ['success' => false];
    }

    private function upgradeToTier1($customerId, $dob, $phoneCountryCode, $phone, $street, $city, $state, $country, $postalCode, $identificationNumber)
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->patch('https://sandbox.api.maplerad.com/v1/customers/upgrade/tier1', [
                'dob' => $dob,
                'phone' => [
                    'phone_country_code' => $phoneCountryCode,
                    'phone_number' => $phone,
                ],
                'address' => [
                    'street' => $street,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                    'postal_code' => $postalCode,
                ],
                'customer_id' => $customerId,
                'identification_number' => $identificationNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upgrade customer to Tier 1', ['exception' => $e->getMessage()]);
        }
    }
}
