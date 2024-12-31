<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Rate extends Controller
{
    /**
     * Handle the request to fetch exchange rates.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRate(Request $request)
    {
        // Validate the input parameters (source and target)
        $validated = $request->validate([
            'source' => 'required|string|max:3', // Example: USD
            'target' => 'required|string|max:3', // Example: NGN
        ]);

        $source = $validated['source'];
        $target = $validated['target'];

        // Check if a rate exists in the database for the provided source and target currencies
        $rate = DB::table('rates')
            ->where('source', $source)
            ->where('target', $target)
            ->first();

        // If no rate is found, return an error response
        if (!$rate) {
            return response()->json([
                'success' => false,
                'message' => "No exchange rate found for {$source} to {$target}.",
            ], 404);
        }

        // Return the found rate as a response
        return response()->json([
            'success' => true,
            'data' => $rate,
        ]);
    }
}
