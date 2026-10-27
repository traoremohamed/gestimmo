@extends('layouts.backLayout.designadmin')

@php($edition = $role->exists)

@section('content')
<x-page :titre="$edition ? 'Modifier le profil ' . $role->name : 'Nouveau profil'" module="Configuration" :retour="route('roles.index')">
    <form method="POST" action="{{ $edition ? route('roles.update', \App\Helpers\Crypt::UrlCrypt($role->id)) : route('roles.store') }}" class="card" style="max-width: 720px">
        @csrf
        @if($edition) @method('PUT') @endif
        <div class="card-body row">
            <x-form.input name="name" label="Nom du profil" :value="$role->name" required col="col-md-6" />
            @unless($edition)
                <x-form.select name="modele_id" label="Reprendre les droits de" :options="$modeles->mapWithKeys(fn ($m) => [$m->id => $m->name . ($m->estModele() ? ' (modèle)' : '')])"
                    :value="request('modele')" col="col-md-6" placeholder="— Profil vide —" aide="Les droits copiés restent modifiables ensuite." />
            @endunless
            <x-form.textarea name="description" label="Description" :value="$role->description" rows="2" />
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('roles.index') }}" class="btn btn-light">Annuler</a>
            <button class="btn btn-primary"><i class="ti ti-device-floppy"></i> {{ $edition ? 'Enregistrer' : 'Créer et attribuer les droits' }}</button>
        </div>
    </form>
</x-page>
@endsection
