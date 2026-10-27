@extends('layouts.backLayout.designadmin')

@php
    use App\Enums\StatutBail;
    use App\Models\EtatLieux;
    $soldeDu = $loyers->filter(fn ($l) => $l->statut->value !== 'annule')->sum(fn ($l) => (float) $l->resteAPayer());
    $echu = $loyers->filter(fn ($l) => $l->estEnRetard())->sum(fn ($l) => (float) $l->resteAPayer());
@endphp

@section('content')
<x-page :titre="$bail->numero ? 'Bail ' . $bail->numero : 'Bail (brouillon)'" module="Gestion locative" :retour="route('baux.index')">
    <x-slot:badge><x-statut :valeur="$bail->statut" class="fs-6" /></x-slot:badge>
    <x-attente-validation :objet="$bail" />
    <x-slot:actions>
        @if($bail->statut === StatutBail::Brouillon)
            @can('baux-edit')<a href="{{ route('baux.edit', $bail) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-pencil"></i> Modifier</a>@endcan
            @can('baux-activer')
                <x-action :url="route('baux.activer', $bail)" libelle="Activer le bail" icone="ti-player-play" classe="btn btn-sm btn-success"
                    confirmer="Activer le bail ? Les sommes d'entrée et les loyers d'avance seront générés et le bien marqué loué." />
            @endcan
        @else
            <a href="{{ route('baux.contrat', $bail) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-file-type-pdf"></i> Contrat PDF</a>
        @endif
        @if($bail->statut === StatutBail::Resilie)
            @php($decompte = $bail->decomptes->first())
            @if($decompte)
                <a href="{{ route('decomptes.show', $decompte) }}" class="btn btn-sm btn-primary"><i class="ti ti-receipt-refund"></i> Décompte de sortie</a>
            @else
                @can('baux-sortie')
                    <x-action :url="route('baux.sortie', $bail)" libelle="Décompte de sortie" icone="ti-receipt-refund" classe="btn btn-sm btn-primary"
                        confirmer="Préparer le décompte de sortie (garanties, réparations, compteurs, impayés) ?" />
                @endcan
            @endif
            @can('baux-edit')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEtatLieux"><i class="ti ti-clipboard-check"></i> État des lieux</button>
            @endcan
        @endif
        @if($bail->statut === StatutBail::Actif)
            @can('encaissements-create')
                <a href="{{ route('encaissements.create', ['tiers_id' => $bail->locataire_id, 'activite' => 'location']) }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>
            @endcan
            @can('baux-edit')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalRevision"><i class="ti ti-trending-up"></i> Réviser le loyer</button>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEtatLieux"><i class="ti ti-clipboard-check"></i> État des lieux</button>
            @endcan
            @can('baux-resilier')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalResiliation"><i class="ti ti-door-exit"></i> Résilier</button>
            @endcan
        @endif
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Loyer + charges" :valeur="montant((float) $bail->loyer_mensuel + (float) $bail->charges_mensuelles)" icone="ti-key" detail="par mois" />
        <x-stat libelle="Dépôt de garantie" :valeur="montant($bail->depot_garantie)" icone="ti-shield-lock" :detail="$bail->mois_caution . ' mois'" />
        <x-stat libelle="Impayés échus" :valeur="montant($echu)" icone="ti-alert-triangle" />
        <x-stat libelle="Solde total dû" :valeur="montant($soldeDu)" icone="ti-wallet" />
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Locataire</dt><dd><a href="{{ route('tiers.show', $bail->locataire) }}">{{ $bail->locataire->nom_complet }}</a><div class="small text-muted">{{ $bail->locataire->contact }}</div></dd>
                        @if($bail->garant)<dt>Garant</dt><dd><a href="{{ route('tiers.show', $bail->garant) }}">{{ $bail->garant->nom_complet }}</a></dd>@endif
                        <dt>Bien</dt><dd><a href="{{ route('biens.show', $bail->bien) }}">{{ $bail->bien->designation }}</a></dd>
                        <dt>Propriétaire</dt><dd>{{ $bail->bien->proprietaire?->nom_complet ?? 'Agence' }} {{ $bail->mandat ? '(mandat ' . $bail->mandat->numero . ')' : '' }}</dd>
                        <dt>Type / durée</dt><dd>{{ \App\Models\Bail::TYPES[$bail->type_bail] }} — {{ $bail->duree_mois }} mois {{ $bail->tacite_reconduction ? '(tacite reconduction)' : '' }}</dd>
                        <dt>Période</dt><dd>du {{ $bail->date_debut->format('d/m/Y') }} au {{ $bail->date_fin?->format('d/m/Y') ?? '—' }}</dd>
                        <dt>Échéance</dt><dd>le {{ $bail->jour_echeance }} de chaque période de {{ $bail->periodicite_mois }} mois</dd>
                        <dt>Pénalité / révision</dt><dd>{{ $bail->taux_penalite }} % / {{ $bail->taux_revision }} % {{ $bail->date_prochaine_revision ? '(le ' . $bail->date_prochaine_revision->format('d/m/Y') . ')' : '' }}</dd>
                        @if($bail->date_resiliation)
                            <dt>Résiliation</dt><dd>le {{ $bail->date_resiliation->format('d/m/Y') }} — {{ \App\Models\Referentiel::libelle('motif_resiliation', $bail->motif_resiliation) }}
                                @if($bail->depot_restitue !== null)<br>Dépôt restitué : {{ montant($bail->depot_restitue) }}@endif</dd>
                        @endif
                        @if($bail->observation)<dt>Clauses</dt><dd>{{ $bail->observation }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">États des lieux</div>
                <ul class="list-group list-group-flush">
                    @if((float) $bail->caution_sodeci || (float) $bail->caution_cie || $bail->compteur_cie_credit)
                        <li class="list-group-item small bg-light">
                            Compteurs : SODECI {{ $bail->compteur_sodeci_numero ?? '—' }} (caution {{ montant($bail->caution_sodeci) }}) ·
                            CIE {{ $bail->compteur_cie_numero ?? '—' }} (caution {{ montant($bail->caution_cie) }})
                            @if($bail->compteur_cie_credit)<br><i class="ti ti-bolt"></i> Compteur CIE pris à crédit ({{ montant($bail->compteur_cie_montant_credit) }}) : remboursable à la sortie sur justificatifs.@endif
                        </li>
                    @endif
                    @forelse($bail->etatsLieux as $etat)
                        <li class="list-group-item">
                            <div class="fw-medium">{{ $etat->type === 'entree' ? 'Entrée' : 'Sortie' }} — {{ $etat->date->format('d/m/Y') }}</div>
                            @foreach($etat->pieces ?? [] as $piece)
                                <div class="small">{{ $piece['piece'] }} : {{ EtatLieux::ETATS[$piece['etat'] ?? ''] ?? '—' }} {{ $piece['commentaire'] ?? '' }}</div>
                            @endforeach
                            @if($etat->releves_compteurs)<div class="small text-muted">Compteurs : {{ $etat->releves_compteurs }}</div>@endif
                            @if($etat->compteurs)<div class="small text-muted">Index CIE : {{ $etat->compteurs['cie_index'] ?? '—' }} · SODECI : {{ $etat->compteurs['sodeci_index'] ?? '—' }}</div>@endif
                            @foreach($etat->reparations ?? [] as $r)
                                <div class="small text-danger"><i class="ti ti-tool"></i> {{ $r['libelle'] }} : {{ montant($r['montant']) }} ({{ ($r['a_charge'] ?? 'locataire') === 'locataire' ? 'locataire' : 'propriétaire' }})</div>
                            @endforeach
                            @if($etat->observations)<div class="small text-muted">{{ $etat->observations }}</div>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Aucun état des lieux.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Avis d'échéance et quittances</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Échéance</th><th>Libellé</th><th class="text-end">Montant</th><th class="text-end">Payé</th><th>Statut</th><th>Quittance</th></tr></thead>
                        <tbody>
                            @forelse($loyers as $loyer)
                                <tr class="{{ $loyer->estEnRetard() ? 'table-danger' : '' }}">
                                    <td>{{ $loyer->date_echeance->format('d/m/Y') }}</td>
                                    <td>{{ $loyer->libelle }} <span class="badge bg-light text-dark">{{ $loyer->nature->libelle() }}</span></td>
                                    <td class="text-end montant">{{ montant($loyer->montant) }}</td>
                                    <td class="text-end montant">{{ montant($loyer->montant_paye) }}</td>
                                    <td><x-statut :valeur="$loyer->statut" /></td>
                                    <td>
                                        @if($loyer->quittance_numero)
                                            <a href="{{ route('loyers.quittance', $loyer) }}" target="_blank" class="small"><i class="ti ti-file-type-pdf"></i> {{ $loyer->quittance_numero }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-vide message="Les avis d'échéance seront générés à l'activation du bail." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($bail->statut === StatutBail::Actif)
        <div class="modal fade" id="modalResiliation" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('baux.resilier', $bail) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Résiliation du bail</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.input name="date_resiliation" label="Date de fin effective" type="date" :value="today()" required col="col-md-6" />
                        <x-form.select name="motif" label="Motif" :options="$motifs" required col="col-md-6" />
                        <div class="col-12 small text-muted">Les loyers des périodes postérieures non réglés seront annulés. Les garanties (dépôt, cautions de compteurs)
                            sont ensuite liquidées dans le <strong>décompte de sortie</strong>, après l'état des lieux de sortie.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Résilier</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRevision" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('baux.reviser', $bail) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Révision du loyer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body">
                        <x-form.input name="loyer_mensuel" label="Nouveau loyer mensuel" type="number" step="0.01" min="0" required col="col-12"
                            :value="round((float) $bail->loyer_mensuel * (1 + (float) $bail->taux_revision / 100), 2)" :aide="'Loyer actuel : ' . montant($bail->loyer_mensuel)" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Réviser</button></div>
                </form>
            </div>
        </div>

    @endif

    @if($bail->statut !== StatutBail::Brouillon)
        <div class="modal fade" id="modalEtatLieux" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('baux.etatslieux.store', $bail) }}" class="modal-content" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">État des lieux</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="type" label="Type" :options="['entree' => 'Entrée', 'sortie' => 'Sortie']" required col="col-md-4" />
                        <x-form.input name="date" label="Date" type="date" :value="today()" required col="col-md-4" />
                        <x-form.input name="compteurs[cie_index]" label="Index compteur CIE" col="col-md-2" />
                        <x-form.input name="compteurs[sodeci_index]" label="Index SODECI" col="col-md-2" />
                        @foreach(['Séjour', 'Cuisine', 'Chambre 1', 'Chambre 2', 'Salle d\'eau', 'Extérieurs'] as $i => $piece)
                            <div class="col-md-4 mb-2"><input name="pieces[{{ $i }}][piece]" value="{{ $piece }}" class="form-control form-control-sm" aria-label="Pièce"></div>
                            <div class="col-md-3 mb-2">
                                <select name="pieces[{{ $i }}][etat]" class="form-select form-select-sm" aria-label="État">
                                    @foreach(EtatLieux::ETATS as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-5 mb-2"><input name="pieces[{{ $i }}][commentaire]" class="form-control form-control-sm" placeholder="Commentaire" aria-label="Commentaire"></div>
                        @endforeach
                        <div class="col-12 mt-2"><h6 class="text-uppercase text-muted small">Réparations à prévoir (état des lieux de sortie)</h6></div>
                        @for($i = 0; $i < 5; $i++)
                            <div class="col-md-6 mb-2"><input name="reparations[{{ $i }}][libelle]" class="form-control form-control-sm" placeholder="Réparation (ex. peinture séjour, serrure…)" aria-label="Réparation"></div>
                            <div class="col-md-3 mb-2"><input type="number" step="0.01" min="0" name="reparations[{{ $i }}][montant]" class="form-control form-control-sm text-end" placeholder="Montant" aria-label="Montant"></div>
                            <div class="col-md-3 mb-2"><select name="reparations[{{ $i }}][a_charge]" class="form-select form-select-sm" aria-label="À la charge de">
                                <option value="locataire">Locataire (retenue)</option><option value="proprietaire">Propriétaire (usure normale)</option></select></div>
                        @endfor
                        <x-form.textarea name="observations" label="Observations" rows="2" />
                        @if(app(\App\Support\AgenceCourante::class)->moduleActif('ged'))
                            <div class="col-12 mb-2"><label class="form-label" for="photos-edl">Photos / documents (GED)</label>
                                <input type="file" name="photos[]" id="photos-edl" class="form-control" multiple accept="image/*,application/pdf"></div>
                        @endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    @endif

    <x-ged :objet="$bail" titre="Documents du bail" categorie="CONTRAT" />
</x-page>
@endsection
