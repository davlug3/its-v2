<?php

namespace App\Support;

final class NotificationSanitizer
{
    public static function sanitize(string $text): string
    {
        // Defense-in-depth: redact credential-like patterns in legacy rows.
        $text = preg_replace('/(password|pword|passwd|pwd)\s*[:=]\s*[^\s&<"\']+/i', '$1=[redacted]', $text) ?? $text;
        $text = str_replace(['http://http://', 'https://https://'], ['http://', 'https://'], $text);

        return $text;
    }
}
