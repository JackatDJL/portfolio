<?php

namespace Tests\Unit;

use App\Tags\Attribution;
use PHPUnit\Framework\TestCase;

class AttributionTest extends TestCase
{
    public function test_it_preserves_credit_and_links_an_explicit_source(): void
    {
        $this->assertSame('Foto: A · CC BY-SA 3.0, <a href="https://example.org/photo?id=1&amp;lang=de">Quelle</a>.', Attribution::linkedText('Foto: A · CC BY-SA 3.0, https://example.org/photo?id=1&lang=de.'));
    }

    public function test_it_escapes_markup_and_does_not_link_other_schemes(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt; javascript:alert(1)', Attribution::linkedText('<script>alert(1)</script> javascript:alert(1)'));
    }
}
