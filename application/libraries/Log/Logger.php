<?php
/**
 * Library to handle logging within the application
 *
 * @category    Internal_Library
 * @package     Applyandbuy
 * @author      Asad Siddiqui <asad@sureify.com>
 * @createdDate 25-April-2019
 */
require_once APPPATH . 'libraries/Log/LogModerator.php';
use Sentry;

/**
 * Utility class to handle application level error logs
 *
 * @category Internal_Library
 * @package  Applyandbuy
 * @author   Asad Siddiqui <asad@sureify.com>
 * @license  Private http://null
 */
class Logger
{
    private static $instance = null;
    private static $enabled_loggers;
    private static $db;
    private static $tablename;
    private static $papertrail_host;
    private static $papertrail_port;
    private static $log_threshold;
    /**
     * Function to initialize private variables within class
     *
     * @return void
     */
    public static function initialize() {
        if (self::$instance === null) {
            self::$instance = &get_instance();
            self::$instance->config->load('logging', TRUE);
            self::$enabled_loggers = self::$instance->config->item('enabled_loggers','logging');
            self::$log_threshold = self::$instance->config->item('log_threshold') ?? [];
            self::$tablename = "";
            self::$db = "";
            self::$papertrail_host = "";
            self::$papertrail_port = "";

            if (in_array('database', self::$enabled_loggers)) {
                $dsn = self::$instance->config->item('loggers')['database']['dsn'];
                self::$tablename = self::$instance->config->item('loggers')['database']['tablename'];
                self::$db = self::$instance->load->database($dsn, true);
            }

            if (in_array('papertrail', self::$enabled_loggers)) {
                self::$papertrail_host = self::$instance->config->item('loggers')['papertrail']['hostname'];
                self::$papertrail_port = self::$instance->config->item('loggers')['papertrail']['port'];
            }

            if (in_array('sentry', self::$enabled_loggers)) {
                self::initializeSentry();
            }
        }
    }

    private static function initializeSentry() {
        Sentry\init([
            'environment' => getenv('ENVIRONMENT'),
            'dsn' => getenv('SENTRY_DSN'),
            'error_types' => E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED & ~E_WARNING
        ]);
        Sentry\configureScope(function (Sentry\State\Scope $scope): void {
            $user_info = [
                'id' => $_POST['user_id'],
                // 'email' => 'test@test.com',
                // 'username' => 'test'
            ];
            $scope->setUser($user_info);
        });
    }
    
    /**
     * Function to log error to database
     *
     * @param array $params an array which contains the following values
     *                      error_type, error_message, error_file, error_line
     *
     * @return void
     */
    private static function db($params) {
        unset($params['format']);
        try {
            self::$db->insert(self::$tablename, $params);
        } catch (Exception $e) {
            log_message('ERROR', 'Error while logging error to DB');
        }
    }

    /**
     * Function to log error to papertrail service
     *
     * @param array $params an array which contains the following values
     *                      error_type, error_message, error_file, error_line
     *
     * @return void
     */
    private static function papertrail($input) {
        if (is_array($input)) {
            $papertrail_input = array(
                $input['format'],
                $input['error_type'],
                $input['error_message']
            );
            $message = call_user_func_array('sprintf', $papertrail_input);
        } else {
            $message = $input;
        }
        try {
            $uid = $_POST["uid"];
            if (empty($uid)) {
                $uid = $_GET["uid"];
            }
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            $message = LogModerator::Moderate($message);
            if (!empty($uid)) {
                $message = $uid . " - " . LogModerator::Moderate($message);
                //$message = encryptData($uid, "base64") . " - " . LogModerator::Moderate($message);
            }
            socket_sendto($sock, $message, strlen($message), 0, self::$papertrail_host, self::$papertrail_port);
            socket_close($sock);
        } catch (Exception $e) {
            log_message('ERROR', 'Error while sending log to papertrail :' . $e->getMessage());
        }
    }

    private static function sentry($exception) {
        Sentry\captureException($exception);
    }
    
    /**
     * Function to log error to all enabled loggers
     *
     * @param Object $exception The exception object caught in the catch block
     *
     * @return void
     */
    public static function logException($exception) {
        self::initialize();
        $errors = self::_getErrorCodes();
        $code = $exception->getCode();
        $code = $code ? $code : E_USER_ERROR;
        $params = array(
            // 'format' =>"%s - %s at %s:%s",
            'format' =>"%s - %s",
            'error_type' => $errors[$code] .' '. get_class($exception),
            'error_message' => $exception->getMessage(),
            // 'error_file' => $exception->getFile(),
            // 'error_line' => $exception->getLine()
        );
        if (in_array('file', self::$enabled_loggers)) {
            log_message('ERROR', LogModerator::Moderate(strtr("{error_type} - {error_message}", $params)));
        }
        if (in_array('database', self::$enabled_loggers)) {
            self::db($params);
        }
        if (in_array('papertrail', self::$enabled_loggers)) {
            self::papertrail($params);
        }
        if (in_array('sentry', self::$enabled_loggers)) {
            self::sentry($exception);
        }
    }
    
    public static function logMessage($level, $message) {
        self::initialize();
        $message = LogModerator::Moderate($message);
        if (in_array('file', self::$enabled_loggers)) {
            log_message($level, $message);
        }
        // TODO: Need to add support for database logging
        //        if (in_array('database', self::$enabled_loggers)) {
        //            self::db($params);
        //        }
        if (in_array('papertrail', self::$enabled_loggers)) {
            self::papertrail($level . " - " . $message);
        }
    }


    /**
     * Function to log info messages to all enabled loggers
     *
     * @param string $message The message to be logged
     *
     * @return void
     */
    public static function log($message) {
        self::initialize();
        $str = (isset($_POST['uid'])) ? " UID = ".$_POST['uid'] : (isset($_GET['uid']) ? " UID = ".$_GET['uid'] : "");
        self::logMessage('LOG', $message.$str);
    }
    
    /**
     * Function to log error messages to all enabled loggers
     *
     * @param string $message The message to be logged
     *
     * @return void
     */
    public static function logError($message) {
        self::initialize();
        $str = (isset($_POST['uid'])) ? " UID = ".$_POST['uid'] : (isset($_GET['uid']) ? " UID = ".$_GET['uid'] : "");
        self::logMessage('ERROR', $message.$str);
    }
    
    /**
     * Function to log debug messages to all enabled loggers
     *
     * @param string $message The message to be logged
     *
     * @return void
     */
    public static function debug($message) {
        self::initialize();
        if (in_array(3, self::$log_threshold)) {
            $str = (isset($_POST['uid'])) ? " UID = ".$_POST['uid'] : (isset($_GET['uid']) ? " UID = ".$_GET['uid'] : "");
            self::logMessage('DEBUG', $message.$str);
        }
    }
    
    
    /**
     * Function to get a list of all defined error code with their names
     *
     * @return array
     */
    private static function _getErrorCodes() {
        $codes = [];
        $constants = get_defined_constants();
        foreach ($constants as $name => $value) {
            if (strpos($name, 'E_') === 0) {
                $codes[$value] = $name;
            }
        }
        return $codes;
    }
}
