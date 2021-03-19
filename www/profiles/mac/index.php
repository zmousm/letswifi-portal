<?php declare(strict_types=1);

/*
 * This file is part of letswifi; a system for easy eduroam device enrollment
 *
 * Copyright: 2018-2021, Jørn Åne de Jong, Uninett AS <jorn.dejong@uninett.no>
 * Copyright: 2020-2021, Paul Dekkers, SURF <paul.dekkers@surf.nl>
 * SPDX-License-Identifier: BSD-3-Clause
 */

require \implode(\DIRECTORY_SEPARATOR, [\dirname(__DIR__, 3), 'src', '_autoload.php']);

$app = new letswifi\LetsWifiApp();
$app->registerExceptionHandler();
$realm = $app->getRealm();
$app->getUserFromBrowserSession( $realm );

switch ( $_SERVER['REQUEST_METHOD'] ) {
	case 'GET': return $app->render(
		[
			'href' => '/profiles/mac/',
			'action' => '/profiles/new/',
			'device' => 'eap-config',
		], 'mobileconfig-mac-new' );
}

\header( 'Content-Type: text/plain', true, 405 );
exit( "405 Method Not Allowed\r\n" );
