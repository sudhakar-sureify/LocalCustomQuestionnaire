<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$config = [];
$config['enabled_loggers'] = array(
     'file',         // No separate config required, uses log files as per CI config
);

// if (getenv('ENVIRONMENT') != 'local') {
//     $config['enabled_loggers'][] = 'papertrail';
//     // $config['enabled_loggers'][] = 'sentry';
//     if (!empty(getenv("SENTRY_DSN"))) {
//         $config['enabled_loggers'][] = 'sentry';
//     }
// }

// $config['loggers'] = array(
//     'papertrail' => array(
//         'hostname' => getenv('PAPERTRAIL_HOST'),
//         'port' => getenv('PAPERTRAIL_PORT'),
//     ),
//     'database' => array(
//         // 'dsn' => '<drivername>://<username>:<password>@<hostname>/<dbname>
//         'dsn' => 'mysqli://logger:SomePassword@123@localhost/applyandbuy',
//         'tablename' => 'error_logs',
//     ),
// );
