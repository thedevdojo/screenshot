<?php

namespace DevDojo\ScreenshotClient;

use Illuminate\Support\Facades\Storage;
use Stringable;

/**
 * The result of saving a screenshot. Carries the disk + path and can produce
 * a public URL. Stringifies to the path, so it still behaves like the old
 * string return value in string contexts.
 */
class StoredScreenshot implements Stringable
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {}

    /** The disk-relative path, e.g. "screenshots/example.png". */
    public function path(): string
    {
        return $this->path;
    }

    /** The disk the screenshot was stored on. */
    public function disk(): string
    {
        return $this->disk;
    }

    /** The full URL to the stored screenshot (requires a publicly-served disk). */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
