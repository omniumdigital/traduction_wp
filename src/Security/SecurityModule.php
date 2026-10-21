<?php
/**
 * Security services.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers crypto, key storage and PII filtering.
 */
final class SecurityModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( Crypto::class, static fn() => new Crypto() );
		$container->set( ApiKeyStore::class, static fn( Container $c ) => new ApiKeyStore( $c->get( Crypto::class ) ) );
		$container->set( PiiFilter::class, static fn() => new PiiFilter() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {}
}
