<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class QuestionnaireMras {
    public function __construct() {
        $this->CI =& get_instance();
    }
    public function createCase($payload) {
        $this->token = self::getAuthToken();
        $token = $this->token['access_token'];
        $api_config = [
            "reqProtocol" => "curl",
            "method" => "POST",
            "apiUrl" => BRIGHTHOUSE_FULL_CASE_URL,
            "headerParams" => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $token
            ],
            "apiDisplayName" => "create Full Case",
        ];
        $payload = json_encode($payload);
        try{
            $response_data = makeAPICall($api_config,$payload);
        }
        catch (APIException $e){
            Logger::logException($e);
         }
        
        return json_decode($response_data, true);
    }
    public function getDisclosure($question_id,$payload,$url,$method) {
        $token = self::getAuthToken();
        $api_config = [
            "reqProtocol"=>"curl",
            "method" => $method,
            "apiUrl" =>$url.$question_id,
            "headerParams" => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $token['access_token']
            ],
            "apiDisplayName" => "get Disclosure Source",
        ];

        try {
            $response_data = makeAPICall($api_config, $payload);
        } catch (APIException $e) {
            Logger::logException($e);
        }

        if ($response_data) {
            return json_decode($response_data, true);
        }
    }
    public static function getAuthToken($force = false)
    {
        $api_config = [];
        try {
            if(!empty(MRAS_BEARER_TOKEN))
            {
                $api_config = ["getAccessToken" => [
                    "reqProtocol" => "curl",
                    "method" => "POST",
                    "apiUrl" => BRIGHTHOUSE_TOKEN_URL,
                    "headerParams" => [
                        "Content-Type: application/x-www-form-urlencoded",
                        "Authorization: ".MRAS_BEARER_TOKEN
                    ],
                    "defaultParams"=> [
                        "grant_type" => "client_credentials"
                    ],
                    "apiDisplayName" => "get Access Token",
                ]];
            }
            else
            {
                $api_config = ["getAccessToken" => [
                    "reqProtocol" => "curl",
                    "method" => "POST",
                    "apiUrl" => BRIGHTHOUSE_TOKEN_URL,
                    "headerParams" => [
                        "Content-Type: application/x-www-form-urlencoded",
                    ],
                    "defaultParams"=> [
                        "grant_type" => "client_credentials",
                        "client_id" => BRIGHTHOUSE_CLIENT_ID,
                        "client_secret" => BRIGHTHOUSE_CLIENT_SECRET
                    ],
                    "apiDisplayName" => "get Access Token",
                ]];
            }
            $response_data = makeAPICall($api_config['getAccessToken']);
            $response_data = json_decode($response_data, true);

        }
        catch (MalformedExternalRequest $e) {
            Logger::logException($e);
            return $error_map;
        }
        return $response_data;
    }
}