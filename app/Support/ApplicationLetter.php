<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\GlobalSet;

final class ApplicationLetter
{
    private int $documentCount = 0;

    public function make(Entry $entry, array $cv, array $documents): array
    {
        $recipient = ApplicationData::group($entry, 'recipient');
        $letter = ApplicationData::group($entry, 'letter');
        $signature = ApplicationData::group($entry, 'signature');
        $body = ApplicationData::coverLetter($entry);
        $this->documentCount = count($documents);
        $bodyText = [];
        array_walk_recursive($body, function ($value, $key) use (&$bodyText) {
            if ($key === 'text') {
                $bodyText[] = (string) $value;
            }
        });
        $required = [
            'recipient.organisation' => $recipient['organisation'] ?? '',
            'recipient.address' => $recipient['address'] ?? '',
            'letter.subject' => $letter['subject'] ?? '',
            'letter.salutation' => $letter['salutation'] ?? '',
            'letter.body' => trim(implode(' ', $bodyText)),
            'letter.closing' => $letter['closing'] ?? '',
            'sender.name' => $cv['name'] ?? '',
            'sender.address' => $cv['contact']['address'] ?? '',
        ];
        $labels = ['recipient.organisation' => 'Organisation', 'recipient.address' => 'Empfängeranschrift', 'letter.subject' => 'Betreff', 'letter.salutation' => 'Anrede', 'letter.body' => 'Anschreiben', 'letter.closing' => 'Grußformel', 'sender.name' => 'Absendername im Globalset', 'sender.address' => 'Absenderanschrift im CV-Globalset'];
        foreach ($required as $field => $value) {
            if (empty($value) || (is_string($value) && trim($value) === '')) {
                throw ValidationException::withMessages([$field => 'Für den PDF-Export bitte ergänzen: '.$labels[$field].'.']);
            }
        }

        $private = CvPrivateData::reveal(GlobalSet::find('cv')->inDefaultSite(), strict: true);
        $country = trim($private['country'] ?? '');
        $senderLines = array_values(array_filter([
            trim(($private['street'] ?? '').' '.($private['house_number'] ?? '')),
            trim(($private['postal_code'] ?? '').' '.($private['city'] ?? '')),
            in_array(mb_strtolower($country), ['', 'de', 'deutschland', 'germany'], true) ? '' : $country,
        ]));
        $date = Carbon::parse(ApplicationData::date($entry))->locale('de');
        $place = trim($private['city'] ?? '');

        return [
            'recipient' => array_values(array_filter([
                $recipient['organisation'], $recipient['recipient'] ?? '',
                ...preg_split('/\R/u', $recipient['address']),
            ])),
            'sender' => $cv['name'],
            'sender_address_lines' => $senderLines ?: preg_split('/,\s*/u', $cv['contact']['address']),
            'date_line' => ($place !== '' ? $place.', den ' : '').$date->translatedFormat('j. F Y'),
            'address' => $cv['contact']['address'],
            'return_address' => $cv['sender_return_address'],
            'email' => $cv['contact']['email'] ?: $cv['contact']['public_email'],
            'phone' => $cv['contact']['phone'],
            'accent' => Latex::accessibleAccent($cv['accent']),
            'date' => Carbon::parse(ApplicationData::date($entry))->format('d.m.Y'),
            'subject' => $letter['subject'], 'salutation' => $letter['salutation'],
            'body' => $this->nodes($body), 'closing' => $letter['closing'],
            'signature' => ($signature['mode'] ?? 'none') === 'typed' ? ($signature['name'] ?? '') : '',
            'documents' => array_column($documents, 'label'),
        ];
    }

    /** A deliberately small Bard-to-LaTeX vocabulary; user content is always escaped. */
    public function nodes(array $nodes): string
    {
        $output = '';
        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            $children = $this->nodes($node['content'] ?? []);
            $output .= match ($type) {
                'text' => $this->text($node),
                'paragraph' => $children."\n\\par\n",
                'hardBreak' => '\\newline{}',
                'bulletList' => "\\begin{itemize}\n".$children."\\end{itemize}\n",
                'orderedList' => "\\begin{enumerate}\n".$children."\\end{enumerate}\n",
                'listItem' => '\\item '.$children,
                default => throw ValidationException::withMessages(['letter.body' => 'Nicht unterstützter Textbaustein im Anschreiben.']),
            };
        }

        return $output;
    }

    private function text(array $node): string
    {
        $text = Latex::escape((string) ($node['text'] ?? ''));
        foreach ($node['marks'] ?? [] as $mark) {
            $text = match ($mark['type'] ?? '') {
                'bold' => '\\textbf{'.$text.'}',
                'italic' => '\\emph{'.$text.'}',
                'link' => $this->link($text, $mark['attrs']['href'] ?? ''),
                default => $text,
            };
        }

        return $text;
    }

    private function link(string $text, string $href): string
    {
        if ($href === '#cv') {
            return '\\hyperlink{application-cv}{'.$text.'}';
        }
        if (preg_match('/^#document-([1-9][0-9]*)$/', $href, $match) && (int) $match[1] <= $this->documentCount) {
            return '\\hyperlink{application-document-'.$match[1].'}{'.$text.'}';
        }

        return '\\href{'.self::url($href).'}{'.$text.'}';
    }

    public static function url(string $url): string
    {
        if (! preg_match('#^(https?://|mailto:|tel:)#i', $url) || preg_match('/[\x00-\x20]/', $url) || strpbrk($url, chr(92).'{}') !== false) {
            throw ValidationException::withMessages(['letter.body' => 'Links müssen gültige HTTP(S)-, mailto- oder tel-URLs sein.']);
        }

        if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::withMessages(['letter.body' => 'Ungültige HTTP(S)-URL.']);
        }

        return Latex::escapeUrl($url);
    }
}
