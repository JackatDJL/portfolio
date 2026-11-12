@php
    $data = $record->data ?? [];
    $action = $record ? route('statamic.cp.sponsorships.update', $record->id) : route('statamic.cp.sponsorships.store');
    $expenseText = collect($data['expenses'] ?? [])->map(fn ($expense) => $expense['date'].' | '.$expense['description'].' | '.number_format($expense['amount_cents'] / 100, 2, '.', ''))->implode("\n");
@endphp
<form action="{{ $action }}" method="post" enctype="multipart/form-data" class="sponsorship-form grid gap-4 md:grid-cols-2">
    @csrf
    @if ($record) @method('put') @endif
    <label>Kategorie
        <select name="funding_category" required @disabled($record)>
            @foreach ($categories as $category)<option value="{{ $category }}" @selected(($record->funding_category ?? old('funding_category', 'personal')) === $category)>{{ ['personal' => 'Persönlich', 'company' => 'Unternehmen', 'school' => 'Schulteam / AtheBlues'][$category] }}</option>@endforeach
        </select>
    </label>
    @if($record)<input type="hidden" name="funding_category" value="{{ $record->funding_category }}">@endif
    <label>Organisation<input class="input-text" name="organisation" value="{{ old('organisation', $data['organisation'] ?? '') }}" required maxlength="200"></label>
    <label>Kontaktperson<input class="input-text" name="contact" value="{{ old('contact', $data['contact'] ?? '') }}" maxlength="200"></label>
    <label>Kontakt-E-Mail<input class="input-text" name="email" type="email" value="{{ old('email', $data['email'] ?? '') }}"></label>
    <label>Status
        <select name="status" required>@foreach (['new' => 'Neu', 'contacted' => 'Kontaktiert', 'negotiating' => 'In Klärung', 'accepted' => 'Zugesagt', 'declined' => 'Abgelehnt', 'closed' => 'Abgeschlossen'] as $key => $label)<option value="{{ $key }}" @selected(old('status', $data['status'] ?? 'new') === $key)>{{ $label }}</option>@endforeach</select>
    </label>
    <label>Nachfassen am<input name="follow_up_date" type="date" value="{{ old('follow_up_date', $data['follow_up_date'] ?? '') }}"></label>
    <label>Zugesagt (EUR)<input name="pledged_amount" type="number" step="0.01" min="0" value="{{ old('pledged_amount', isset($data['pledged_cents']) ? number_format($data['pledged_cents'] / 100, 2, '.', '') : '') }}"></label>
    <label>Geld erhalten (EUR)<input name="received_amount" type="number" step="0.01" min="0" value="{{ old('received_amount', isset($data['received_cents']) ? number_format($data['received_cents'] / 100, 2, '.', '') : '') }}"></label>
    <label class="flex items-center gap-2"><input type="checkbox" name="received_confirmed" value="1" @checked(old('received_confirmed', $data['received_confirmed'] ?? false))> Zahlungseingang bestätigt</label>
    <label>Art des Beitrags<select name="contribution_kind"><option value="monetary">Geld</option><option value="in_kind" @selected(old('contribution_kind', $data['contribution_kind'] ?? '') === 'in_kind')>Sachleistung</option><option value="both" @selected(old('contribution_kind', $data['contribution_kind'] ?? '') === 'both')>Geld und Sachleistung</option></select></label>
    <label>Sachleistung<textarea name="in_kind_description" rows="2">{{ old('in_kind_description', $data['in_kind_description'] ?? '') }}</textarea></label>
    <label>Zweckbindung<textarea name="restricted_purpose" rows="2">{{ old('restricted_purpose', $data['restricted_purpose'] ?? '') }}</textarea></label>
    <label>Zugewiesenes Projekt
        <select name="allocated_project"><option value="">Noch nicht zugewiesen</option>
            @foreach ($projects as $category => $categoryProjects) @foreach ($categoryProjects as $key => $label)<option value="{{ $key }}" data-category="{{ $category }}" @selected(($record->funding_category ?? old('funding_category', 'personal')) === $category && ($data['allocated_project'] ?? '') === $key)>{{ $label }}</option>@endforeach @endforeach
        </select>
    </label>
    <label>Für Projekt verfügbar (EUR)<input name="allocated_amount" type="number" step="0.01" min="0" value="{{ old('allocated_amount', isset($data['allocated_cents']) ? number_format($data['allocated_cents'] / 100, 2, '.', '') : '') }}"></label>
    <label class="md:col-span-2">Ausgaben (eine Zeile: YYYY-MM-DD | Beschreibung | EUR)<textarea class="font-mono" name="expenses" rows="3">{{ old('expenses', $expenseText) }}</textarea></label>
    <label class="md:col-span-2">Korrespondenz / Verlauf<textarea name="correspondence" rows="4">{{ old('correspondence', $data['correspondence'] ?? '') }}</textarea></label>
    <label class="md:col-span-2">Interne Notizen<textarea name="internal_notes" rows="3">{{ old('internal_notes', $data['internal_notes'] ?? '') }}</textarea></label>
    <label>Dokumentart<input class="input-text" name="document_label" value="Rechnung, Beleg oder weiterer Nachweis" maxlength="100"></label>
    <label class="md:col-span-2">Rechnungen, Belege, unterstützende Dokumente (PDF/JPG/PNG; max. 15 MB je Datei)<input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple></label>
    <label class="flex items-center gap-2"><input type="checkbox" name="acknowledgment_permission" value="1" @checked(old('acknowledgment_permission', $data['acknowledgment_permission'] ?? false))> Öffentliche Danksagung ausdrücklich erlaubt</label>
    <div class="md:col-span-2"><button class="btn-primary btn" type="submit">{{ $record ? 'Änderungen speichern' : 'Förderfall speichern' }}</button></div>
</form>
