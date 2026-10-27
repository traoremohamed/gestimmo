@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Nouveau séjour" module="Résidences meublées" :retour="route('sejours.index')">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('sejours.store') }}" class="card">
                @csrf
                <div class="card-body row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required" for="bien_id">Bien meublé</label>
                        <select name="bien_id" id="bien_id" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($biens as $bien)
                                <option value="{{ $bien->id }}" data-prix="{{ $bien->prix_nuitee }}" @selected((int) old('bien_id', $bienId) === $bien->id)>{{ $bien->designation }} — {{ montant($bien->prix_nuitee) }}/nuit</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form.select name="tiers_id" label="Client" :options="$tiers" required recherche col="col-md-6" />
                    <x-form.input name="date_arrivee" label="Arrivée" type="date" :value="request('arrivee', today()->toDateString())" required col="col-md-3" />
                    <x-form.input name="date_depart" label="Départ" type="date" :value="\Illuminate\Support\Carbon::parse(request('arrivee', today()))->addDay()->toDateString()" required col="col-md-3" />
                    <x-form.input name="nb_personnes" label="Personnes" type="number" min="1" value="1" required col="col-md-2" />
                    <x-form.input name="prix_nuitee" label="Prix / nuit" type="number" step="0.01" min="0" col="col-md-4" aide="Vide = tarif du bien" />
                    <x-form.input name="remise" label="Remise" type="number" step="0.01" min="0" col="col-md-3" />
                    <x-form.input name="frais_supplementaires" label="Frais (ménage, extras…)" type="number" step="0.01" min="0" col="col-md-3" />
                    <x-form.select name="source" label="Source" :options="['direct' => 'Direct', 'telephone' => 'Téléphone', 'site' => 'Site web', 'plateforme' => 'Plateforme de réservation', 'agence' => 'Agence partenaire']" col="col-md-6" />
                    <x-form.textarea name="observation" label="Observation" rows="2" />
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Heures d'arrivée / départ : {{ parametre('meuble.heure_arrivee') }} / {{ parametre('meuble.heure_depart') }}</span>
                    <button class="btn btn-primary"><i class="ti ti-calendar-check"></i> Réserver</button>
                </div>
            </form>
        </div>
    </div>
</x-page>
@endsection
