#!/usr/bin/env php
<?php declare(strict_types=1);
if ( PHP_SAPI !== 'cli' ) {
	header( 'Content-Type: text/plain', true, 403 );
	die( "403 Forbidden\r\n\r\nThis script is intended to be run from the commandline only\r\n");
}
if ( sizeof( $argv ) !== 1 ) {
	echo $argv[0] . ' takes no arguments' . PHP_EOL;
	die( 2 );
}
require implode(DIRECTORY_SEPARATOR, [dirname(__DIR__, 1), 'src', '_autoload.php']);

use fyrkat\openssl\CSR;
use fyrkat\openssl\DN;
use fyrkat\openssl\OpenSSLConfig;
use fyrkat\openssl\PrivateKey;

$app = new letswifi\LetsWifiApp();
$app->registerExceptionHandler();
$realmManager = $app->getRealmManager();

$serverNames = $realmManager->getAllServerNames();
echo implode( PHP_EOL, $serverNames ) . PHP_EOL;
