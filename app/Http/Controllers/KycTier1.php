<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KycTier1 extends Controller
{
    private $secretKey;
    private $encryption_key = "xPF0rPHQiRSLn3KD3OVq++GtW11ag7R2G8J0owl+xsE=";

    public function __construct()
    {
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function kycTier1(Request $request)
    {
        // Log incoming request data for debugging
        Log::info('Request received in kycTier1:', $request->all());

        try {
            // Retrieve the token from the request body (not header)
            $token = $request->input('token');

            // Log the secret key for debugging (Only do this in development mode!)
            Log::info('Secret Key Used for Authorization: ', ['secret_key' => $this->secretKey]);

            // Validate token and fetch user data
            $user = DB::table('user_credentials')->where('token', $token)->first();

            if (!$user) {
                Log::warning('Invalid token in KYC request.', ['token' => $token]);
                return response()->json(['error' => 'Unauthorized: Invalid token'], 401);
            }

            // Fetch user details
            $firstName = $user->first_name;
            $lastName = $user->last_name;
            $email = $user->email;
            $dob = $user->dob;
            $phone = $user->phone;
            $bvnHash = $user->bvn_hash;

            // Decrypt BVN hash
            $identificationNumber = $this->decryptBVN($bvnHash);

            // Format the DOB
            $formattedDob = Carbon::createFromFormat('Y-m-d', $dob)->format('d-m-Y');

            // Determine phone country code and country code
            $phoneCountryCode = ($user->country === 'Nigeria') ? '+234' : '+1';
            $countryCode = ($user->country === 'Nigeria') ? 'NG' : 'US';

            // Extract address data from the request
            $street = $request->input('street');
            $city = $request->input('city');
            $state = $request->input('state');
            $postalCode = $request->input('postal_code');

            // Prepare the cURL request data
            $requestData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'country' => $countryCode,
            ];

            // Log the full cURL request data
            Log::info('Sending request to external API:', $requestData);

            // Send request to external API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post('https://sandbox.api.maplerad.com/v1/customers', $requestData);

            // Log the raw cURL request (for debugging)
            Log::info('Full cURL request sent:', [
                'url' => 'https://sandbox.api.maplerad.com/v1/customers',
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ],
                'data' => $requestData
            ]);

            // Log the response from the external API
            $responseData = $response->json();
            Log::info('Response from external API:', $responseData);

            // Check if the response is valid
            if (!isset($responseData['status']) || !$responseData['status']) {
                Log::error('Customer creation failed with the following details:', [
                    'error_details' => $responseData,
                    'received_data' => $request->all()
                ]);
                return response()->json([
                    'error' => 'Customer creation failed', 
                    'details' => $responseData,
                    'received_data' => $request->all()
                ], 400);
            }

            // Extract customer ID and proceed to upgrade to Tier 2
            $customerId = $responseData['data']['id'];
            $upgradeResponse = $this->upgradeToTier2(
                $customerId,
                $formattedDob,
                $phoneCountryCode,
                $phone,
                $street,
                $city,
                $state,
                $countryCode,
                $postalCode,
                $identificationNumber
            );

            Log::info('Upgrade to Tier 2 Response:', $upgradeResponse);

            if (isset($upgradeResponse['message']) && $upgradeResponse['message'] === 'Customer upgraded successfully') {
                // Begin transaction
                DB::beginTransaction();

                try {
                    // Update user_credentials table with address details
                    $updated = DB::table('user_credentials')
                        ->where('token', $token)
                        ->update([
                            'address' => $street,
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => $postalCode,
                            'level' => 'Tier 2',
                        ]);

                    Log::info('Update query result:', ['updated' => $updated]);

                    if ($updated === 0) {
                        throw new \Exception('No rows were updated in user_credentials');
                    }

                    // Insert into kyc_tier table
                    DB::table('kyc_tier')->insert([
                        'customer_id' => $customerId,
                        'user_id' => $user->user_id,
                        'token' => $token,
                        'level' => 'Tier 2',
                    ]);

                    DB::commit();

                    return response()->json(['customer_id' => $customerId, 'message' => 'Customer upgraded successfully'], 200);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Database transaction failed:', ['error' => $e->getMessage()]);
                    return response()->json(['error' => 'An error occurred while updating the database.'], 500);
                }
            } else {
                return response()->json(['error' => 'Customer upgrade failed', 'details' => $upgradeResponse], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error in KYC Tier 1 process.', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred during the KYC process.'], 500);
        }
    }

    private function upgradeToTier2($customerId, $dob, $phoneCountryCode, $phoneNumber, $street, $city, $state, $country, $postalCode, $identificationNumber)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->patch('https://sandbox.api.maplerad.com/v1/customers/upgrade/tier1', [
                'dob' => $dob,
                'phone' => [
                    'phone_country_code' => $phoneCountryCode,
                    'phone_number' => $phoneNumber,
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

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error in Tier 2 upgrade.', ['exception' => $e->getMessage()]);
            return ['status' => false, 'error' => $e->getMessage()];
        }
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
}
