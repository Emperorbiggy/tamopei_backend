<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;  // For generating unique transaction reference

class Checkout extends Controller
{
    private $payazaKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->payazaKey = config('app.payaza_api_key');

        // Log the key value (only the payazaKey)
        Log::info('Payaza API Key Retrieved:', ['payazaKey' => $this->payazaKey]); 
    }

    public function handleCheckout(Request $request)
    {
        Log::info('handleCheckout function started.');

        // Log the incoming request data
        Log::info('Received Checkout request data:', $request->all());

        // Validate incoming request data
        try {
            $data = $request->validate([
                'service_payload.first_name' => 'required|string',
                'service_payload.last_name' => 'required|string',
                'service_payload.email_address' => 'required|email',
                'service_payload.phone_number' => 'required|string',
                'service_payload.amount' => 'required|numeric|min:0.01',
                'service_payload.currency' => 'required|string',
                'service_payload.cardNumber' => 'required|string',
                'service_payload.expiryMonth' => 'required|string',
                'service_payload.expiryYear' => 'required|string',
                'service_payload.securityCode' => 'required|string',
            ]);

            Log::info('Request data validated successfully.', $data);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Generate a unique transaction reference
        $transactionReference = 'TX' . strtoupper(Str::random(16));

        // Access nested data inside service_payload
        $servicePayload = $data['service_payload'];

        $requestPayload = [
            'service_payload' => [
                'first_name' => $servicePayload['first_name'],
                'last_name' => $servicePayload['last_name'],
                'email_address' => $servicePayload['email_address'],
                'phone_number' => $servicePayload['phone_number'],
                'amount' => $servicePayload['amount'],
                'transaction_reference' => $transactionReference, // Use the generated reference
                'currency' => $servicePayload['currency'],
                'description' => 'Test',
                'card' => [
                    'expiryMonth' => $servicePayload['expiryMonth'],
                    'expiryYear' => $servicePayload['expiryYear'],
                    'securityCode' => $servicePayload['securityCode'],
                    'cardNumber' => $servicePayload['cardNumber'],
                ],
                'callback_url' => 'https://webhook.site/ed6dd427-dfcf-44a3-8fa7-4cc1ab55e029', // Replace with your actual callback URL
            ]
        ];

        // Log the request data before sending it to the API
        Log::info('Sending request to Payaza API.', $requestPayload);

        try {
            // Log the Authorization header separately
            Log::info('Authorization Header:', ['Authorization' => 'Payaza ' . $this->payazaKey]);

            // Send the POST request to the payment API
            $response = Http::withHeaders([
                'Authorization' => 'Payaza ' . $this->payazaKey,
                'Content-Type' => 'application/json',
            ])->post('https://cards-live.78financials.com/card_charge/', $requestPayload);

            // Log the full response body and status code
            Log::info('Response Status Code:', ['status_code' => $response->status()]);
            Log::info('Response Body:', ['response_body' => $response->body()]);

            // If the response is OK, return the success response
            if ($response->ok()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Payment processed successfully.'
                ]);
            } else {
                // If the response is not OK, log the error and return an error message
                Log::error('Error in Payment Response:', ['error' => $response->json()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed. Please try again later.',
                ], 500);
            }
        } catch (\Exception $e) {
            // Log the error if the request fails
            Log::error('Error processing payment:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again later.',
            ], 500);
        }
    }
}
