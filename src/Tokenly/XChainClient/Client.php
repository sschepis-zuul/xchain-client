<?php 

namespace Tokenly\XChainClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Tokenly\HmacAuth\Generator;
use Exception;

/**
* XChain Client
*/
class Client
{
    
    function __construct($xchain_url, $api_key, $api_secret)
    {
        $this->xchain_url = $xchain_url;
        $this->api_key    = $api_key;
        $this->api_secret = $api_secret;
    }

    /**
     * creates a new payment address
     * @return array An array with an (string) id and (string) address
     */
    public function newPaymentAddress() {
        $result = $this->newAPIRequest('POST', '/addresses', []);
        return $result;
    }

    /**
     * monitor a new address
     * @param  string  $address          bitcoin/counterparty address
     * @param  string  $webhook_endpoint webhook callback URL
     * @param  string  $monitor_type     send or receive
     * @param  boolean $active           active
     * @return array                     The new monitor object
     */
    public function newAddressMonitor($address, $webhook_endpoint, $monitor_type='receive', $active=true) {
        $body = [
            'address'         => $address,
            'webhookEndpoint' => $webhook_endpoint,
            'monitorType'     => $monitor_type,
            'active'          => $active,
        ];
        $result = $this->newAPIRequest('POST', '/monitors', $body);
        return $result;
    }

    /**
     * creates a new payment address
     * @return array An array with an (string) id and (string) address
     */
    public function send($payment_address_id, $destination, $quantity, $asset, $sweep=false) {
        $body = [
            'destination' => $destination,
            'quantity'    => $quantity,
            'asset'       => $asset,
            'sweep'       => $sweep,
        ];
        $result = $this->newAPIRequest('POST', '/sends/'.$payment_address_id, $body);
        return $result;
    }


    protected function newAPIRequest($method, $path, $data=[]) {
        $api_path = '/api/v1'.$path;

        $client = new GuzzleClient(['base_url' => $this->xchain_url,]);

        $request = $client->createRequest($method, $api_path);
        if ($data AND $method == 'POST') {
            $request = $client->createRequest('POST', $api_path, ['json' => $data]);
        } else if ($method == 'GET') {
            $request = $client->createRequest($method, $api_path, ['query' => $data]);
        }

        // add auth
        $this->getAuthenticationGenerator()->addSignatureToGuzzleRequest($request, $this->api_key, $this->api_secret);
        \LTBAuctioneer\Debug\Debug::trace("\$request=".$request,__FILE__,__LINE__,$this);
        
        // send request
        try {
            $response = $client->send($request);
            \LTBAuctioneer\Debug\Debug::trace("\$response=".$response,__FILE__,__LINE__,$this);
        } catch (RequestException $e) {
            if ($response = $e->getResponse()) {
                \LTBAuctioneer\Debug\Debug::trace("[ERROR] \$response=".$response,__FILE__,__LINE__,$this);
                // interpret the response and error message
                $code = $response->getStatusCode();
                $json = $response->json();
                if ($json and isset($json['message'])) {
                    throw new Exception($json['message'], $code);
                }
            }

            // if no response, then just throw the original exception
            throw $e;
        }

        $json = $response->json();
        if (!is_array($json)) { throw new Exception("Unexpected response", 1); }

        return $json;
    }

    protected function getAuthenticationGenerator() {
        $generator = new Generator();
        return $generator;
    }

}