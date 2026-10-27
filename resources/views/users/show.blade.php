@extends('layouts.backLayout.designadmin')

@php($hid = \App\Helpers\Crypt::UrlCrypt($user->id))

@section('content')
<x-page :titre="$user->nom_complet" module="Paramétrage" :retour="route('users.index')">
    <x-slot:actions>
        @can('users-edit')
            @if($user->verrouille_jusqu_a?->isFuture())
                <x-action :url="route('users.deverrouiller', $hid)" libelle="Déverrouiller" icone="ti-lock-open" classe="btn btn-sm btn-outline-danger" />
            @endif
            <a href="{{ route('users.edit', $hid) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-body">
                <dl class="fiche row mb-0">
                    <dt class="col-5">Identifiant</dt><dd class="col-7"><code>{{ $user->login_users }}</code></dd>
                    <dt class="col-5">E-mail</dt><dd class="col-7">{{ $user->email }}</dd>
                    <dt class="col-5">Téléphone</dt><dd class="col-7">{{ $user->cel_users ?: '—' }}</dd>
                    <dt class="col-5">Fonction</dt><dd class="col-7">{{ $user->fonction ?: '—' }}</dd>
                    <dt class="col-5">Profil</dt><dd class="col-7">@foreach($user->roles as $r)<span class="badge badge-soft-primary">{{ $r->name }}</span>@endforeach</dd>
                    <dt class="col-5">Agence</dt><dd class="col-7">{{ $user->agence?->nom ?? 'Plateforme' }} @foreach($user->agences as $a)<span class="badge badge-soft-secondary">+ {{ $a->nom }}</span>@endforeach</dd>
                </dl>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-body">
                <dl class="fiche row mb-0">
                    <dt class="col-5">État</dt><dd class="col-7">{{ $user->accesAutorise() ? 'Accès autorisé' : 'Accès inactif' }}</dd>
                    <dt class="col-5">Période d'accès</dt><dd class="col-7">{{ $user->date_debut_acces?->format('d/m/Y') ?? '—' }} → {{ $user->date_fin_acces?->format('d/m/Y') ?? 'sans limite' }}</dd>
                    <dt class="col-5">Dernière connexion</dt><dd class="col-7">{{ $user->derniere_connexion_at?->format('d/m/Y H:i') ?? 'jamais' }} {{ $user->derniere_ip ? '(' . $user->derniere_ip . ')' : '' }}</dd>
                    <dt class="col-5">Échecs de connexion</dt><dd class="col-7">{{ $user->tentatives_echouees }}{{ $user->verrouille_jusqu_a?->isFuture() ? ' — verrouillé jusqu\'à ' . $user->verrouille_jusqu_a->format('H:i') : '' }}</dd>
                    <dt class="col-5">Mot de passe</dt><dd class="col-7">{{ $user->flag_mdp ? 'personnalisé' : 'provisoire (à changer à la connexion)' }}</dd>
                </dl>
            </div></div>
        </div>
    </div>
</x-page>
@endsection
