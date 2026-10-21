<?php
/**
 * Minimal dependency injection container.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lazy singleton container keyed by class or interface name.
 */
final class Container {

	/**
	 * Registered factories.
	 *
	 * @var array<string, callable(Container): object>
	 */
	private array $factories = array();

	/**
	 * Resolved instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Register a factory. The service is built once, on first get().
	 *
	 * @param string                      $id      Class or interface name.
	 * @param callable(Container): object $factory Factory receiving the container.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Class or interface name.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a service.
	 *
	 * @template T of object
	 * @param class-string<T> $id Class or interface name.
	 * @return T
	 * @throws \OutOfBoundsException When the service is not registered.
	 * @throws \UnexpectedValueException When the factory returns a wrong type.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			if ( ! isset( $this->factories[ $id ] ) ) {
				throw new \OutOfBoundsException( 'Service not registered: ' . esc_html( $id ) );
			}
			$service = ( $this->factories[ $id ] )( $this );
			if ( ! $service instanceof $id ) {
				throw new \UnexpectedValueException( 'Factory returned an invalid service for: ' . esc_html( $id ) );
			}
			$this->instances[ $id ] = $service;
		}

		/**
		 * Resolved service, typed for static analysis.
		 *
		 * @var T $instance
		 */
		$instance = $this->instances[ $id ];
		return $instance;
	}
}
