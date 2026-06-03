<?php

namespace Tests\Feature;

use DevDojo\ScreenshotClient\Exceptions\ScreenshotException;
use DevDojo\ScreenshotClient\Facades\Screenshot;
use DevDojo\ScreenshotClient\PendingScreenshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScreenshotClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'screenshot.url' => 'https://screenshot.test',
            'screenshot.api_key' => 'secret-key',
            'screenshot.disk' => 'local',
            'screenshot.timeout' => 30,
        ]);

        Storage::fake('local');
    }

    public function test_helper_and_facade_return_a_builder(): void
    {
        $this->assertInstanceOf(PendingScreenshot::class, screenshot());
        $this->assertInstanceOf(PendingScreenshot::class, screenshot('https://google.com'));
        $this->assertInstanceOf(PendingScreenshot::class, Screenshot::html('<p>hi</p>'));
    }

    public function test_html_save_posts_payload_stores_file_and_returns_path(): void
    {
        Http::fake([
            'https://screenshot.test/api/snap-from-html' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png']),
        ]);

        $path = screenshot()
            ->html('<p class="bg-green-500 p-10">Example Here</p>')
            ->dimensions(800, 400)
            ->tailwind(4)
            ->save('screenshots/example.png');

        $this->assertSame('screenshots/example.png', $path);
        Storage::disk('local')->assertExists('screenshots/example.png');
        $this->assertSame('PNGBYTES', Storage::disk('local')->get('screenshots/example.png'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://screenshot.test/api/snap-from-html'
                && $request['html'] === '<p class="bg-green-500 p-10">Example Here</p>'
                && $request['width'] === 800
                && $request['height'] === 400
                && $request['tailwind_version'] === 4
                && $request->hasHeader('Authorization', 'Bearer secret-key');
        });
    }

    public function test_url_shortcut_hits_the_url_endpoint(): void
    {
        Http::fake(['*' => Http::response('PNG', 200)]);

        screenshot('https://google.com')->save();

        Http::assertSent(fn ($request) => $request->url() === 'https://screenshot.test/api/snap-from-url'
            && $request['url'] === 'https://google.com');
    }

    public function test_save_auto_generates_a_path_when_none_given(): void
    {
        Http::fake(['*' => Http::response('PNG', 200)]);

        $path = screenshot()->html('<p>hi</p>')->save();

        $this->assertMatchesRegularExpression('#^screenshots/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_base64_returns_encoded_bytes_and_data_uri(): void
    {
        Http::fake(['*' => Http::response('PNG', 200)]);

        $this->assertSame(base64_encode('PNG'), screenshot()->html('<p>x</p>')->base64());
        $this->assertSame('data:image/png;base64,'.base64_encode('PNG'), screenshot()->html('<p>x</p>')->base64(true));
    }

    public function test_failure_throws_screenshot_exception_with_message(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid or missing API key.'], 401)]);

        $this->expectException(ScreenshotException::class);
        $this->expectExceptionMessage('401');

        screenshot()->html('<p>x</p>')->save();
    }

    public function test_missing_source_throws_before_any_request(): void
    {
        Http::fake();

        $this->expectException(ScreenshotException::class);

        screenshot()->save();
    }
}
