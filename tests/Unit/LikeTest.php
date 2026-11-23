<?php

namespace Tests\Unit;

use App\Support\Like;
use PHPUnit\Framework\TestCase;

class LikeTest extends TestCase
{
    public function test_escapes_wildcards(): void
    {
        $this->assertSame('100\\%', Like::escape('100%'));
        $this->assertSame('a\\_b', Like::escape('a_b'));
        $this->assertSame('a\\\\b', Like::escape('a\\b'));
    }

    public function test_contains_wraps_with_wildcards(): void
    {
        $this->assertSame('%50\\%%', Like::contains('50%'));
        $this->assertSame('%plain%', Like::contains('plain'));
    }

    public function test_leaves_safe_text_unchanged(): void
    {
        $this->assertSame('hello world', Like::escape('hello world'));
    }
}
