@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$element->nom" module="Patrimoine" :retour="route('immeubles.index')">
    <x-slot:actions>
        @if(app(\App\Support\AgenceCourante::class)->moduleActif('syndic') && !$element->copropriete)
            @can('coproprietes-create')
                <a href="{{ route('coproprietes.create', ['immeuble_id' => $element->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-building-community"></i> Créer la copropriété</a>
            @endcan
        @endif
        @can('biens-generer')
            <a href="{{ route('biens.generation') }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-wand"></i> Générer des appartements</a>
        @endcan
        @can('immeubles-edit')
            <a href="{{ route('immeubles.edit', $element) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Adresse</dt><dd>{{ $element->adresse ?: '—' }}</dd>
                        <dt>Programme / lotissement</dt><dd>{{ $element->programme?->nom ?? '—' }} / {{ $element->lotissement?->nom ?? '—' }}</dd>
                        <dt>Îlot / lot</dt><dd>{{ $element->ilot ?: '—' }} / {{ $element->lot ?: '—' }}</dd>
                        <dt>Étages</dt><dd>{{ $element->nombre_etages }}</dd>
                        <dt>Propriétaire</dt><dd>{{ $element->proprietaire?->nom_complet ?? 'Agence / promoteur' }}</dd>
                        @if($element->copropriete)
                            <dt>Copropriété</dt><dd><a href="{{ route('coproprietes.show', $element->copropriete) }}">{{ $element->copropriete->nom }}</a></dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            @include('patrimoine._biens', ['biens' => $element->biens()->orderBy('etage')->orderBy('porte')->get(), 'filtre' => ['immeuble_id' => $element->id]])
        </div>
    </div>
</x-page>
@endsection
