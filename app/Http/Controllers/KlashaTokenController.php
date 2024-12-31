<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KlashaTokenController extends Controller
{
    /**
     * Fetch the Klasha token and return the result.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchToken()
    {
        // Get the Klasha token response using the service provider
        $klashaResponse = app('KlashaToken');

        // Return the response as JSON
        return response()->json($klashaResponse, 200, [], JSON_PRETTY_PRINT);
    }
}
