<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_plain_text_returns_text_without_markup(): void
    {
        $this->assertSame('Hello world', (new RichText)->plainText('<p>Hello <strong>world</strong></p>'));
        $this->assertSame('Line one.', (new RichText)->plainText('<p>Line one.</p>'));
    }

    public function test_plain_text_empty_for_empty_content(): void
    {
        $this->assertSame('', (new RichText)->plainText('<p></p>'));
        $this->assertSame('', (new RichText)->plainText('<p>&nbsp;</p>'));
    }

    public function test_sanitize_strips_script_and_keeps_allowed_markup(): void
    {
        $clean = (new RichText)->sanitize('<p>Hello <script>alert(1)</script><strong>world</strong></p>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringContainsString('<p>Hello', $clean);
    }

    public function test_sanitize_strips_event_handlers_on_unallowed_elements(): void
    {
        $clean = (new RichText)->sanitize('<p>Hello <div onclick="alert(1)">World</div></p>');

        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function test_sanitize_drops_unsafe_link_schemes(): void
    {
        $clean = (new RichText)->sanitize('<p><a href="javascript:alert(1)">click</a></p>');

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_sanitize_keeps_allowed_heading_and_link(): void
    {
        $clean = (new RichText)->sanitize('<h1>Title</h1><p><a href="https://example.com">Docs</a></p>');

        $this->assertStringContainsString('<h1>Title</h1>', $clean);
        $this->assertStringContainsString('https://example.com', $clean);
    }
}
