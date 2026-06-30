<?php

namespace App\Services\Refund\BankTemplates;

use InvalidArgumentException;

/**
 * Registry of available bank bulk-transfer templates. Register new banks here.
 */
class BankTemplateRegistry
{
    /** key => generator class */
    protected static array $map = [
        'cimb' => CimbBizChannelTemplate::class,
    ];

    public static function make(string $key): BankBulkTemplate
    {
        $key = strtolower($key);
        if (!isset(static::$map[$key])) {
            throw new InvalidArgumentException("Unknown bank template: {$key}");
        }

        return new static::$map[$key]();
    }

    public static function has(string $key): bool
    {
        return isset(static::$map[strtolower($key)]);
    }

    /** @return array<string,string> key => label, for the UI dropdown */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(static::$map) as $key) {
            $out[$key] = static::make($key)->label();
        }

        return $out;
    }
}
