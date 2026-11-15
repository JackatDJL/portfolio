<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicationDocumentTest extends TestCase
{
    public function test_existing_publications_keep_their_pdf_preview_and_open_the_canonical_page_only_from_the_action(): void
    {
        $documents = [
            'breaking-free-from-big-tech' => [
                'file' => '/documents/breaking-free-from-big-tech.pdf',
                'doi' => '10.5281/zenodo.19793847',
                'title' => 'Breaking Free from Big Tech',
            ],
            'zinsen-und-finanzen' => [
                'file' => '/documents/zinsen-und-finanzen.pdf',
                'doi' => '10.5281/zenodo.20792995',
                'title' => 'Zinsen und Finanzen',
            ],
        ];

        foreach ($documents as $slug => $publication) {
            $document = $publication['file'];
            $path = public_path(ltrim($document, '/'));
            $this->assertFileExists($path);
            $this->assertSame('%PDF-', file_get_contents($path, false, null, 0, 5));

            $response = $this->get('/publikationen/'.$slug);
            $response->assertOk();
            $response->assertSee('data-pdf-url="'.$document.'"', false);
            $response->assertSee('<a class="btn btn-primary pdf-viewer__action" href="https://doi.org/'.$publication['doi'].'"', false);
            $response->assertSee('class="btn btn-primary pdf-viewer__action"', false);
            $response->assertSee('aria-label="Veröffentlichung anzeigen: '.$publication['title'].'"', false);
            $response->assertSee('>Veröffentlichung anzeigen</a>', false);
            $response->assertSeeInOrder([
                'data-pdf-pages',
                'data-pdf-loading',
                'class="pdf-viewer__spinner"',
                'data-pdf-loading-text',
            ], false);
            $response->assertDontSee('href="'.$document.'"', false);
            $response->assertDontSee('class="pdf-viewer__preview-link"', false);
        }
    }
}
