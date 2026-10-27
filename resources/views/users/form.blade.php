@extends('layouts.backLayout.designadmin')

@php($edition = $user->exists)

@section('content')
<x-page :titre="$edition ? 'Modifier ' . $user->nom_complet : 'Nouvel utilisateur'" module="Paramétrage" :retour="route('users.index')">
    <form method="POST" action="{{ $edition ? route('users.update', \App\Helpers\Crypt::UrlCrypt($user->id)) : route('users.store') }}" autocomplete="off">
        @csrf
        @if($edition) @method('PUT') @endif
        <div class="row">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header">Identité et accès</div>
                    <div class="card-body row">
                        <x-form.input name="name" label="Nom" :value="$user->name" required col="col-md-4" />
                        <x-form.input name="prenom_users" label="Prénoms" :value="$user->prenom_users" required col="col-md-4" />
                        <x-form.select name="genre_users" label="Genre" :options="['Feminin' => 'Féminin', 'Masculin' => 'Masculin']" :value="$user->genre_users" col="col-md-4" />
                        <x-form.input name="email" label="E-mail" type="email" :value="$user->email" required col="col-md-6" />
                        <x-form.input name="cel_users" label="Téléphone mobile" :value="$user->cel_users" col="col-md-3" />
                        <x-form.input name="tel_users" label="Téléphone fixe" :value="$user->tel_users" col="col-md-3" />
                        <x-form.input name="fonction" label="Fonction" :value="$user->fonction" col="col-md-6" />
                        <x-form.input name="adresse_users" label="Adresse" :value="$user->adresse_users" col="col-md-6" />
                        <x-form.input name="login_users" label="Identifiant de connexion" :value="$user->login_users" required col="col-md-6" aide="8 caractères minimum" />
                        <x-form.input name="password" label="{{ $edition ? 'Nouveau mot de passe (vide = inchangé)' : 'Mot de passe provisoire' }}" type="password" :required="!$edition" col="col-md-6"
                            aide="Changement obligatoire à la première connexion." autocomplete="new-password" />
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header">Profil et période d'accès</div>
                    <div class="card-body row">
                        <x-form.select name="roles" label="Profil" :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->name . ($r->estModele() ? ' (modèle)' : '')])" :value="$roleActuel" required col="col-12" />
                        <x-form.input name="date_debut_acces" label="Accès à partir du" type="date" :value="$user->date_debut_acces" col="col-md-6" />
                        <x-form.input name="date_fin_acces" label="Jusqu'au" type="date" :value="$user->date_fin_acces" col="col-md-6" aide="Vide = sans limite (stagiaire, intérimaire…)" />
                        <x-form.checkbox name="flag_actif_users" label="Compte actif" :value="(bool) ($user->flag_actif_users ?? true)" col="col-12" />
                        @if($superAdmin)
                            @if($horsAgence)
                                <x-form.select name="agence_id" label="Agence du compte" :options="$agences" :value="$user->agence_id" col="col-12" placeholder="— Plateforme —" />
                            @endif
                            <x-form.select name="agences_supplementaires[]" label="Agences supplémentaires" :options="$agences" :value="$user->agences->pluck('id')->all()" multiple col="col-12"
                                aide="Pour un groupe possédant plusieurs agences : l'utilisateur pourra changer d'agence de travail." />
                        @endif
                    </div>
                </div>
                @if($edition && $user->verrouille_jusqu_a?->isFuture())
                    <div class="alert alert-danger small"><i class="ti ti-lock"></i> Compte verrouillé jusqu'à {{ $user->verrouille_jusqu_a->format('H:i') }} après {{ $user->tentatives_echouees }} échecs.</div>
                @endif
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('users.index') }}" class="btn btn-light">Annuler</a>
                    <button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button>
                </div>
            </div>
        </div>
    </form>
</x-page>
@endsection
