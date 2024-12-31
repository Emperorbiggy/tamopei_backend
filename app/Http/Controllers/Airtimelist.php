<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class Airtimelist extends Controller
{
    /**
     * Retrieve the access token from the service container
     */
    protected function getAccessToken()
    {
        return app('AccessToken');
    }

    /**
     * Function to make an API request to a specific service category
     */
    protected function makeApiRequest($accessToken, $serviceId)
    {
        $apiEndpoint = "https://api.safehavenmfb.com/vas/service/$serviceId/service-categories";

        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "ClientID: 9e0763f989fd3090583662e05117fdca",
            "accept: application/json",
            "authorization: Bearer $accessToken"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true); // Decode JSON response into associative array
    }

    /**
     * Fetch and format service categories
     */
    public function fetchServiceCategories(Request $request)
    {
        try {
            // Get a fresh access token
            $accessToken = $this->getAccessToken();

            // Service ID (you can pass this as a request parameter if dynamic)
            $serviceId = "61efaba1da92348f9dde5f6c";

            // Make API request
            $apiResponse = $this->makeApiRequest($accessToken, $serviceId);

            // Extract and format the response
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $formattedResponse = array_map(function ($network) {
                    return [
                        'name' => $network['identifier'],
                        'logo' => $network['logoUrl'],
                        'id' => $network['_id']
                    ];
                }, $apiResponse['data']);

                return response()->json([
                    'statusCode' => 200,
                    'data' => $formattedResponse
                ]);
            } else {
                return response()->json([
                    'statusCode' => 204,
                    'message' => 'No data found'
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => 'An error occurred: ' . $e->getMessage()
            ]);
        }
    }
}
