<?php

/**
 * Tracks the log and remove sensitive information
 * @author saibabu <saibabu@sureify.com>
 * @createdDate 21-Aug-2019
 * @version "1.0.0"
 * @since version 1.0.0
 */
//namespace Utility;
class LogModerator {

    private static $defaultConfig = [
        "enable_constants" => true,
        "enable_env" => true,
        "enable_ssn" => true,
        "enable_phonenumber" => true,
        "enable_ipaddress" => false,
        "constants_list" => [
            "ORG_ID",
            "ORG_ACCESS_TOKEN",
            "MAGNUM_CLIENT_ID",
            "MAGNUM_RULE_BASE_NAME",
            "TRANSMIT_FILE_DEST_DIR",
            "SFTP_HOST",
            "SFTP_USERNAME",
            "SFTP_PWD",
            "ENCRYPT_SALT",
            "DOCUSIGN_USERNAME",
            "DOCUSIGN_PASSWORD",
            "DOCUSIGN_INTEGRATOR_KEY",
            "AWS_BUCKET",
            "AWS_PVT_BUCKET",
            "AWS_PVT_KMS_KEY_ID",
            "AWS_KMS_KEY_ID",
            "AWS_ACCESS_KEY_ID",
            "AWS_SECRET_ACCESS_KEY",
            "AWS_PVT_ACCESS_KEY_ID",
            "AWS_PVT_SECRET_ACCESS_KEY",
            "REDIS_HOST",
            "REDIS_PASSWORD",
            "ANB_RAW_PATH",
            "COMPONENTS_PATH",
            "HELPERS_PATH"
        ],
        "constants_ignore_list" => [],
        "env_list" => [
            "PHINX_MYSQL_READ_HOST",
            "PHINX_MYSQL_READ_USER",
            "PHINX_MYSQL_PWD",
            "PHINX_MYSQL_HOST",
            "PHINX_MYSQL_READ_DB",
            "PHINX_MYSQL_USER",
            "PHINX_MYSQL_DB",
            "APACHE_LOCK_DIR",
            "APACHE_LOG_DIR",
            "PHINX_MYSQL_READ_PWD",
            "AWS_ACCESS_KEY_ID",
            "AWS_SECRET_ACCESS_KEY",
        ],
        "env_ignore_list" => [],
        "enable_regex" => false,
        "censored_text" => "{#removed}"
    ];
    /*
      future implementation to support regex in constant names
      "*_TOKEN",
      "*_KEY",
      "*_ID",
      "*_PASSWORD",
      "*_PWD"
     */
    private static $config = null;

    public function get($name) {
        return self::$config[$name];
    }

    public function set($name, $value) {
        self::$config[$name] = $value;
    }

    /**
     * function to remove/replace sensitive information for a given text
     */
    public static function Moderate(string $message, $reg_replace = null) {
        if (is_null(self::$config)) {
            self::$config = self::$defaultConfig;
        }
        //self::$config = self::$defaultConfig;
        //var_dump(self::$defaultConfig);exit;
        if (!is_null($reg_replace)) {
            self::$config['censored_text'] = $reg_replace;
        }
        if (!empty($message)) {
            $message = isset(self::$config['enable_constants']) && self::$config['enable_constants'] ? self::FilterConstantsData($message) : $message;
            $message = isset(self::$config['enable_env']) && self::$config['enable_env'] ? self::FilterEnvData($message) : $message;
            $message = isset(self::$config['enable_ssn']) && self::$config['enable_ssn'] ? self::FilterSSN($message) : $message;
            $message = isset(self::$config['enable_phonenumber']) && self::$config['enable_phonenumber'] ? self::FilterPhoneNumber($message) : $message;
            $message = isset(self::$config['enable_ipaddress']) && self::$config['enable_ipaddress'] ? self::FilterIpAddress($message) : $message;
        }
        return $message;
    }

    /**
     * function to rest configuration
     */
    public static function resetConfig() {
        self::$config = self::$defaultConfig;
    }

    private static function getConstantValue($constant_name) {
        if (defined($constant_name)) {
            return constant($constant_name);
        }
        return null;
    }

    private static function FilterConstantsData(string $message) {
        $original_message = $message;
        try {
            if (isset(self::$config['enable_constants']) && isset(self::$config['constants_list'])) {
                foreach (self::$config['constants_list'] as $contant_name) {
                    $const_value = self::getConstantValue($contant_name);
                    if (!is_null($const_value) && !is_bool($const_value) && !empty($const_value)) {
                        $message = self::__replaceData($message, $const_value, self::$config['censored_text']);
                    }
                }
            }
            $original_message = $message;
        } catch (\Throwable $ex) {
            //sliently ignore error			
        } catch (\Exception $ex) {
            //sliently ignore error
        } finally {
            return $original_message;
        }
    }

    private static function FilterEnvData(string $message) {
        $original_message = $message;
        try {
            foreach (self::$config['env_list'] as $key) {
                $value = getenv($key);
                if (isset($value) && !is_null($value) && !is_bool($value) && !empty($value)) {
                    $message = self::__replaceData($message, $value, self::$config['censored_text']);
                }
            }
            $original_message = $message;
        } catch (\Throwable $ex) {
            //sliently ignore error			
        } catch (\Exception $ex) {
            //sliently ignore error
        } finally {
            return $original_message;
        }
    }

    public static function FilterSSN(string $message) {
        $original_message = $message;
        try {
            $original_message = self::FilterRegularExpression($message, "/[0-9]{3}-[0-9]{2}-[0-9]{4}/i");
        } catch (\Throwable $ex) {
            //sliently ignore error			
        } catch (\Exception $ex) {
            //sliently ignore error
        } finally {
            return $original_message;
        }
    }

    public static function FilterPhoneNumber(string $message) {
        $original_message = $message;
        try {
            $original_message = self::FilterRegularExpression($message, "/[0-9]{3}-[0-9]{3}-[0-9]{4}/i");
        } catch (\Throwable $ex) {
            //sliently ignore error			
        } catch (\Exception $ex) {
            //sliently ignore error
        } finally {
            return $original_message;
        }
    }

    private static function FilterIpAddress(string $message) {
        $original_message = $message;
        try {
            $original_message = self::FilterRegularExpression($message, "/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/i");
        } catch (\Throwable $ex) {
            //sliently ignore error			
        } catch (\Exception $ex) {
            //sliently ignore error
        } finally {
            return $original_message;
        }
    }

    /**
     * replaces text based on given pattern
     *
     * @param	string	$string subject text
     * @param	string	$pattern regular expression to search and replace 
     * @return	string
     */
    private static function FilterRegularExpression(string $string, $pattern) {
        if (is_bool($pattern)) {
            return $string;
        }
        $string = preg_replace($pattern, self::$config['censored_text'], $string);
        return $string;
    }

    /**
     * replace data function
     *
     * Supply a string and an array of disallowed words and any
     * matched words will be converted to #### or to the replacement
     * word you've submitted.
     *
     * @param	string	the text string
     * @param	string	the array of censored words
     * @param	string	the optional replacement value
     * @return	string
     */
    private static function __replaceData($str, $censored, $replacement = '') {
        if (!is_array($censored)) {
            $censored = [$censored];
        }
        $str = ' ' . $str . ' ';
        foreach ($censored as $badword) {
            $str = str_replace($badword, $replacement, $str);
        }
        return trim($str);
    }

}
