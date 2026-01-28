<?php
/**
 * Asset Enqueuer Interface
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface for asset enqueuing
 */
interface AssetEnqueuerInterface
{
    /**
     * Enqueue scripts and styles for checkout
     *
     * @return void
     */
    public function enqueue_scripts(): void;
}
