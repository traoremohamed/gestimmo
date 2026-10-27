@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Mon agence" module="Administration">
    <form method="POST" action="{{ route('monagence.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="row">
            <div class="col-xl-9">
                <div class="card mb-4">
                    <div class="card-header">Identité</div>
                    <div class="card-body row">
                        <x-form.input name="raison_sociale" label="Raison sociale" :value="$agence->raison_sociale" required />
                        <x-form.input name="sigle" label="Sigle" :value="$agence->sigle" col="col-md-3" />
                        <x-form.input name="devise" label="Devise" :value="$agence->devise" required col="col-md-3" />
                        <x-form.input name="email" label="E-mail" type="email" :value="$agence->email" col="col-md-6" />
                        <x-form.input name="telephone" label="Téléphone" :value="$agence->telephone" col="col-md-3" />
                        <x-form.input name="telephone2" label="Téléphone 2" :value="$agence->telephone2" col="col-md-3" />
                        <x-form.input name="adresse" label="Adresse" :value="$agence->adresse" col="col-md-6" />
                        <x-form.input name="ville" label="Ville" :value="$agence->ville" col="col-md-3" />
                        <x-form.select name="pays_id" label="Pays" :options="$pays" :value="$agence->pays_id" col="col-md-3" />
                        <x-form.input name="rccm" label="RCCM" :value="$agence->rccm" col="col-md-6" />
                        <x-form.input name="compte_contribuable" label="Compte contribuable" :value="$agence->compte_contribuable" col="col-md-6" />
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">Apparence</div>
                    <div class="card-body row align-items-end">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="logo">Logo</label>
                            @if($agence->logo_url)<div class="mb-2"><img src="{{ $agence->logo_url }}" alt="Logo" height="48"></div>@endif
                            <input type="file" name="logo" id="logo" accept="image/*" class="form-control">
                        </div>
                        <x-form.input name="couleur" label="Couleur principale" type="color" :value="$agence->couleur" col="col-md-3" class="form-control-color" />
                        
                        <div class="col-12 small text-muted">
                            Site vitrine ({{ $agence->vitrine_active ? 'publié' : 'non publié' }}) : <a href="{{ route('vitrine.accueil', $agence->slug) }}" target="_blank" rel="noopener">{{ route('vitrine.accueil', $agence->slug) }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-end">
            <button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button>
        </div>
    </form>
</x-page>
@endsection
