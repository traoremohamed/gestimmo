@extends('layouts.backLayout.designadmin')

@php($statuts = \App\Models\Validation\Circuit::STATUTS)

@section('content')
<x-page titre="Circuits de validation" module="Validations">
    <x-slot:actions>
        @can('circuits-edit')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCircuit"><i class="ti ti-plus"></i> Nouveau circuit</button>@endcan
    </x-slot:actions>
    <p class="text-muted small">Une action couverte par un circuit <strong>actif</strong> n'est plus exécutée directement : elle devient une demande, validée étape par étape
        (profil, permission ou personne désignée, selon le montant), puis exécutée automatiquement à la dernière approbation. Un circuit est créé en brouillon,
        approuvé par une autre personne, puis activé ; pour le modifier ensuite, on en crée une nouvelle version.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Circuit</th><th>Action</th><th>Portée</th><th class="text-end">Dès</th><th class="text-center">Étapes</th><th>Version</th><th>Statut</th></tr></thead>
                <tbody>
                @forelse($circuits as $c)
                    <tr class="ligne-cliquable" data-href="{{ route('circuits.show', $c) }}">
                        <td class="fw-semibold">{{ $c->libelle }}</td>
                        <td class="small">{{ $actions[$c->action] ?? \App\Services\Validation\Catalogue::libelle($c->action) }}</td>
                        <td class="small">{{ $c->filtre ?: 'toutes' }}</td>
                        <td class="text-end montant">{{ $c->montant_declenchement !== null ? montant($c->montant_declenchement) : '—' }}</td>
                        <td class="text-center">{{ $c->etapes_count }}</td>
                        <td>v{{ $c->version }}</td>
                        <td><span class="badge text-bg-{{ $statuts[$c->statut][1] }}">{{ $statuts[$c->statut][0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-vide message="Aucun circuit : toutes les actions s'exécutent directement." icone="ti-git-merge" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('circuits-edit')
        <div class="modal fade" id="modalCircuit" tabindex="-1" aria-labelledby="modalCircuit-titre" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('circuits.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title" id="modalCircuit-titre">Nouveau circuit</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="action" label="Action soumise à validation" :options="$actions" required col="col-12" />
                        <x-form.input name="libelle" label="Nom du circuit" required col="col-md-6" />
                        <x-form.input name="montant_declenchement" label="Validation exigée à partir de (montant)" type="number" step="0.01" min="0" col="col-md-6" aide="Vide = toujours" />
                        <x-form.input name="filtre" label="Portée (filtre)" col="col-md-6" aide="Vide = tous les cas (ex. un motif, un type, une catégorie)" />
                        <x-form.textarea name="description" label="Description" rows="2" />
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary">Créer le brouillon</button></div>
                </form>
            </div>
        </div>
    @endcan
</x-page>
@endsection
