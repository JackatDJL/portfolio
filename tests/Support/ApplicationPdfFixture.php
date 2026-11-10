<?php

namespace Tests\Support;

use App\Support\ApplicationData;
use App\Support\CvPdfRenderer;
use App\Support\CvPrivateData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Symfony\Component\Process\Process;

/** Synthetic layout data only. Never seeds an actual application or credential. */
final class ApplicationPdfFixture
{
    public string $root;

    public $entry;

    private array $original = [];

    public function __construct(?string $root = null)
    {
        $this->root = $root ?: sys_get_temp_dir().'/portfolio-pdf-test-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        config([
            'applications.cache_path' => $this->root.'/cache', 'applications.work_path' => $this->root.'/work',
            'cv.private_pdf_root' => $this->root.'/compiler',
            'filesystems.disks.application_documents.root' => $this->root.'/documents',
        ]);
        Storage::forgetDisk('application_documents');
        mkdir($this->root.'/documents', 0700);
        foreach (['site', 'cv'] as $handle) {
            $global = GlobalSet::find($handle)->inDefaultSite();
            $this->original[$handle] = $global->data()->all();
        }
        $site = GlobalSet::find('site')->inDefaultSite();
        $site->set('name', 'Erika Muster');
        $site->set('email', 'public@example.invalid');
        $site->set('profile_image', null);
        $site->set('social_links', [['label' => 'Matrix Layouttest', 'url' => 'https://matrix.to/#/#layout-test:example.invalid']]);
        $cv = GlobalSet::find('cv')->inDefaultSite();
        foreach (CvPrivateData::FIELDS as $field) {
            $cv->set($field, null);
        }
        foreach (['private_email' => 'private-export@example.invalid', 'phone' => '+49 000 123456', 'street' => 'Musterstraße', 'house_number' => '1', 'postal_code' => '12345', 'city' => 'Musterstadt', 'country' => 'Deutschland'] as $field => $value) {
            $cv->set($field, Crypt::encryptString($value));
        }
        $profile = Entry::query()->where('collection', 'cv_profiles')->where('published', true)->first();
        $this->entry = Entry::make()->id((string) Str::uuid())->collection('applications')->data([
            'title' => 'Synthetic PDF integration fixture', 'application_status' => 'draft',
            'recipient' => ApplicationData::encrypt([['organisation' => 'Beispiel GmbH · Layouttest', 'recipient' => 'Personalabteilung', 'address' => "Beispielweg 12\n54321 Beispielstadt"]]),
            'letter' => ApplicationData::encrypt([[
                'subject' => "Bewerbung um ein Praktikum\nReferenz Layouttest",
                'salutation' => 'Sehr geehrte Damen und Herren,',
                'body' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Dies ist ausschließlich ein synthetischer Layouttest. Der Text beschreibt keine echte Bewerbung und enthält keine erfundenen Qualifikationen.']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hier werden Lesbarkeit, Abstände und funktionierende Verweise geprüft. Weitere Informationen finden Sie unter '], ['type' => 'text', 'text' => 'Beispiel-Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org/application?one=1&two=2#details']]]], ['type' => 'text', 'text' => '.']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Die beigefügten Unterlagen dienen ausschließlich der technischen Prüfung der PDF-Zusammenstellung.']]],
                ],
                'closing' => 'Mit freundlichen Grüßen',
            ]]),
            'cv' => ApplicationData::encrypt([['profile' => $profile ? [$profile->id()] : [], 'introduction' => 'Synthetischer Layouttest. Keine echte Bewerbung.', 'accent_color' => '#235c4c']]),
            'documents' => ApplicationData::encrypt([['mode' => 'override', 'items' => []]]),
            'signature' => ApplicationData::encrypt([['mode' => 'typed', 'name' => 'Erika Muster · Layouttest']]),
            'tracking' => ApplicationData::encrypt([['application_date' => '2026-11-06']]),
        ]);
    }

    public function documents(): void
    {
        $items = [];
        foreach (['Erste Anlage', 'Zweite Anlage'] as $index => $label) {
            $source = '\\documentclass[a4paper]{article}\\usepackage{hyperref}\\begin{document}'.$label.'\\par\\href{https://example.org/document-'.$index.'}{External link}\\par\\hyperlink{shared-destination}{Internal link}\\newpage\\hypertarget{shared-destination}{Target '.$index.'}\\end{document}';
            $pdf = app(CvPdfRenderer::class)->compile($source);
            $bookmarked = $this->root.'/bookmarked-'.$index.'.pdf';
            $bookmark = new Process([
                config('applications.python'), base_path('tests/Support/add_named_outline.py'),
                $pdf, $bookmarked, 'Attachment bookmark '.$index, (string) $index,
            ]);
            $bookmark->mustRun();
            @unlink($pdf);
            $pdf = $bookmarked;
            $name = 'document-'.$index.'.pdf';
            rename($pdf, $this->root.'/documents/'.$name);
            $items[] = ['label' => $label, 'file' => [$name]];
        }
        $this->entry->set('documents', ApplicationData::encrypt([['mode' => 'override', 'items' => $items]]));
        // Links are authored in Bard; the reference-style letter adds no automatic Anlagen block.
        $letter = ApplicationData::group($this->entry, 'letter');
        $links = [['type' => 'text', 'text' => 'Lebenslauf', 'marks' => [['type' => 'link', 'attrs' => ['href' => '#cv']]]]];
        foreach ($items as $index => $item) {
            $links[] = ['type' => 'text', 'text' => ', '];
            $links[] = ['type' => 'text', 'text' => $item['label'], 'marks' => [['type' => 'link', 'attrs' => ['href' => '#document-'.($index + 1)]]]];
        }
        $letter['body'][2]['content'] = [
            ['type' => 'text', 'text' => 'Die beigefügten Unterlagen dienen ausschließlich der technischen Prüfung: '],
            ...$links, ['type' => 'text', 'text' => '.'],
        ];
        $this->entry->set('letter', ApplicationData::encrypt([$letter]));
    }

    public function restore(): void
    {
        foreach ($this->original as $handle => $values) {
            GlobalSet::find($handle)->inDefaultSite()->data($values);
        }
        Storage::forgetDisk('application_documents');
    }

    public function cleanup(): void
    {
        $this->restore();
        $remove = function ($path) use (&$remove) {
            if (is_dir($path) && ! is_link($path)) {
                foreach (scandir($path) as $name) {
                    if ($name !== '.' && $name !== '..') {
                        $remove($path.'/'.$name);
                    }
                }
                rmdir($path);
            } else {
                @unlink($path);
            }
        };
        $remove($this->root);
    }
}
