@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$element->nom" module="Patrimoine" :retour="route('lotissements.index')">
    <x-slot:actions>
        @can('biens-generer')
            <a href="{{ route('biens.generation') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-wand"></i> Générer des lots</a>
        @endcan
        @can('lotissements-edit')
            <a href="{{ route('lotissements.edit', $element) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Programme</dt><dd>{{ $element->programme?->nom ?? '—' }}</dd>
                        <dt>Référence</dt><dd>{{ $element->reference ?: '—' }}</dd>
                        <dt>Titre foncier</dt><dd>{{ \App\Models\Referentiel::libelle('type_titre_foncier', $element->type_titre) ?? '—' }} {{ $element->numero_titre }}</dd>
                        <dt>Préfecture / village</dt><dd>{{ collect([$element->prefecture, $element->village])->filter()->implode(' / ') ?: '—' }}</dd>
                        <dt>Superficie</dt><dd>{{ $element->superficie ? number_format((float) $element->superficie, 0, ',', ' ') . ' m²' : '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @include('patrimoine._biens', ['biens' => $element->biens()->orderBy('ilot')->orderByRaw('length(numero_lot), numero_lot')->get(), 'filtre' => ['lotissement_id' => $element->id]])
        </div>
    </div>
</x-page>
@endsection
