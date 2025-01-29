<?php

require 'vendor/autoload.php';

use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger;
use TelegramFileDownloaderMadeline\UserClient;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['API_ID', 'API_HASH', 'UPLOAD_DIR', 'BASE_URL']);

// Optional environment variables with defaults
$loggerUsername = $_ENV['LOGGER_USERNAME'] ?? 'FileDownloader';

// Initialize settings
$settings = new Settings;
$settings->getAppInfo()
    ->setApiId((int)$_ENV['API_ID'])
    ->setApiHash($_ENV['API_HASH']);

$settings->getLogger()
    ->setLevel(Logger::LEVEL_VERBOSE)
    ->setExtra(['username' => $loggerUsername]);

// Create session name
$session = 'user.madeline';

// Start the client
UserClient::startAndLoop($session, $settings); 