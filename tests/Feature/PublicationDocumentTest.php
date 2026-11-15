<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicationDocumentTest extends TestCase
{
    public function test_publication_cover_links_to_its_canonical_landing_page(): void
    {
        $this->get('/publikationen/breaking-free-from-big-tech')
            ->assertOk()
            ->assertSee('class="publication-preview__link"', false)
            ->assertSee('href="https://doi.org/10.5281/zenodo.19793847"', false)
            ->assertSee('aria-label="Veröffentlichung öffnen: Breaking Free from Big Tech"', false)
            ->assertSee('class="publication-preview__action">Veröffentlichung öffnen</span>', false)
            ->assertSee('alt="Erste Seite von Breaking Free from Big Tech"', false)
            ->assertSee('src="/img/asset/', false);
    }

    public function test_existing_publications_use_their_actual_published_pdf_files_for_preview_and_direct_opening(): void
    {
        $documents = [
            'breaking-free-from-big-tech' => '/documents/breaking-free-from-big-tech.pdf',
            'zinsen-und-finanzen' => '/documents/zinsen-und-finanzen.pdf',
        ];

        foreach ($documents as $slug => $document) {
            $path = public_path(ltrim($document, '/'));
            $this->assertFileExists($path);
            $this->assertSame('%PDF-', file_get_contents($path, false, null, 0, 5));

            $response = $this->get('/publikationen/'.$slug);
            $response->assertOk();
            $response->assertSee('data-pdf-url="'.$document.'"', false);
            $response->assertSee('href="'.$document.'"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'href="'.$document.'"'));
            $response->assertDontSee('PDF direkt öffnen');
            $response->assertSee('class="pdf-viewer__preview-link"', false);
        }
    }
}
