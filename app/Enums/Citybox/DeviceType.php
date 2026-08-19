<?php

namespace App\Enums\Citybox;

/**
 * CityBox hardware type (`box_list.type`) → mark1 model name + layer count.
 * Both SG models confirmed FIVE layers in their portal (2026-08-19):
 * visual-2 = model F5, visual-8 = model C5. Unknown types fall back to a
 * generic 5-layer entry and log — never block provisioning on a new type.
 */
enum DeviceType: string
{
    case Visual2 = 'visual-2';
    case Visual8 = 'visual-8';
    case Unknown = 'unknown';

    public static function fromApi(mixed $raw): self
    {
        return is_string($raw) ? (self::tryFrom($raw) ?? self::Unknown) : self::Unknown;
    }

    public function layerCount(): int
    {
        return 5;
    }

    public function modelName(): string
    {
        return match ($this) {
            self::Visual2 => 'CityBox F5 (visual-2)',
            self::Visual8 => 'CityBox C5 (visual-8)',
            self::Unknown => 'CityBox (unknown type)',
        };
    }
}
