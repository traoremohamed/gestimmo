@extends('layouts.backLayout.designadmin')

@php
    $statuts = \App\Models\Validation\Circuit::STATUTS;
    $modifiable = $circuit->estModifiable() && auth()->user()->can('circuits-edit');
    $aide = \App\Services\Validation\Catalogue::ACTIONS[$circuit->action][4] ?? null;
@endphp

@section('content')
<x-page :titre="$circuit->libelle" module="Validations" :retour="route('circuits.index')">
    <x-slot:badge><span class="badge text-bg-{{ $statuts[$circuit->statut][1] }} fs-6">{{ $statuts[$circuit->statut][0] }} · v{{ $circuit->version }}</span></x-slot:badge>
    <x-slot:actions>
        @can('circuits-approuver')
            @if($circuit->statut === 'BROUILLON')
                <x-action :url="route('circuits.approuver', $circuit)" libelle="Approuver" icone="ti-check" classe="btn btn-sm btn-outline-success"
                    confirmer="Approuver ce circuit ? (l'approbation doit venir d'une autre personne que son auteur)" />
            @elseif($circuit->statut === 'APPROUVE')
                <x-action :url="route('circuits.activer', $circuit)" libelle="Activer" icone="ti-player-play" classe="btn btn-sm btn-success"
                    confirmer="Activer ce circuit ? L'action sera soumise à validation dès maintenant (la version précédente sera retirée)." />
            @endif
            @if(in_array($circuit->statut, ['ACTIF', 'APPROUVE'], true))
                <x-action :url="route('circuits.retirer', $circuit)" libelle="Retirer" icone="ti-player-stop" classe="btn btn-sm btn-outline-danger"
                    confirmer="Retirer ce circuit ? L'action s'exécutera de nouveau sans validation." />
            @endif
        @endcan
        @can('circuits-edit')
            @unless($circuit->estModifiable())
                <x-action :url="route('circuits.version', $circuit)" libelle="Nouvelle version" icone="ti-copy" classe="btn btn-sm btn-outline-primary" />
            @endunless
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-4">
            <form method="POST" action="{{ route('circuits.update', $circuit) }}" class="card mb-3">
                @csrf @method('PUT')
                <div class="card-header">Circuit</div>
                <fieldset class="card-body row" @disabled(!$modifiable)>
                    <x-form.select name="action" label="Action" :options="$actions" :value="$circuit->action" required col="col-12" />
                    <x-form.input name="libelle" label="Nom" :value="$circuit->libelle" required col="col-12" />
                    <x-form.input name="montant_declenchement" label="À partir de (montant)" type="number" step="0.01" min="0" :value="$circuit->montant_declenchement" col="col-md-6" />
                    <x-form.input name="filtre" label="Portée" :value="$circuit->filtre" col="col-md-6" :aide="$aide" />
                    <x-form.textarea name="description" label="Description" :value="$circuit->description" rows="2" />
                </fieldset>
                @if($modifiable)<div class="card-footer text-end"><button class="btn btn-sm btn-primary">Enregistrer</button></div>@endif
            </form>
            <div class="card mb-3"><div class="card-body small">
                <div>Créé par {{ $circuit->auteur?->nom_complet ?? '—' }} le {{ $circuit->created_at->format('d/m/Y') }}</div>
                @if($circuit->approuve_at)<div>Approuvé par {{ $circuit->approbateur?->nom_complet }} le {{ $circuit->approuve_at->format('d/m/Y') }}</div>@endif
                @if($circuit->active_at)<div>Actif depuis le {{ $circuit->active_at->format('d/m/Y H:i') }}</div>@endif
                @if($versions->count() > 1)
                    <div class="mt-2">Versions : @foreach($versions as $v)<a href="{{ route('circuits.show', $v) }}" class="badge {{ $v->id === $circuit->id ? 'text-bg-primary' : 'badge-soft-secondary' }}">v{{ $v->version }} {{ strtolower($statuts[$v->statut][0]) }}</a> @endforeach</div>
                @endif
            </div></div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">Étapes de validation</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>#</th><th>Étape</th><th>Validateur</th><th>Montant</th><th>Délai</th><th></th></tr></thead>
                        <tbody>
                        @forelse($circuit->etapes as $e)
                            <tr>
                                <td><span class="badge rounded-pill text-bg-primary">{{ $e->ordre }}</span></td>
                                <td class="fw-semibold">{{ $e->libelle }}@if($e->separation_auteur)<div class="small text-muted fw-normal">validateur ≠ demandeur</div>@endif</td>
                                <td class="small">{{ $e->designation() }}</td>
                                <td class="small">{{ $e->montant_min !== null ? 'dès ' . montant($e->montant_min) : '' }} {{ $e->montant_max !== null ? 'jusqu\'à ' . montant($e->montant_max) : '' }}</td>
                                <td class="small">{{ $e->delai_heures ? $e->delai_heures . ' h' : '—' }}</td>
                                <td class="text-end">
                                    @if($modifiable)
                                        <x-action :url="route('circuits.etapes.destroy', [$circuit, $e])" methode="DELETE" icone="ti-trash" classe="btn btn-sm btn-light text-danger" titre="Supprimer" confirmer="Supprimer cette étape ?" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-vide message="Aucune étape : ajoutez au moins une étape." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($modifiable)
                    <form method="POST" action="{{ route('circuits.etapes.store', $circuit) }}" class="card-body border-top row">
                        @csrf
                        <div class="col-12"><h6 class="text-uppercase text-muted small">Ajouter une étape</h6></div>
                        <x-form.input name="libelle" label="Libellé (ex. Visa du responsable)" required col="col-md-6" />
                        <x-form.select name="role_id" label="Profil validateur" :options="$roles" col="col-md-6" placeholder="—" />
                        <x-form.select name="user_id" label="ou personne désignée" :options="$utilisateurs" col="col-md-6" placeholder="—" />
                        <x-form.select name="permission_requise" label="ou permission requise" :options="$permissions" col="col-md-6" placeholder="—" recherche />
                        <x-form.input name="montant_min" label="Dès (montant)" type="number" step="0.01" min="0" col="col-md-3" />
                        <x-form.input name="montant_max" label="Jusqu'à" type="number" step="0.01" min="0" col="col-md-3" />
                        <x-form.input name="delai_heures" label="Délai (heures)" type="number" min="1" col="col-md-3" />
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <input type="hidden" name="separation_auteur" value="0">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="separation_auteur" value="1" id="sep" checked>
                                <label class="form-check-label" for="sep">Validateur ≠ demandeur</label></div>
                        </div>
                        <div class="col-12 text-end"><button class="btn btn-outline-primary"><i class="ti ti-plus"></i> Ajouter l'étape</button></div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-page>
@endsection
