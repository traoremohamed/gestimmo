@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$element->nom" module="Patrimoine" :retour="route('programmes.index')">
    <x-slot:actions>
        @can('programmes-edit')
            <a href="{{ route('programmes.edit', $element) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Type</dt><dd>{{ \App\Models\Programme::TYPES[$element->type] ?? $element->type }}</dd>
                        <dt>Localisation</dt><dd>{{ collect([$element->adresse, $element->commune, $element->ville])->filter()->implode(', ') ?: '—' }}</dd>
                        <dt>Lancement / livraison</dt><dd>{{ date_fr($element->date_lancement) ?: '—' }} / {{ date_fr($element->date_livraison_prevue) ?: '—' }}</dd>
                        <dt>Lotissements</dt><dd>{{ $element->lotissements()->pluck('nom')->implode(', ') ?: '—' }}</dd>
                        <dt>Immeubles</dt><dd>{{ $element->immeubles()->pluck('nom')->implode(', ') ?: '—' }}</dd>
                        @if($element->description)<dt>Description</dt><dd>{{ $element->description }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @include('patrimoine._biens', ['biens' => $element->biens()->orderBy('reference')->get(), 'filtre' => ['programme_id' => $element->id]])
        </div>
    </div>
</x-page>
@endsection
