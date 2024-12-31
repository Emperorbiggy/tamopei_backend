<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;

class DataList extends Controller
{
    /**
     * Retrieve the access token from the service container
     */
    protected function getAccessToken()
    {
        // Assuming you have a service or way to fetch the AccessToken
        return app('AccessToken');
    }

    /**
     * Function to make an API request to service categories using the access token
     */
    protected function makeApiRequest($accessToken, $serviceId)
    {
        $apiEndpoint = "https://api.safehavenmfb.com/vas/service/$serviceId/service-categories";

        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "ClientID: 9e0763f989fd3090583662e05117fdca",
            "accept: application/json",
            "authorization: Bearer $accessToken"
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response; // Return the raw response for full visibility
    }

    /**
     * Function to format the JSON response
     */
    protected function formatApiResponse($json)
    {
        $data = json_decode($json, true);
        
        if (isset($data['data']) && is_array($data['data'])) {
            $formattedResponse = array_map(function($item) {
                return [
                    'id' => $item['_id'], // Map '_id' to 'id'
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'logoUrl' => $item['logoUrl']
                ];
            }, $data['data']);
            
            return json_encode(['statusCode' => 200, 'data' => $formattedResponse]);
        } else {
            return json_encode(['statusCode' => 204, 'message' => 'No data found']);
        }
    }

    /**
     * Handle the request for service categories
     */
    public function getServiceCategories(Request $request)
    {
        try {
            // Get a fresh access token
            $accessToken = $this->getAccessToken();

            // Define service ID (could be dynamic based on user input)
            $serviceId = "61efabb2da92348f9dde5f6e";

            // Make API request to fetch service categories
            $apiResponse = $this->makeApiRequest($accessToken, $serviceId);

            // Format the API response
            $formattedResponse = $this->formatApiResponse($apiResponse);

            // Return the formatted response as JSON
            return response()->json(json_decode($formattedResponse));

        } catch (Exception $e) {
            // Handle any exceptions and return an error message
            return response()->json([
                'statusCode' => 500,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ]);
        }
    }
}
