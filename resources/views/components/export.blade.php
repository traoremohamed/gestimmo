@props(['url' => null, 'formats' => \App\Support\Export::FORMATS, 'libelle' => 'Exporter'])

{{-- Menu d'export de la page courante (filtres conservés) : Excel, PDF signé QR, CSV --}}
@php($base = $url ?? request()->fullUrl())
<div class="dropdown no-print">
    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-download"></i> {{ $libelle }}</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach($formats as $format => $nom)
            <li><a class="dropdown-item" href="{{ $base . (str_contains($base, '?') ? '&' : '?') . 'format=' . $format }}">
                <i class="ti {{ ['xlsx' => 'ti-file-spreadsheet text-success', 'pdf' => 'ti-file-type-pdf text-danger', 'csv' => 'ti-file-text'][$format] ?? 'ti-file' }}"></i> {{ $nom }}</a></li>
        @endforeach
    </ul>
</div>
