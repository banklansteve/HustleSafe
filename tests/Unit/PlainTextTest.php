<?php

namespace Tests\Unit;

use App\Support\PlainText;
use Tests\TestCase;

class PlainTextTest extends TestCase
{
    public function test_strips_html_and_inline_styles(): void
    {
        $input = '<p style="color:red;font-size:18px">Hello <strong>world</strong></p>';

        $this->assertSame('Hello world', PlainText::from($input));
    }

    public function test_limits_length(): void
    {
        $input = str_repeat('word ', 50);

        $this->assertLessThanOrEqual(24, strlen(PlainText::from($input, 20)));
    }
}
