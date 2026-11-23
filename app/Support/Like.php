<?php

namespace App\Support;

final class Like
{
    /**
     * Escape LIKE wildcards so user input is matched literally.
     */
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * Build a contains-style LIKE pattern with the term escaped.
     */
    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }
}
