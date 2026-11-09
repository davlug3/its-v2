<?php

namespace Tests\Unit;

use App\Support\NotificationSanitizer;
use PHPUnit\Framework\TestCase;

class NotificationSanitizerTest extends TestCase
{
    public function test_redacts_password_like_patterns(): void
    {
        $input = 'Your password: secret123 and pword=abc';
        $out = NotificationSanitizer::sanitize($input);

        $this->assertStringContainsString('password=[redacted]', $out);
        $this->assertStringContainsString('pword=[redacted]', $out);
        $this->assertStringNotContainsString('secret123', $out);
        $this->assertStringNotContainsString('abc', $out);
    }

    public function test_fixes_malformed_double_urls(): void
    {
        $input = 'Visit http://http://example.com and https://https://example.com/x';
        $out = NotificationSanitizer::sanitize($input);

        $this->assertSame('Visit http://example.com and https://example.com/x', $out);
    }

    public function test_leaves_normal_text_unchanged(): void
    {
        $input = 'Ticket 123 was created.';
        $this->assertSame($input, NotificationSanitizer::sanitize($input));
    }
}
