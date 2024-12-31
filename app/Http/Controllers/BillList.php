<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillList extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Assuming 'AccessToken' is registered in the service container
    }

    // Function to get service categories using the access token
    public function getServiceCategories()
    {
        $accessToken = $this->getAccessToken(); // Retrieve the access token

        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve access token.'
            ], 500);
        }

        $url = "https://api.safehavenmfb.com/vas/service/61efab78b5ce7eaad3b405d0/service-categories";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "ClientID: 9e0763f989fd3090583662e05117fdca",  // Add the ClientID header
            "Authorization: Bearer $accessToken",        // Include the access token in the Authorization header
            "accept: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($responseData) {
            return response()->json($responseData); // Return the service categories as JSON
        } else {
            return response()->json([
                'message' => 'Failed to fetch service categories or invalid access token.'
            ], 500);
        }
    }
}
