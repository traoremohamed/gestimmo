@extends('layouts.backLayout.designadmin')

@php($envoyee = $campagne->statut === 'envoyee')

@section('content')
<x-page :titre="$campagne->nom" module="Marketing" :retour="route('campagnes.index')">
    <x-slot:actions>
        @unless($envoyee)
            @can('campagnes-create')<a href="{{ route('campagnes.edit', $campagne) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-pencil"></i> Modifier</a>@endcan
            @can('campagnes-envoyer')
                <x-action :url="route('campagnes.envoyer', $campagne)" :libelle="'Envoyer à ' . $destinataires->count() . ' destinataire(s)'" icone="ti-send" classe="btn btn-sm btn-success"
                    confirmer="Envoyer la campagne maintenant ? Cette action est définitive." />
            @endcan
        @endunless
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ \App\Models\Campagne::CANAUX[$campagne->canal] }} {{ $campagne->objet ? '— ' . $campagne->objet : '' }}</div>
                <div class="card-body" style="white-space: pre-line">{{ $campagne->message }}</div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ $envoyee ? 'Destinataires (envoyée le ' . $campagne->envoyee_le->format('d/m/Y H:i') . ')' : 'Aperçu des destinataires' }} — {{ $destinataires->count() }}</div>
                <div class="table-responsive" style="max-height: 480px">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Nom</th><th>{{ $campagne->canal === 'email' ? 'E-mail' : 'Téléphone' }}</th>@if($envoyee)<th>Statut</th>@endif</tr></thead>
                        <tbody>
                            @forelse($destinataires as $d)
                                <tr><td>{{ $d['nom'] }}</td><td>{{ $campagne->canal === 'email' ? $d['email'] : $d['telephone'] }}</td>@if($envoyee)<td>{{ $d['statut'] }}</td>@endif</tr>
                            @empty
                                <tr><td colspan="3"><x-vide message="Aucun destinataire ne correspond à la cible." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-page>
@endsection
