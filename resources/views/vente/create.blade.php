@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Nouveau contrat de vente" module="Ventes" :retour="route('ventes.index')">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('ventes.store') }}" class="card">
                @csrf
                <div class="card-body">
                    <x-form.select name="acquereur_id" label="Acquéreur" :options="$tiers->pluck('nom_complet', 'id')" :value="$acquereurId" required recherche col="col-12" />
                    <p class="small text-muted mb-0">
                        Le client n'existe pas encore ?
                        @can('tiers-create')<a href="{{ route('tiers.create', ['role' => 'acquereur']) }}">Créer sa fiche</a>.@endcan
                        Les frais obligatoires et les conditions par défaut de l'agence sont appliqués au brouillon.
                    </p>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-arrow-right"></i> Créer le brouillon</button></div>
            </form>
        </div>
    </div>
</x-page>
@endsection
