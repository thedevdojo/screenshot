<?php

namespace DevDojo\ScreenshotClient;

use DevDojo\ScreenshotClient\Exceptions\ScreenshotException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A fluent, single-use builder for one screenshot request.
 *
 * Accumulates the source (url/html) and options, then a terminal method
 * (save/bytes/base64/response) performs the HTTP request to the service.
 */
class PendingScreenshot
{
    /** 'url' or 'html' */
    protected ?string $source = null;

    protected ?string $sourceValue = null;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?int $tailwind = null;

    protected ?string $disk = null;

    /**
     * A URL passed here (e.g. via screenshot('https://…')) becomes the source.
     * Use html() for HTML content.
     */
    public function __construct(?string $url = null)
    {
        if ($url !== null) {
            $this->source = 'url';
            $this->sourceValue = $url;
        }
    }

    /*
    | Source -------------------------------------------------------------- */

    public function html(string $html): static
    {
        $this->source = 'html';
        $this->sourceValue = $html;

        return $this;
    }

    /*
    | Options ------------------------------------------------------------- */

    public function width(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function dimensions(int $width, int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function tailwind(int $version): static
    {
        $this->tailwind = $version;

        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /*
    | Terminals ----------------------------------------------------------- */

    /**
     * Capture and store the screenshot, returning a StoredScreenshot
     * (exposes ->path() and ->url(); stringifies to the path). Auto-generates
     * a path under screenshots/ when none is given.
     */
    public function save(?string $path = null): StoredScreenshot
    {
        $path ??= 'screenshots/'.Str::uuid().'.png';
        $disk = $this->disk ?: $this->defaultDisk();

        Storage::disk($disk)->put($path, $this->bytes());

        return new StoredScreenshot($disk, $path);
    }

    /** Capture and return the raw PNG bytes. */
    public function bytes(): string
    {
        return $this->request()->body();
    }

    /** Capture and return a base64 string, or a data: URI when $dataUri is true. */
    public function base64(bool $dataUri = false): string
    {
        $encoded = base64_encode($this->bytes());

        return $dataUri ? 'data:image/png;base64,'.$encoded : $encoded;
    }

    /** Capture and return an inline image HTTP response. */
    public function response(): Response
    {
        return response($this->bytes(), 200, ['Content-Type' => 'image/png']);
    }

    /*
    | Internals ----------------------------------------------------------- */

    protected function request(): ClientResponse
    {
        if ($this->source === null) {
            throw new ScreenshotException('No screenshot source set. Call url() or html() first.');
        }

        $payload = [$this->source => $this->sourceValue];

        if ($this->width !== null) {
            $payload['width'] = $this->width;
        }
        if ($this->height !== null) {
            $payload['height'] = $this->height;
        }
        if ($this->tailwind !== null) {
            $payload['tailwind_version'] = $this->tailwind;
        }

        $endpoint = rtrim((string) $this->config('url'), '/').'/api/snap-from-'.$this->source;

        $request = Http::timeout((int) $this->config('timeout', 120))->acceptJson();

        if ($key = $this->config('api_key')) {
            $request = $request->withToken((string) $key);
        }

        $response = $request->post($endpoint, $payload);

        if ($response->failed()) {
            $message = $response->json('message') ?: $response->body();

            throw new ScreenshotException("Screenshot request failed [{$response->status()}]: {$message}");
        }

        return $response;
    }

    protected function defaultDisk(): string
    {
        return $this->config('disk') ?: config('filesystems.default');
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('screenshot.'.$key, $default);
    }
}
