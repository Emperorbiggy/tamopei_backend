<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class MomoCollection extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function createCollection(Request $request)
{
    // Validate the incoming request
    $request->validate([
        'account_number' => 'required|string',
        'amount' => 'required|numeric',
        'bank_code' => 'required|string',
        'currency' => 'required|string',
        'description' => 'required|string',
        'meta.counterparty.first_name' => 'required|string',
        'meta.counterparty.last_name' => 'required|string',
        'meta.counterparty.email' => 'required|email',
        'meta.counterparty.phone_number' => 'required|string',
    ]);

    // Generate a unique reference
    $reference = Str::uuid();

    // Convert amount to kobo (multiply by 100)
    $amountInKobo = $request->input('amount') * 100;

    // Prepare the payload
    $payload = [
        'account_number' => $request->input('account_number'),
        'amount' => $amountInKobo, // Use the amount in kobo here
        'bank_code' => $request->input('bank_code'),
        'currency' => $request->input('currency'),
        'description' => $request->input('description'),
        'reference' => $reference,
        'meta' => [
            'counterparty' => [
                'first_name' => $request->input('meta.counterparty.first_name'),
                'last_name' => $request->input('meta.counterparty.last_name'),
                'email' => $request->input('meta.counterparty.email'),
                'phone_number' => $request->input('meta.counterparty.phone_number'),
            ],
        ],
    ];

    try {
        // Send the POST request to the Maplerad API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://sandbox.api.maplerad.com/v1/collections/momo', $payload);

        // Handle the response
        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json(),
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create MOMO collection',
                'details' => $response->json(),
            ], $response->status());
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred',
            'details' => $e->getMessage(),
        ], 500);
    }
}

}
