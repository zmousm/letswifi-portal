<?php declare(strict_types=1);

/*
 * This file is part of letswifi; a system for easy eduroam device enrollment
 *
 * Copyright: 2018-2020, Jørn Åne de Jong, Uninett AS <jorn.dejong@uninett.no>
 * SPDX-License-Identifier: BSD-3-Clause
 */

namespace letswifi;

use DomainException;

use fkooman\Template\Tpl;

use fyrkat\oauth\Client;
use fyrkat\oauth\JWTSigner;
use fyrkat\oauth\OAuth;

use letswifi\browserauth\BrowserAuthInterface;

use PDO;
use RuntimeException;
use Throwable;

class LetsWifiApp
{
	const HTTP_CODES = [
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
	];

	/** @var Config */
	private $config;

	/** @var ?PDO */
	private $pdo;

	/** @var ?Tpl */
	private $tpl;

	public function __construct( Config $config = null )
	{
		$this->config = $config ?? new Config();
	}

	public function getUserFromBrowserSession( Realm $realm ): User
	{
		$auth = $this->getBrowserAuthenticator( $realm );
		$userId = $auth->requireAuth();

		return new User( $userId );
	}

	/**
	 * @psalm-suppress InvalidStringClass
	 */
	public function getBrowserAuthenticator( Realm $realm ): BrowserAuthInterface
	{
		$params = $this->config->getArrayOrEmpty( 'auth.params' );
		$service = $this->config->getString( 'auth.service' );
		$realmParams = $this->config->getArrayOrEmpty( 'realm.auth' );
		if ( \array_key_exists( $realm->getName(), $realmParams ) ) {
			$params = \array_merge( $params, $realmParams[$realm->getName()] );
		}

		if ( !\preg_match( '/[A-Z][A-Za-z0-9]+/', $service ) ) {
			throw new DomainException( 'Illegal auth.service specified in config' );
		}
		$service = 'letswifi\\browserauth\\' . $service;
		$result = new $service( $params );
		if ( $result instanceof BrowserAuthInterface ) {
			return $result;
		}
		throw new DomainException( 'auth.service must point to a class that implements BrowserAuthInterface' );
	}

	public function getOAuthHandler( Realm $realm ): OAuth
	{
		$oauth = new OAuth( new JWTSigner( $realm->getSecretKey() ) );
		foreach ( $this->config->getArray( 'oauth.clients' ) as $client ) {
			$oauth->registerClient( new Client( $client['clientId'], $client['redirectUris'], $client['scopes'] ) );
		}

		return $oauth;
	}

	public function getRealm( string $realmName = null ): Realm
	{
		if ( null === $realmName ) {
			$realmName = $this->getCurrentRealmName();
		}

		return new Realm( $this->getPDO(), $realmName );
	}

	public function guessRealm( Realm $baseRealm ): ?Realm
	{
		$auth = $this->getBrowserAuthenticator( $baseRealm );
		$guess = $auth->guessRealm( $this->config->getArrayOrEmpty( 'realm.auth' ) );

		return $guess ? $this->getRealm( $guess ) : null;
	}

	public function registerExceptionHandler(): void
	{
		\set_exception_handler( [$this, 'handleException'] );
	}

	public function handleException( Throwable $ex ): void
	{
		\error_log( $ex->__toString() );
		$code = $ex->getCode();
		if ( !\is_int( $code ) || !\array_key_exists( $code, static::HTTP_CODES ) ) {
			$code = 500;
		}
		$codeExplain = static::HTTP_CODES[$code];
		$message = $ex->getMessage();
		\header( 'Content-Type: text/plain', true, $code );
		echo "${code} ${codeExplain}\r\n\r\n${message}\r\n";
	}

	public function render( array $data, ?string $template = null ): void
	{
		if ( null === $template || \array_key_exists( 'json', $_GET ) || false === \strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' ) ) {
			\header( 'Content-Type: application/json' );
			die( \json_encode( $data ) );
		}
		die( $this->getTemplate()->render( $template, $data ) );
	}

	protected function getTemplate(): Tpl
	{
		if ( null === $this->tpl ) {
			$dirs = $this->config->getArrayOrNull('tpldir') ?? [\implode( \DIRECTORY_SEPARATOR, [\dirname( __DIR__, 2 ), 'tpl'])];
			$this->tpl = new Tpl( $dirs );
		}

		return $this->tpl;
	}

	protected function getPDO(): PDO
	{
		if ( null === $this->pdo ) {
			$dsn = $this->config->getString( 'pdo.dsn' );
			$username = $this->config->getStringOrNull( 'pdo.username' );
			$password = $this->config->getStringOrNull( 'pdo.password' );

			$this->pdo = new PDO( $dsn, $username, $password );
			$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		}

		return $this->pdo;
	}

	private function getCurrentRealmName(): string
	{
		if ( !\array_key_exists( 'realm', $_GET ) ) {
			throw new RuntimeException( 'No realm set' );
		}
		if ( \is_string( $_GET['realm'] ) ) {
			return $_GET['realm'];
		}
		throw new RuntimeException( 'realm parameter must be string' );
	}
}
