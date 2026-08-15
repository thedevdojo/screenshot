<?php

namespace Tests\Feature;

use App\Http\Controllers\ScreenshotController;
use Tests\TestCase;

/**
 * The snap-from-html endpoint serders two kinds of payloads: bare fragments,
 * which get wrapped with the Inter font stack and a Tailwind CDN so they are
 * presentable, and complete documents, which must render EXACTLY as posted.
 * Injecting the wrapper into a complete document corrupts it — the Tailwind
 * v3 CDN's universal --tw-* variable reset breaks Tailwind v4 gradients in
 * the posted document's own stylesheet.
 */
class SnapFromHtmlPreparationTest extends TestCase
{
    private function controller(): object
    {
        return new class extends ScreenshotController
        {
            public function detect(string $content): bool
            {
                return $this->isCompleteDocument($content);
            }

            public function prepare(string $content, string $cdn): string
            {
                return $this->prepareHtml($content, $cdn);
            }
        };
    }

    public function test_complete_documents_are_detected(): void
    {
        $c = $this->controller();

        $this->assertTrue($c->detect('<!doctype html><html><body>x</body></html>'));
        $this->assertTrue($c->detect('<!DOCTYPE HTML><html>x</html>'));
        $this->assertTrue($c->detect("\n  <html lang=\"en\"><head></head></html>"));
        $this->assertTrue($c->detect('<HTML><body>x</body></HTML>'));
    }

    public function test_fragments_are_not_detected_as_documents(): void
    {
        $c = $this->controller();

        $this->assertFalse($c->detect('<div class="p-4">hello</div>'));
        $this->assertFalse($c->detect('<p>html is great</p>'));
        $this->assertFalse($c->detect('plain text'));
        $this->assertFalse($c->detect('<header><h1>hi</h1></header>'));
    }

    public function test_fragments_are_wrapped_with_font_and_cdn(): void
    {
        $wrapped = $this->controller()->prepare('<div>hi</div>', '<script src="cdn"></script>');

        $this->assertStringContainsString('<script src="cdn"></script>', $wrapped);
        $this->assertStringContainsString('fonts.googleapis.com', $wrapped);
        $this->assertStringContainsString('<div>hi</div>', $wrapped);
        $this->assertStringStartsWith('<html>', $wrapped);
    }

    public function test_complete_documents_pass_through_untouched(): void
    {
        $doc = "<!doctype html>\n<html><head><style>.x{color:red}</style></head><body>x</body></html>";

        $this->assertSame($doc, $this->controller()->prepare($doc, '<script src="cdn"></script>'));
    }
}
