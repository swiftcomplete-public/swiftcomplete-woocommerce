<?php
/**
 * Hook Manager for centralized WordPress hook registration
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Core;

defined('ABSPATH') || exit;

/**
 * Manages WordPress hook registration
 */
class HookManager
{
    /**
     * Registered hooks
     *
     * @var array<string, array<int, array{callback: callable, priority: int, accepted_args: int}>>
     */
    private $hooks = array();

    /**
     * Register a WordPress hook
     *
     * @param string   $hook          Hook name
     * @param callable $callback      Callback function
     * @param int      $priority      Hook priority
     * @param int      $accepted_args Number of accepted arguments
     * @return bool
     */
    public function register_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $result = add_action($hook, $callback, $priority, $accepted_args);

        // Track registered hooks for potential unregistration
        if ($result) {
            if (!isset($this->hooks[$hook])) {
                $this->hooks[$hook] = array();
            }
            $this->hooks[$hook][] = array(
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $accepted_args,
            );
        }

        return $result;
    }

    /**
     * Register a WordPress filter
     *
     * @param string   $hook          Filter name
     * @param callable $callback      Callback function
     * @param int      $priority      Filter priority
     * @param int      $accepted_args Number of accepted arguments
     * @return bool
     */
    public function register_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $result = add_filter($hook, $callback, $priority, $accepted_args);

        // Track registered filters
        if ($result) {
            if (!isset($this->hooks[$hook])) {
                $this->hooks[$hook] = array();
            }
            $this->hooks[$hook][] = array(
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $accepted_args,
            );
        }

        return $result;
    }

    /**
     * Register multiple hooks at once
     *
     * @param array<string, array{callback: callable, priority?: int, accepted_args?: int, type?: 'action'|'filter'}> $hooks Array of hooks to register
     */
    public function register_many(array $hooks): void
    {
        foreach ($hooks as $hook => $config) {
            $callback = $config['callback'];
            $priority = $config['priority'] ?? 10;
            $accepted_args = $config['accepted_args'] ?? 1;
            $type = $config['type'] ?? 'action';

            if ('filter' === $type) {
                $this->register_filter($hook, $callback, $priority, $accepted_args);
            } else {
                $this->register_action($hook, $callback, $priority, $accepted_args);
            }
        }
    }

    /**
     * Unregister a hook
     *
     * @param string   $hook     Hook name
     * @param callable $callback Callback function
     * @param int      $priority Hook priority
     * @return bool
     */
    public function unregister(string $hook, callable $callback, int $priority = 10): bool
    {
        $result = remove_action($hook, $callback, $priority);

        // Remove from tracking
        if ($result && isset($this->hooks[$hook])) {
            $this->hooks[$hook] = array_filter(
                $this->hooks[$hook],
                function ($registered) use ($callback, $priority) {
                    return $registered['callback'] !== $callback || $registered['priority'] !== $priority;
                }
            );

            if (empty($this->hooks[$hook])) {
                unset($this->hooks[$hook]);
            }
        }

        return $result;
    }

    /**
     * Get all registered hooks
     *
     * @return array<string, array>
     */
    public function get_registered_hooks(): array
    {
        return $this->hooks;
    }
}
