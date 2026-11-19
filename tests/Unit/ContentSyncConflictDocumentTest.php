<?php

namespace Tests\Unit;

use App\Support\ContentSyncConflictDocument;
use App\Support\ContentSyncFailure;
use Tests\TestCase;

class ContentSyncConflictDocumentTest extends TestCase
{
    public function test_it_reconciles_each_conflict_section_independently(): void
    {
        $content = "before\n<<<<<<< HEAD\nlocal title\n=======\nincoming title\n>>>>>>> main\nkeep this\n<<<<<<< HEAD\nlocal body\n=======\nincoming body\n>>>>>>> main\nafter\n";
        $document = ContentSyncConflictDocument::inspect($content);

        $this->assertFalse($document['binary']);
        $this->assertCount(2, $document['sections']);
        $resolved = ContentSyncConflictDocument::compose($content, $document['sections'], [
            ['id' => 0, 'choice' => 'ours'],
            ['id' => 1, 'choice' => 'manual', 'text' => "manually reconciled body\n"],
        ]);

        $this->assertSame("before\nlocal title\nkeep this\nmanually reconciled body\nafter\n", $resolved);
        $this->assertFalse(ContentSyncConflictDocument::containsConflictMarker($resolved));
    }

    public function test_it_uses_a_configured_wider_conflict_marker_size(): void
    {
        $content = "before\n<<<<<<<< HEAD\nlocal title\n========\nincoming title\n>>>>>>>> main\nafter\n";
        $document = ContentSyncConflictDocument::inspect($content, 8);

        $this->assertCount(1, $document['sections']);
        $resolved = ContentSyncConflictDocument::compose($content, $document['sections'], [
            ['id' => 0, 'choice' => 'theirs'],
        ], 8);

        $this->assertSame("before\nincoming title\nafter\n", $resolved);
        $this->assertTrue(ContentSyncConflictDocument::containsConflictMarker("<<<<<<<< unresolved\n"));
    }

    public function test_private_cv_values_are_redacted_and_cannot_be_manually_submitted(): void
    {
        $secret = 'PRIVATE-CV-EMAIL-SENTINEL@example.invalid';
        $content = "<<<<<<< HEAD\nprivate_email: {$secret}\n=======\nprivate_email: another-private-value\n>>>>>>> main\n";
        $document = ContentSyncConflictDocument::inspect($content);

        $this->assertTrue($document['sections'][0]['protected']);
        $this->assertStringNotContainsString($secret, ContentSyncConflictDocument::displaySection($document['sections'][0]['ours'], true));
        $this->expectException(ContentSyncFailure::class);
        ContentSyncConflictDocument::compose($content, $document['sections'], [
            ['id' => 0, 'choice' => 'manual', 'text' => 'private_email: user supplied'],
        ]);
    }

    public function test_quoted_private_field_keys_are_also_redacted(): void
    {
        $secret = 'PRIVATE-QUOTED-CV-SENTINEL';
        $content = "<<<<<<< HEAD\n\"private_email\": {$secret}\n=======\n\"private_email\": incoming-private-value\n>>>>>>> main\n";
        $document = ContentSyncConflictDocument::inspect($content);

        $this->assertTrue($document['sections'][0]['protected']);
        $this->assertSame('[Geschützte CV-Werte werden nicht angezeigt.]', ContentSyncConflictDocument::displaySection($document['sections'][0]['ours'], true));
        $this->assertStringNotContainsString($secret, ContentSyncConflictDocument::displaySection($document['sections'][0]['ours'], true));
    }

    public function test_it_rejects_missing_decisions_and_manual_conflict_markers(): void
    {
        $content = "<<<<<<< HEAD\nlocal\n=======\nincoming\n>>>>>>> main\n";
        $document = ContentSyncConflictDocument::inspect($content);

        try {
            ContentSyncConflictDocument::compose($content, $document['sections'], []);
            $this->fail('A missing section decision must not be accepted.');
        } catch (ContentSyncFailure $failure) {
            $this->assertStringContainsString('every conflict section', $failure->getMessage());
        }

        $this->expectException(ContentSyncFailure::class);
        ContentSyncConflictDocument::compose($content, $document['sections'], [
            ['id' => 0, 'choice' => 'manual', 'text' => "<<<<<<< still unresolved\n"],
        ]);
    }

    public function test_it_caps_the_total_resolved_file_size(): void
    {
        $content = "<<<<<<< HEAD\nlocal one\n=======\nincoming one\n>>>>>>> main\n<<<<<<< HEAD\nlocal two\n=======\nincoming two\n>>>>>>> main\n";
        $document = ContentSyncConflictDocument::inspect($content);

        $this->expectException(ContentSyncFailure::class);
        ContentSyncConflictDocument::compose($content, $document['sections'], [
            ['id' => 0, 'choice' => 'manual', 'text' => str_repeat('a', 140_000)],
            ['id' => 1, 'choice' => 'manual', 'text' => str_repeat('b', 140_000)],
        ]);
    }
}
