@extends('statamic::layout')

@section('title', 'Sponsoring und Förderung')

@section('content')
    <header class="mb-6 flex items-end justify-between gap-4">
        <div>
            <h1 class="mb-2">Sponsoring und Förderung</h1>
            <p class="text-gray-600">Private Akten, getrennt nach persönlichen Mitteln, Unternehmensmitteln und Schulteam-Mitteln. Eine Änderung der Mittelzuordnung wird nicht automatisch vorgenommen.</p>
        </div>
        <a class="btn" href="{{ route('statamic.cp.sponsorships.export') }}">CSV exportieren</a>
    </header>

    @if (session('success'))<div class="mb-4 rounded border border-green-500 p-3" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded border border-red-500 p-3" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mb-10 rounded border p-5">
        <h2 class="mb-4 text-xl font-semibold">Förderfall anlegen</h2>
        @include('cp.sponsorships.form', ['record' => null])
    </section>

    <section class="space-y-8">
        @foreach ($categories as $category)
            <div>
                <h2 class="mb-3 text-xl font-semibold">{{ ['personal' => 'Persönliche Softwareprojekte', 'company' => 'Unternehmen / unabhängige Vorhaben', 'school' => 'AtheBlues / Schulrobotik'][$category] }}</h2>
                @php($group = $records->where('funding_category', $category))
                @if ($group->isEmpty())<p class="text-gray-600">Noch keine Förderfälle.</p>@endif
                @foreach ($group as $record)
                    <details class="mb-4 rounded border p-4">
                        <summary class="cursor-pointer font-semibold">{{ $record->data['organisation'] }} · {{ $record->data['status'] }} · Zusage {{ number_format($record->data['pledged_cents'] / 100, 2, ',', '.') }} € / erhalten {{ number_format($record->data['received_cents'] / 100, 2, ',', '.') }} €</summary>
                        <p class="my-3 text-sm text-gray-600">Verfügbar in {{ $record->data['allocated_project'] ?: 'noch keinem Projekt zugewiesen' }}: {{ number_format(($balances[$category.':'.$record->data['allocated_project']] ?? 0) / 100, 2, ',', '.') }} €</p>
                        @include('cp.sponsorships.form', ['record' => $record])
                        @if (!empty($record->data['documents']))
                            <div class="mt-4"><strong>Vertrauliche Belege:</strong>
                                <ul>@foreach ($record->data['documents'] as $i => $document)<li><a href="{{ route('statamic.cp.sponsorships.documents', ['id' => $record->id, 'index' => $i]) }}">{{ is_array($document) ? $document['label'] : 'Beleg '.$loop->iteration }} herunterladen</a></li>@endforeach</ul>
                            </div>
                        @endif
                    </details>
                @endforeach
            </div>
        @endforeach
    </section>
@endsection
