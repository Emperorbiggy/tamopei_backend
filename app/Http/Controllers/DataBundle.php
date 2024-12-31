<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;

class DataBundle extends Controller
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
     * Function to make an API request to fetch specific service products using the access token
     */
    protected function makeApiRequest($accessToken, $networkId)
    {
        $apiEndpoint = "https://api.safehavenmfb.com/vas/service-category/$networkId/products";

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
     * Function to extract and format the relevant fields from the API response
     */
    protected function getServiceDataForNetwork($networkId)
    {
        try {
            // Get a fresh access token
            $accessToken = $this->getAccessToken();
            
            // Make API request to get service products using the fresh access token
            $specificServiceResponse = $this->makeApiRequest($accessToken, $networkId);
            $responseData = json_decode($specificServiceResponse, true);

            // Extract relevant fields from the response
            $result = [];
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                foreach ($responseData['data'] as $item) {
                    $result[] = [
                        'bundleCode' => $item['bundleCode'],
                        'amount' => $item['amount'],
                        'validity' => $item['validity']
                    ];
                }
            }

            // Return the formatted response as JSON
            return response()->json([
                "data" => $result
            ]);
            
        } catch (Exception $e) {
            // Handle any exceptions and return an error message
            return response()->json([
                "error" => "An error occurred: " . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the request to fetch service bundles for a network
     */
    public function getBundlesForNetwork(Request $request)
    {
        // Validate that networkId is provided in the request
        $request->validate([
            'networkId' => 'required|string',
        ]);

        $networkId = $request->input('networkId');

        // Get the service data for the network
        return $this->getServiceDataForNetwork($networkId);
    }
}
