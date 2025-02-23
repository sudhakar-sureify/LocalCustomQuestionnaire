<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('get_converter')) {
    function get_converter($type) {
        switch ($type) {
            case 'mras':
                return new MrasConverter();
            case 'swisre':
                return new SwisreConverter();
            default:
                throw new Exception("Invalid client type");
        }
    }
}

if (!function_exists('get_questionnaire')) {
    function get_questionnaire($type) {
        switch ($type) {
            case 'mras':
                return new QuestionnaireMras();
            default:
                throw new Exception("Invalid client type");
        }
    }
}
if (!function_exists('get_disclosure')) {
    function get_disclosure($type) {
        switch ($type) {
            case 'mras':
                return new QuestionnaireMras();
            default:
                throw new Exception("Invalid client type");
        }
    }
}
if(!function_exists('makeAPICall')) {
    function makeAPICall($api_config, $params = null, $curl_params = array())
    {
        $arguments = func_get_args();
        $url = $api_config['apiUrl'];
        $requesttype = strtoupper($api_config['method']);
        $headers = $api_config['headerParams'];
        if (isset($params['headers']) && is_array($params['headers'])) {
            $headers = array_merge($headers, $params['headers']);
            unset($params['headers']);
        }
        if (empty($api_config['defaultParams'])) {
            $api_config['defaultParams'] = [];
        }
        if (!isset($params) && empty($params)) {
            $params = [];
        }
        $encode = false;
        if (!is_array($api_config['defaultParams'])) {
            $encode = true;
            $api_config['defaultParams'] = json_decode($api_config['defaultParams'], true);
        }
        if (!is_array($params)) {
            $encode = true;
            $temp_params = json_decode($params, true, 512, JSON_FORCE_OBJECT);
            if (empty($temp_params)) {
                $validate_xml = simplexml_load_string($params);
                if ($validate_xml !== FALSE) {
                    $encode = false;
                    $temp_params = array('xml_string' => $params);
                }
            }
            $params = $temp_params;
        }
        $params = array_merge($api_config['defaultParams'], is_array($params)?$params:[]);
        if ($encode or $api_config['callback_method'] == "encode") {
            if (isset($params['headers']) and
                !empty($params['headers'])) {
                $headers = array_merge($headers, $params['headers']);
                unset($params['headers']);
            }
            $params = json_encode($params);
        }
        $api_config['headers'] = $headers;
        // Merging defaultParams in $api_config and $params END

        //generate query string only if its a get query function
        if ($requesttype == 'GET') {
            $params_query = http_build_query(is_array($params)?$params:[]);
            $url .= '?' . $params_query;
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (substr($url, 0, 5) == "https") {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        if(!empty($api_config['sslkey']) && !empty($api_config['sslcert'])){
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);
            curl_setopt($curl, CURLOPT_SSLKEY, constant($api_config['sslkey']));
            curl_setopt($curl, CURLOPT_SSLCERT, constant($api_config['sslcert']));
       }
        if (count($headers) > 0) {
            foreach ($curl_params as $key => $value) {
                if ($key == 'CURLOPT_HTTPHEADER') {
                    array_push($headers, $value);
                }
            }
            $headers[] = 'Expect:';
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if ($requesttype == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $api_timeout = $api_config['timeout'] ?? 800;
        curl_setopt($curl, CURLOPT_TIMEOUT, $api_timeout);


        if ($requesttype == 'POST') {
            foreach ($headers as $key => $value) {
                if (strpos($value, 'x-www-form-urlencoded') !== False) {
                    $params = http_build_query($params);
                }
            }
            if (is_array($params)) {
                curl_setopt($curl, CURLOPT_POST, count($params));
            }
            if (isset($params['xml_string'])) {
                $params = $params['xml_string'];
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }

        if ($requesttype == "PUT") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }
        if ($requesttype == "PATCH") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }
        if ($requesttype == "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $temp_params);
        }
        foreach ($curl_params as $curl_param_key => $curl_param_value) {
            curl_setopt($curl, $curl_param_key, $curl_param_value);
        }

        $retries = $max_retries = $api_config['retry_config']['max_retry'] ?? 0;
        $retry_sleep = $api_config['retry_config']['retry_sleep'] ?? 0;
        //Execute the cURL request.
        $response = curl_exec($curl);
        return $response;
    }
}