@extends('layouts.backLayout.designadmin')

@php
    use App\Enums\StatutVente;
    use App\Models\Vente;
    use App\Models\VenteFrais;
    $brouillon = $vente->estModifiable();
    $paye = $vente->totalPaye();
@endphp

@section('content')
<x-page :titre="$vente->numero ? 'Contrat ' . $vente->numero : 'Nouveau contrat de vente (brouillon)'" module="Ventes" :retour="route('ventes.index')">
    <x-slot:badge><x-statut :valeur="$vente->statut" class="fs-6" /></x-slot:badge>
    <x-attente-validation :objet="$vente" />
    <x-slot:actions>
        @if($brouillon)
            @can('ventes-valider')
                @if($vente->lignes->isNotEmpty())
                    <x-action :url="route('ventes.valider', $vente)" libelle="Valider le contrat" icone="ti-circle-check" classe="btn btn-sm btn-success"
                        confirmer="Valider ce contrat ? L'échéancier sera généré et les biens marqués vendus. Le contrat ne sera plus modifiable." />
                @endif
            @endcan
            @can('ventes-create')
                <x-action :url="route('ventes.destroy', $vente)" methode="DELETE" libelle="Supprimer le brouillon" icone="ti-trash" classe="btn btn-sm btn-outline-danger" confirmer="Supprimer ce brouillon ?" />
            @endcan
        @else
            <a href="{{ route('ventes.contrat', $vente) }}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="ti ti-file-type-pdf"></i> Contrat PDF</a>
        @endif
        @if($vente->statut === StatutVente::EnCours)
            @can('encaissements-create')
                <a href="{{ route('encaissements.create', ['tiers_id' => $vente->acquereur_id, 'activite' => 'vente']) }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>
            @endcan
            @can('ventes-desister')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDesistement"><i class="ti ti-arrow-back-up"></i> Désistement</button>
            @endcan
        @endif
        @if($vente->statut === StatutVente::Desistee && !$vente->rembourse_le && (float) $vente->montant_a_rembourser > 0)
            @can('ventes-rembourser')
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalRemboursement"><i class="ti ti-cash-banknote"></i> Rembourser</button>
            @endcan
        @endif
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Prix net" :valeur="montant($vente->montant_net)" icone="ti-home-dollar" :detail="(float) $vente->remise ? 'Remise : ' . montant($vente->remise) : null" />
        <x-stat libelle="Frais annexes" :valeur="montant($vente->montant_frais)" icone="ti-receipt" />
        <x-stat libelle="Encaissé" :valeur="montant($paye)" icone="ti-cash" :detail="$brouillon ? null : $vente->tauxRecouvrement() . ' % du total'" />
        <x-stat libelle="Reste à payer" :valeur="montant($brouillon ? (float) $vente->montant_net + (float) $vente->montant_frais : $vente->resteAPayer())" icone="ti-alert-circle" />
    </div>

    @if($vente->statut === StatutVente::Desistee)
        <div class="alert alert-danger">
            <strong>Désistement du {{ $vente->date_desistement?->format('d/m/Y') }}</strong> —
            {{ \App\Models\Referentiel::libelle('motif_desistement', $vente->motif_desistement) }}.
            Retenue : {{ montant($vente->montant_retenu) }} · À rembourser : <strong>{{ montant($vente->montant_a_rembourser) }}</strong>
            @if($vente->rembourse_le) · Remboursé le {{ $vente->rembourse_le->format('d/m/Y') }} @endif
            @if($vente->observation_desistement)<div class="small mt-1">{{ $vente->observation_desistement }}</div>@endif
        </div>
    @endif

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">Acquéreur</div>
                <div class="card-body">
                    <a href="{{ route('tiers.show', $vente->acquereur) }}" class="fw-semibold">{{ $vente->acquereur->nom_complet }}</a>
                    <div class="small text-muted">{{ $vente->acquereur->contact }}</div>
                    <div class="small text-muted">{{ $vente->acquereur->adresse }}</div>
                    <hr>
                    <dl class="fiche mb-0 small">
                        <dt>Commercial</dt><dd>{{ $vente->commercial?->nom_complet ?? '—' }}</dd>
                        @if($vente->valide_le)
                            <dt>Validé</dt><dd>le {{ $vente->valide_le->format('d/m/Y') }} par {{ $vente->validePar?->nom_complet }}</dd>
                        @endif
                        @if((float) $vente->montant_commission)
                            <dt>Commission</dt><dd>{{ montant($vente->montant_commission) }} ({{ $vente->taux_commission }} %)</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Conditions financières</div>
                @if($brouillon)
                    <form method="POST" action="{{ route('ventes.conditions', $vente) }}" class="card-body row">
                        @csrf @method('PUT')
                        <x-form.input name="date_contrat" label="Date du contrat" type="date" :value="$vente->date_contrat" required col="col-6" />
                        <x-form.select name="commercial_id" label="Commercial" :options="$commerciaux" :value="$vente->commercial_id" col="col-6" />
                        <x-form.select name="mode_financement" label="Financement" :options="Vente::MODES_FINANCEMENT" :value="$vente->mode_financement" required col="col-6" />
                        <x-form.input name="remise" label="Remise" type="number" step="0.01" min="0" :value="$vente->remise" col="col-6" />
                        <x-form.input name="taux_acompte" label="Acompte (%)" type="number" step="0.01" min="0" max="100" :value="$vente->taux_acompte" col="col-6" aide="Ou montant ci-contre" />
                        <x-form.input name="montant_acompte" label="Acompte (montant)" type="number" step="0.01" min="0" :value="(float) $vente->taux_acompte ? null : $vente->montant_acompte" col="col-6" />
                        <x-form.input name="nombre_echeances" label="Nombre d'échéances" type="number" min="1" :value="$vente->nombre_echeances" col="col-6" />
                        <x-form.select name="periodicite_mois" label="Périodicité" :options="[1 => 'Mensuelle', 2 => 'Bimestrielle', 3 => 'Trimestrielle', 6 => 'Semestrielle', 12 => 'Annuelle']" :value="$vente->periodicite_mois" col="col-6" />
                        <x-form.input name="date_premiere_echeance" label="1re échéance" type="date" :value="$vente->date_premiere_echeance" col="col-6" />
                        <x-form.input name="taux_commission" label="Commission (%)" type="number" step="0.01" min="0" max="100" :value="$vente->taux_commission" col="col-6" />
                        <x-form.textarea name="observation" label="Observation" :value="$vente->observation" rows="2" />
                        <div class="col-12"><button class="btn btn-primary w-100"><i class="ti ti-calculator"></i> Enregistrer et recalculer</button></div>
                    </form>
                @else
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Date du contrat</span><span>{{ $vente->date_contrat?->format('d/m/Y') }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Financement</span><span>{{ Vente::MODES_FINANCEMENT[$vente->mode_financement] }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Prix total</span><span class="montant">{{ montant($vente->prix_total) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Remise</span><span class="montant">{{ montant($vente->remise) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Acompte</span><span class="montant">{{ montant($vente->montant_acompte) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Échéances</span><span>{{ $vente->nombre_echeances }} × {{ $vente->periodicite_mois }} mois</span></li>
                    </ul>
                    @if($vente->observation)<div class="card-body small">{{ $vente->observation }}</div>@endif
                @endif
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Biens</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Bien</th><th>Localisation</th><th class="text-end">Superficie</th><th class="text-end">Prix</th>@if($brouillon)<th></th>@endif</tr></thead>
                        <tbody>
                            @forelse($vente->lignes as $ligne)
                                <tr>
                                    <td><a href="{{ route('biens.show', $ligne->bien) }}">{{ $ligne->bien->reference }}</a> — {{ $ligne->bien->libelle }}</td>
                                    <td class="small">{{ collect([$ligne->bien->ilot ? 'Îlot ' . $ligne->bien->ilot : null, $ligne->bien->numero_lot ? 'Lot ' . $ligne->bien->numero_lot : null])->filter()->implode(', ') }}</td>
                                    <td class="text-end">{{ $ligne->bien->superficie ? number_format((float) $ligne->bien->superficie, 0, ',', ' ') . ' m²' : '' }}</td>
                                    <td class="text-end montant">{{ montant($ligne->prix) }}</td>
                                    @if($brouillon)
                                        <td class="text-end"><x-action :url="route('ventes.biens.destroy', [$vente, $ligne->bien])" methode="DELETE" icone="ti-x" classe="btn btn-sm btn-light text-danger" titre="Retirer" /></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-vide message="Ajoutez le ou les biens vendus." icone="ti-home" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($brouillon)
                    <form method="POST" action="{{ route('ventes.biens.store', $vente) }}" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <div class="col-md-7">
                            <label class="form-label" for="bien_id">Ajouter un bien disponible (ou réservé par ce client)</label>
                            <select name="bien_id" id="bien_id" class="form-select select2" required>
                                <option value="">— Sélectionner —</option>
                                @foreach($biensDisponibles as $bien)
                                    <option value="{{ $bien->id }}">{{ $bien->designation }} — {{ montant($bien->prix_vente) }} @if($bien->statut->value === 'reserve')(réservé)@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="prix">Prix négocié</label>
                            <input type="number" step="0.01" min="0" name="prix" id="prix" class="form-control" placeholder="Prix catalogue">
                        </div>
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i> Ajouter</button></div>
                    </form>
                @endif
            </div>

            <div class="card mb-4">
                <div class="card-header">Frais annexes et documents</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Frais</th><th class="text-end">Montant</th><th class="text-end">Payé</th><th>Statut</th><th>Document</th>@if($brouillon)<th></th>@endif</tr></thead>
                        <tbody>
                            @forelse($vente->frais as $frais)
                                <tr>
                                    <td>{{ $frais->libelle }}</td>
                                    <td class="text-end montant">{{ montant($frais->montant) }}</td>
                                    <td class="text-end montant">{{ montant($frais->montant_paye) }}</td>
                                    <td><x-statut :valeur="$frais->statut" /></td>
                                    <td>
                                        @if($frais->document_requis)
                                            @if($brouillon)
                                                <span class="badge bg-light text-dark">{{ VenteFrais::STATUTS_DOCUMENT[$frais->statut_document] ?? 'Attendu' }}</span>
                                            @else
                                                <form method="POST" action="{{ route('ventes.frais.document', [$vente, $frais]) }}" enctype="multipart/form-data" class="d-flex gap-1">
                                                    @csrf
                                                    <select name="statut_document" class="form-select form-select-sm" aria-label="Statut du document">
                                                        @foreach(VenteFrais::STATUTS_DOCUMENT as $code => $libelle)
                                                            <option value="{{ $code }}" @selected($frais->statut_document === $code)>{{ $libelle }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="file" name="document" class="form-control form-control-sm" aria-label="Document">
                                                    <button class="btn btn-sm btn-light" title="Enregistrer"><i class="ti ti-device-floppy"></i></button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                    @if($brouillon)
                                        <td class="text-end"><x-action :url="route('ventes.frais.destroy', [$vente, $frais])" methode="DELETE" icone="ti-x" classe="btn btn-sm btn-light text-danger" titre="Retirer" /></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted small">Aucun frais.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($brouillon && $fraisTypes->isNotEmpty())
                    <form method="POST" action="{{ route('ventes.frais.store', $vente) }}" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <x-form.select name="frais_type_id" label="Ajouter un frais" :options="$fraisTypes->mapWithKeys(fn ($f) => [$f->id => $f->libelle . ' (' . ($f->mode_calcul === 'pourcentage' ? $f->montant . ' %' : montant($f->montant)) . ')'])" required col="col-md-7 mb-0" />
                        <x-form.input name="montant" label="Montant (si différent)" type="number" step="0.01" min="0" col="col-md-3 mb-0" />
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i> Ajouter</button></div>
                    </form>
                @endif
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ $brouillon ? 'Échéancier prévisionnel' : 'Échéancier' }}</span>
                    @if($brouillon)<span class="small text-muted">Généré à la validation</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>N°</th><th>Libellé</th><th>Date</th><th class="text-end">Montant</th>@unless($brouillon)<th class="text-end">Payé</th><th class="text-end">Reste</th><th>Statut</th>@endunless</tr></thead>
                        <tbody>
                            @if($brouillon)
                                @forelse($simulation as $ligne)
                                    <tr><td>{{ $ligne['numero'] }}</td><td>{{ $ligne['libelle'] }}</td><td>{{ $ligne['date_echeance']->format('d/m/Y') }}</td><td class="text-end montant">{{ montant($ligne['montant']) }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted small">Ajoutez un bien pour simuler l'échéancier.</td></tr>
                                @endforelse
                            @else
                                @foreach($vente->echeances->sortBy('numero') as $echeance)
                                    <tr class="{{ $echeance->estEnRetard() ? 'table-danger' : '' }}">
                                        <td>{{ $echeance->numero }}</td>
                                        <td>{{ $echeance->libelle }}</td>
                                        <td>{{ $echeance->date_echeance->format('d/m/Y') }}</td>
                                        <td class="text-end montant">{{ montant($echeance->montant) }}</td>
                                        <td class="text-end montant">{{ montant($echeance->montant_paye) }}</td>
                                        <td class="text-end montant">{{ montant($echeance->resteAPayer()) }}</td>
                                        <td><x-statut :valeur="$echeance->statut" /></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($vente->statut === StatutVente::EnCours)
        <div class="modal fade" id="modalDesistement" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('ventes.desister', $vente) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Désistement du client</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <p class="small">Sommes versées sur le prix : <strong>{{ montant($vente->totalPayePrix()) }}</strong>. Le solde restant dû sera annulé et les biens redeviendront disponibles.</p>
                        <x-form.select name="motif" label="Motif" :options="$motifs" required col="col-md-7" />
                        <x-form.input name="taux_retenue" label="Retenue (%)" type="number" step="0.01" min="0" max="100" :value="parametre('vente.taux_retenue_desistement')" required col="col-md-5" />
                        <x-form.textarea name="observation" label="Observation" rows="2" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Enregistrer le désistement</button></div>
                </form>
            </div>
        </div>
    @endif

    @if($vente->statut === StatutVente::Desistee && !$vente->rembourse_le)
        <div class="modal fade" id="modalRemboursement" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('ventes.rembourser', $vente) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Remboursement de {{ montant($vente->montant_a_rembourser) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="mode_paiement" label="Mode de paiement" :options="$modes" required col="col-md-6" />
                        <x-form.input name="reference" label="Référence (chèque, virement…)" col="col-md-6" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-warning">Enregistrer le remboursement</button></div>
                </form>
            </div>
        </div>
    @endif
    <x-ged :objet="$vente" categorie="CONTRAT" />
</x-page>
@endsection
