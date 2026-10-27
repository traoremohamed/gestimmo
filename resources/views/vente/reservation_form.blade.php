@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Réserver un bien" module="Ventes" :retour="route('reservations.index')">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('reservations.store') }}" class="card">
                @csrf
                <div class="card-body row">
                    <x-form.select name="tiers_id" label="Client" :options="$tiers->pluck('nom_complet', 'id')" required recherche col="col-12" />
                    <x-form.select name="bien_id" label="Bien disponible à la vente" :options="$biens->mapWithKeys(fn ($b) => [$b->id => $b->designation . ' — ' . montant($b->prix_vente)])"
                        :value="$bienId" required recherche col="col-12" />
                    <x-form.input name="jours" label="Durée de blocage (jours)" type="number" min="1" max="365" :value="$duree" col="col-md-4"
                        aide="Le bien est libéré automatiquement à expiration." />
                    <x-form.textarea name="commentaire" label="Commentaire" col="col-md-8" rows="2" />
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-lock"></i> Réserver</button></div>
            </form>
        </div>
    </div>
</x-page>
@endsection
