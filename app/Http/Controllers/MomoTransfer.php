<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class MomoTransfer extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function createTransfer(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'account_number' => 'required|string',
            'amount' => 'required|numeric',
            'bank_code' => 'required|string',
            'currency' => 'required|string',
            'reason' => 'required|string',
            'meta.counterparty.name' => 'required|string',
            'meta.counterparty.email' => 'required|email',
            'meta.counterparty.phone_number' => 'required|string',
        ]);

        // Convert the amount to the lowest denomination
        $amountInLowestDenomination = intval($request->input('amount') * 100);

        // Generate a unique reference
        $reference = Str::uuid();

        // Prepare the payload for the API request
        $payload = [
            'currency' => $request->input('currency'),
            'meta' => [
                'scheme' => 'MOBILEMONEY',
                'counterparty' => [
                    'name' => $request->input('meta.counterparty.name'),
                    'email' => $request->input('meta.counterparty.email'),
                    'phone_number' => $request->input('meta.counterparty.phone_number'),
                ]
            ],
            'bank_code' => $request->input('bank_code'),
            'reason' => $request->input('reason'),
            'account_number' => $request->input('account_number'),
            'amount' => $amountInLowestDenomination, // Use the converted amount
            'reference' => $reference
        ];

        // Send the cURL request
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secretKey}",
        ])->post('https://sandbox.api.maplerad.com/v1/transfers', $payload);

        // Return the response from the API
        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json(),
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $response->json(),
            ], $response->status());
        }
    }
}
