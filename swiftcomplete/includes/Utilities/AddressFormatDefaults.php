<?php
/**
 * Address format defaults, allowed tokens, and sanitisation.
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Utilities;

defined('ABSPATH') || exit;

/**
 * Single source of truth for the per-field Swiftcomplete populateLineFormat tokens.
 */
class AddressFormatDefaults
{
    /**
     * Default format string per configurable field key.
     *
     * @var array<string,string>
     */
    public const DEFAULTS = array(
        'address_1' => 'BuildingName, BuildingNumber SecondaryRoad, Road',
        'address_2' => 'SubBuilding',
        'company' => 'Company',
        'city' => 'TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY',
    );

    /**
     * Canonical (mixed-case) tokens allowed in a field format.
     * An ALL-CAPS variant of any token forces uppercase output.
     *
     * @var string[]
     */
    public const ALLOWED_TOKENS = array(
        'BuildingName',
        'BuildingNumber',
        'SubBuilding',
        'Company',
        'SecondaryRoad',
        'Road',
        'TertiaryLocality',
        'SecondaryLocality',
        'PrimaryLocality',
    );

    /**
     * Configurable field key => human label (used for settings rows).
     *
     * @var array<string,string>
     */
    public const FIELDS = array(
        'company' => 'Company',
        'address_1' => 'Address line 1',
        'address_2' => 'Address line 2',
        'city' => 'City',
    );

    /**
     * Sanitise one format string: keep only allowed tokens (case-insensitive),
     * preserve ALL-CAPS (uppercase intent), and normalise separators to
     * ", " (comma) or " " (space). Unknown tokens are dropped.
     *
     * @param string $raw Raw format string.
     * @return string Sanitised format string ('' if nothing valid remains).
     */
    public static function sanitize_format(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $lookup = array();
        foreach (self::ALLOWED_TOKENS as $token) {
            $lookup[strtolower($token)] = $token;
        }

        $parts = preg_split(
            '/(\s*,\s*|\s+)/',
            $raw,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if (!is_array($parts)) {
            return '';
        }

        $out = '';
        $pending_sep = '';
        foreach ($parts as $part) {
            if (preg_match('/^\s*,\s*$/', $part)) {
                $pending_sep = ', ';
                continue;
            }
            if (preg_match('/^\s+$/', $part)) {
                $pending_sep = ' ';
                continue;
            }

            $key = strtolower($part);
            if (!isset($lookup[$key])) {
                $pending_sep = '';
                continue;
            }

            $canonical = $lookup[$key];
            $value = ($part === strtoupper($part)) ? strtoupper($canonical) : $canonical;

            if ($out !== '') {
                $out .= ($pending_sep !== '') ? $pending_sep : ', ';
            }
            $out .= $value;
            $pending_sep = '';
        }

        return $out;
    }

    /**
     * Resolve a stored field_formats map into a full, sanitised map:
     * sanitised stored value per key, or the default when empty/missing.
     *
     * @param mixed $stored Stored field_formats (any type; non-arrays treated as empty).
     * @return array<string,string>
     */
    public static function resolve($stored): array
    {
        if (!is_array($stored)) {
            $stored = array();
        }

        $resolved = array();
        foreach (self::DEFAULTS as $key => $default) {
            $value = (isset($stored[$key]) && is_string($stored[$key]))
                ? self::sanitize_format($stored[$key])
                : '';
            $resolved[$key] = ($value !== '') ? $value : $default;
        }

        return $resolved;
    }
}
