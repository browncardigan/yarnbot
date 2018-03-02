<?php

define("DEV", $_SERVER['HTTP_HOST'] == 'localhost');

if (DEV) {
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}

define("PATH_ADDON", str_replace(array($_SERVER['DOCUMENT_ROOT'], "_conf.php"), "", __FILE__));
define("ROOT", $_SERVER['DOCUMENT_ROOT'] . PATH_ADDON);

// db
define("DB_HOST", "localhost");
define("DB_CONNECTION", "mysql");
define("DB_USER", "root");
define("DB_PASSWORD", "root");
define("DB_NAME", "junkee");

// ga
define("GA_PROFILE_ID", 103349026);
define("GA_API_PATH", ROOT . 'thirdparty/google-api-php-client/');
define("GA_API_AUTOLOAD_PATH", GA_API_PATH . 'vendor/autoload.php');
define("GA_API_CRED_PATH", GA_API_PATH . 'cred/service-account-credentials-junkee.json');

// cache
define("CACHE_DIR", ROOT . 'cache/junkee/');

// slack
define("SLACK_WEBHOOK_URL", "https://hooks.slack.com/services/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");

?>