<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SponsorshipManagementController extends Controller
{
    private const CATEGORIES = ['personal', 'company', 'school'];

    private function authorizeSuperuser(): void
    {
        abort_unless(\Statamic\Facades\User::current()?->isSuper(), 403);
    }

    private function projects(): array
    {
        return [
            'personal' => ['software' => 'Persönliche Open-Source- und Softwareprojekte'],
            'company' => ['rosenfold' => 'Rosenfold und unabhängige Vorhaben'],
            'school' => ['atheblues' => 'AtheBlues / Schulrobotik'],
        ];
    }

    public function index()
    {
        $this->authorizeSuperuser();
        $records = DB::table('sponsorship_records')->orderByDesc('created_at')->get()->map(function ($row) {
            $row->data = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
            unset($row->encrypted_payload);

            return $row;
        });

        $balances = [];
        foreach ($records as $record) {
            $data = $record->data;
            $project = $data['allocated_project'] ?? '';
            if ($project === '') {
                continue;
            }
            $balanceKey = $record->funding_category.':'.$project;
            $balances[$balanceKey] = ($balances[$balanceKey] ?? 0)
                + (int) ($data['allocated_cents'] ?? 0)
                - array_sum(array_column($data['expenses'] ?? [], 'amount_cents'));
        }

        return view('cp.sponsorships.index', [
            'records' => $records,
            'categories' => self::CATEGORIES,
            'projects' => $this->projects(),
            'balances' => $balances,
        ])->with('title', 'Sponsoring und Förderung');
    }

    public function store(Request $request)
    {
        $this->authorizeSuperuser();
        $data = $this->validated($request);
        $id = (string) Str::uuid();
        $data['documents'] = [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->storeAs($id, Str::uuid().'.'.($file->guessExtension() ?: 'bin'), 'funding_documents');
            $data['documents'][] = ['path' => $path, 'label' => trim((string) ($data['document_label'] ?? 'Beleg')) ?: 'Beleg'];
        }
        DB::table('sponsorship_records')->insert([
            'id' => $id,
            'funding_category' => $data['funding_category'],
            'encrypted_payload' => Crypt::encryptString(json_encode($this->payload($data), JSON_THROW_ON_ERROR)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('statamic.cp.sponsorships.index')->with('success', 'Förderfall gespeichert.');
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeSuperuser();
        $row = DB::table('sponsorship_records')->where('id', $id)->first();
        abort_unless($row, 404);
        $data = $this->validated($request);
        abort_unless($data['funding_category'] === $row->funding_category, 422, 'Die Mittelzuordnung eines Förderfalls ist unveränderlich.');
        $old = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
        $data['documents'] = $old['documents'] ?? [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->storeAs($id, Str::uuid().'.'.($file->guessExtension() ?: 'bin'), 'funding_documents');
            $data['documents'][] = ['path' => $path, 'label' => trim((string) ($data['document_label'] ?? 'Beleg')) ?: 'Beleg'];
        }
        DB::table('sponsorship_records')->where('id', $id)->update([
            'encrypted_payload' => Crypt::encryptString(json_encode($this->payload($data), JSON_THROW_ON_ERROR)),
            'updated_at' => now(),
        ]);

        return redirect()->route('statamic.cp.sponsorships.index')->with('success', 'Förderfall aktualisiert.');
    }

    public function download(string $id, int $index)
    {
        $this->authorizeSuperuser();
        $row = DB::table('sponsorship_records')->where('id', $id)->first();
        abort_unless($row, 404);
        $data = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
        $document = $data['documents'][$index] ?? null;
        $path = is_array($document) ? ($document['path'] ?? null) : $document;
        abort_unless(is_string($path) && Storage::disk('funding_documents')->exists($path), 404);

        return Storage::disk('funding_documents')->download($path);
    }

    public function export(): StreamedResponse
    {
        $this->authorizeSuperuser();
        $records = DB::table('sponsorship_records')->orderBy('funding_category')->get();

        return response()->streamDownload(function () use ($records): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kategorie', 'Organisation', 'Kontakt', 'Status', 'Nachfassen am', 'Zusage EUR', 'Erhalten EUR', 'Sachleistung', 'Zweckbindung', 'Projekt', 'Zugewiesen EUR', 'Ausgaben EUR', 'Notizen']);
            foreach ($records as $row) {
                $d = json_decode(Crypt::decryptString($row->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
                fputcsv($stream, [$row->funding_category, $this->csvSafe($d['organisation']), $this->csvSafe($d['contact']), $d['status'], $d['follow_up_date'], number_format($d['pledged_cents'] / 100, 2, '.', ''), number_format($d['received_cents'] / 100, 2, '.', ''), $this->csvSafe($d['in_kind_description']), $this->csvSafe($d['restricted_purpose']), $this->csvSafe($d['allocated_project']), number_format($d['allocated_cents'] / 100, 2, '.', ''), number_format(array_sum(array_column($d['expenses'], 'amount_cents')) / 100, 2, '.', ''), $this->csvSafe($d['internal_notes'])]);
            }
            fclose($stream);
        }, 'sponsorship-accounting.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'funding_category' => ['required', Rule::in(self::CATEGORIES)],
            'organisation' => ['required', 'string', 'max:200'],
            'contact' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'status' => ['required', Rule::in(['new', 'contacted', 'negotiating', 'accepted', 'declined', 'closed'])],
            'follow_up_date' => ['nullable', 'date'],
            'correspondence' => ['nullable', 'string', 'max:20000'],
            'pledged_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'received_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'received_confirmed' => ['nullable', 'boolean'],
            'contribution_kind' => ['required', Rule::in(['monetary', 'in_kind', 'both'])],
            'in_kind_description' => ['nullable', 'string', 'max:3000'],
            'restricted_purpose' => ['nullable', 'string', 'max:3000'],
            'allocated_project' => ['nullable', 'string', Rule::in(array_keys($this->projects()[$request->input('funding_category')] ?? []))],
            'allocated_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'expenses' => ['nullable', 'string', 'max:12000'],
            'internal_notes' => ['nullable', 'string', 'max:12000'],
            'acknowledgment_permission' => ['nullable', 'boolean'],
            'documents.*' => ['file', 'max:15360', 'mimes:pdf,png,jpg,jpeg'],
            'document_label' => ['nullable', 'string', 'max:100'],
        ]);
        $toCents = fn ($value): int => (int) round(((float) ($value ?? 0)) * 100);
        $data['pledged_cents'] = $toCents($data['pledged_amount'] ?? 0);
        $data['received_cents'] = $toCents($data['received_amount'] ?? 0);
        $data['allocated_cents'] = $toCents($data['allocated_amount'] ?? 0);
        $monetaryConfirmed = ($data['received_confirmed'] ?? false) && $data['contribution_kind'] !== 'in_kind';
        if ($data['contribution_kind'] === 'in_kind') {
            $data['received_cents'] = 0;
        }
        $availableForAllocation = $monetaryConfirmed ? $data['received_cents'] : 0;
        if (! $monetaryConfirmed) {
            if ($data['allocated_cents'] > 0 || trim((string) ($data['expenses'] ?? '')) !== '') {
                abort(422, 'Vorhandene Projektzuweisungen und Ausgaben müssen vor dem Entfernen der Zahlungsbestätigung ausdrücklich gelöscht werden.');
            }
            $data['allocated_cents'] = 0;
            $data['expenses'] = [];
        } elseif ($data['allocated_cents'] > $availableForAllocation || ($data['allocated_cents'] > 0 && empty($data['allocated_project']))) {
            abort(422, 'Zuweisung muss einem Projekt angehören und darf bestätigte Zahlungseingänge nicht überschreiten.');
        } else {
            $data['expenses'] = $this->parseExpenses((string) ($data['expenses'] ?? ''), $data['allocated_cents']);
        }

        return $data;
    }

    private function parseExpenses(string $input, int $allocation): array
    {
        $expenses = [];
        foreach (preg_split('/\R/u', trim($input), -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $validDate = count($parts) === 3 && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $parts[0]) === 1
                && checkdate((int) substr($parts[0], 5, 2), (int) substr($parts[0], 8, 2), (int) substr($parts[0], 0, 4));
            abort_unless($validDate && is_numeric($parts[2]) && (float) $parts[2] >= 0, 422, 'Ausgabenformat: YYYY-MM-DD | Beschreibung | Betrag in EUR.');
            $expenses[] = ['date' => $parts[0], 'description' => mb_substr($parts[1], 0, 300), 'amount_cents' => (int) round((float) $parts[2] * 100)];
        }
        abort_unless(array_sum(array_column($expenses, 'amount_cents')) <= $allocation, 422, 'Ausgaben dürfen die Projektzuweisung nicht überschreiten.');

        return $expenses;
    }

    private function payload(array $data): array
    {
        return [
            'organisation' => $data['organisation'], 'contact' => $data['contact'] ?? '', 'email' => $data['email'] ?? '',
            'status' => $data['status'], 'follow_up_date' => $data['follow_up_date'] ?? '', 'correspondence' => $data['correspondence'] ?? '',
            'pledged_cents' => $data['pledged_cents'], 'received_cents' => $data['received_cents'],
            'received_confirmed' => (bool) ($data['received_confirmed'] ?? false), 'contribution_kind' => $data['contribution_kind'],
            'in_kind_description' => $data['in_kind_description'] ?? '', 'restricted_purpose' => $data['restricted_purpose'] ?? '',
            'allocated_project' => $data['allocated_project'] ?? '', 'allocated_cents' => $data['allocated_cents'], 'expenses' => $data['expenses'],
            'internal_notes' => $data['internal_notes'] ?? '', 'acknowledgment_permission' => (bool) ($data['acknowledgment_permission'] ?? false),
            'documents' => $data['documents'] ?? [],
        ];
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^\s*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
