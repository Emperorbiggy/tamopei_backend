<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function makePayment(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'price' => 'required|numeric',
            'email' => 'required|email',
            'amount' => 'required|numeric',
        ]);

        // Send request to Haystack API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer sk_live_e6b5d21810aece3611b35816d89376024cac2212',
            'Content-Type' => 'application/json',
        ])->post('https://api.haystack.com/checkout', [
            'amount' => $validated['amount'],
            'currency' => 'USD', // Adjust as needed
            'email' => $validated['email'],
            'metadata' => [
                'price' => $validated['price'],
            ],
            // Additional data for your payment flow
        ]);

        if ($response->successful()) {
            // Return checkout link to frontend
            return response()->json(['checkout_link' => $response->json()['data']['checkout_url']]);
        }

        // Handle errors
        return response()->json(['error' => 'Failed to create payment'], 500);
    }
}
