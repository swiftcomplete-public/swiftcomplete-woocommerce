<?php
/**
 * Service Container for dependency injection
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Core;

defined('ABSPATH') || exit;

/**
 * Simple service container for managing dependencies
 */
class ServiceContainer
{
    /**
     * Container instance
     *
     * @var ServiceContainer
     */
    private static $instance = null;

    /**
     * Registered services
     *
     * @var array<string, callable|object>
     */
    private $services = array();

    /**
     * Resolved service instances
     *
     * @var array<string, object>
     */
    private $resolved = array();

    /**
     * Get container instance
     *
     * @return ServiceContainer
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a service
     *
     * @param string   $id      Service identifier
     * @param callable $factory Factory function that returns the service instance
     */
    public function register(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    /**
     * Register a singleton service
     *
     * @param string   $id      Service identifier
     * @param callable $factory Factory function that returns the service instance
     */
    public function register_singleton(string $id, callable $factory): void
    {
        $this->register($id, function () use ($id, $factory) {
            if (!isset($this->resolved[$id])) {
                $this->resolved[$id] = $factory($this);
            }
            return $this->resolved[$id];
        });
    }

    /**
     * Get a service instance
     *
     * @param string $id Service identifier
     * @return object
     * @throws RuntimeException If service is not registered
     */
    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new RuntimeException("Service '{$id}' is not registered");
        }

        $factory = $this->services[$id];

        if (is_callable($factory)) {
            return $factory($this);
        }

        return $factory;
    }

    /**
     * Check if a service is registered
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Reset the container (useful for testing)
     */
    public function reset(): void
    {
        $this->services = array();
        $this->resolved = array();
    }
}
