<?php

namespace App\Support;

use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;

final class ApplicationEmailComposer
{
    public function compose(Entry $entry): array
    {
        $email = ApplicationData::group($entry, 'email');
        $recipient = ApplicationData::group($entry, 'recipient');
        $letter = ApplicationData::group($entry, 'letter');
        $signature = ApplicationData::group($entry, 'signature');
        $mode = in_array($email['mode'] ?? '', ['short', 'teaser', 'manual'], true) ? $email['mode'] : 'short';
        $subject = trim((string) ($letter['subject'] ?? ''));
        $subject = $subject === '' ? '' : 'Bewerbung: '.$subject;
        $salutation = trim((string) ($email['salutation'] ?? '')) ?: trim((string) ($letter['salutation'] ?? ''));
        $closing = trim((string) ($email['closing'] ?? '')) ?: trim((string) ($letter['closing'] ?? ''));
        $sender = trim((string) ($email['sender_name'] ?? '')) ?: trim((string) ($signature['name'] ?? ''));
        $body = match ($mode) {
            'manual' => trim((string) ($email['manual_body'] ?? '')),
            'teaser' => $this->joinParagraphs(trim((string) ($email['intro'] ?? '')), $this->firstParagraph(ApplicationData::coverLetter($entry))),
            default => trim((string) ($email['intro'] ?? '')) ?: $this->shortBody($recipient),
        };
        $attachments = $this->selectedAttachments($entry);
        $attachmentDescription = $attachments === [] ? '' : 'Als Anlage erhalten Sie: '.implode(', ', array_column($attachments, 'label')).'.';

        $parts = array_values(array_filter([$salutation, $body, $attachmentDescription, $closing, $sender], fn ($part) => trim($part) !== ''));
        $text = implode("\n\n", $parts);
        $html = '<!doctype html><html lang="de"><body style="margin:0;padding:24px;color:#20242a;background:#fff;font:16px/1.6 Arial,Helvetica,sans-serif"><div style="max-width:640px;margin:0 auto">'
            .implode('', array_map(fn ($part) => $this->htmlParagraphs($part), $parts))
            .'</div></body></html>';

        return [
            'recipient' => trim((string) ($recipient['email'] ?? '')),
            'subject' => $subject,
            'mode' => $mode,
            'text' => $text,
            'html' => $html,
            'attachments' => array_column($attachments, 'label'),
        ];
    }

    private function shortBody(array $recipient): string
    {
        $position = trim((string) ($recipient['position'] ?? ''));
        $organisation = trim((string) ($recipient['organisation'] ?? ''));
        if ($position !== '' && $organisation !== '') {
            return "anbei übersende ich Ihnen meine Bewerbungsunterlagen für {$position} bei {$organisation}.";
        }

        return '';
    }

    private function htmlParagraphs(string $text): string
    {
        return implode('', array_map(
            fn ($paragraph) => '<p style="margin:0 0 16px">'.nl2br(e($paragraph), false).'</p>',
            preg_split('/\R{2,}/u', trim($text)) ?: [],
        ));
    }

    private function firstParagraph(array $nodes): string
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'paragraph') {
                return $this->inlineText($node['content'] ?? []);
            }
        }

        return '';
    }

    private function inlineText(array $nodes): string
    {
        $result = '';
        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            if ($type === 'text') {
                $result .= (string) ($node['text'] ?? '');
            } elseif ($type === 'hardBreak') {
                $result .= "\n";
            } elseif ($type === 'paragraph') {
                // Keep inline-only extraction bounded to the selected paragraph.
                continue;
            }
        }

        return trim($result);
    }

    private function joinParagraphs(string $intro, string $teaser): string
    {
        return implode("\n\n", array_filter([$intro, trim($teaser)], fn ($part) => $part !== ''));
    }

    private function selectedAttachments(Entry $entry): array
    {
        $result = [];
        foreach (ApplicationData::documents($entry) as $document) {
            $file = $document['file'] ?? null;
            $file = is_array($file) ? reset($file) : $file;
            if (! is_string($file) || $file === '') {
                continue;
            }
            $asset = Asset::find(str_contains($file, '::') ? $file : 'application_documents::'.$file);
            if (! $asset || $asset->containerHandle() !== 'application_documents') {
                continue;
            }
            $label = trim((string) ($document['label'] ?? ''));
            if ($label !== '') {
                $result[] = ['label' => $label];
            }
        }

        return $result;
    }
}
