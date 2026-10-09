<?php

namespace App\Services\ApkMedia;

/** The file to store for a banner upload, and whether it is ours to delete afterwards. */
final class NormalizedMedia
{
    public function __construct(
        public readonly string $path,
        public readonly string $ext,
        public readonly bool $temporary,
    ) {}

    public function cleanup(): void
    {
        if ($this->temporary && is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
