@extends('layouts.backLayout.designadmin')

@php
    use App\Models\DecompteSortie;
    use App\Models\EtatLieux;
    $bail = $decompte->bail;
    $modifiable = $decompte->estModifiable() && auth()->user()->can('baux-sortie');
    $credits = $decompte->lignes->where('sens', 'credit');
    $debits = $decompte->lignes->where('sens', 'debit');
    $piecesEntree = collect($entree?->pieces ?? [])->keyBy('piece');
@endphp

@section('content')
<x-page :titre="'Décompte de sortie ' . ($decompte->numero ?? '(brouillon)')" module="Gestion locative" :retour="route('baux.show', $bail)">
    <x-slot:badge><span class="badge text-bg-{{ $decompte->couleurStatut() }} fs-6">{{ $decompte->libelleStatut() }}</span></x-slot:badge>
    <x-attente-validation :objet="$decompte" />
    <x-slot:actions>
        <a href="{{ route('decomptes.pdf', $decompte) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="ti ti-file-type-pdf"></i> Décompte PDF signé</a>
        @if($modifiable)
            <x-action :url="route('decomptes.valider', $decompte)" libelle="Valider le décompte" icone="ti-check" classe="btn btn-sm btn-success"
                confirmer="Valider ? Les retenues seront imputées sur les garanties, le crédit compteur passé en dépense et le décompte figé." />
        @endif
        @if($decompte->statut === 'valide')
            @can('baux-restituer')
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalRestitution"><i class="ti ti-cash-banknote"></i> Restituer {{ montant($decompte->montant_a_rembourser) }}</button>
            @endcan
        @endif
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Garanties et crédits" :valeur="montant($decompte->total_credits)" icone="ti-shield-lock" detail="dus au locataire" />
        <x-stat libelle="Retenues" :valeur="montant($decompte->total_retenues)" icone="ti-tool" detail="impayés, réparations, factures" />
        <x-stat libelle="À restituer" :valeur="montant($decompte->montant_a_rembourser)" icone="ti-cash-banknote" :detail="$decompte->rembourse_le ? 'restitué le ' . $decompte->rembourse_le->format('d/m/Y') : ''" />
        <x-stat libelle="Reste dû par le locataire" :valeur="montant($decompte->reste_du)" icone="ti-alert-triangle" :detail="(float) $decompte->reste_du > 0 ? 'suivi en recouvrement' : ''" />
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <span>{{ $decompte->locataire->nom_complet }} — bail {{ $bail->numero }} — {{ $bail->bien->designation }}</span>
            <span class="small text-muted">Sortie le {{ $decompte->date_sortie->format('d/m/Y') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Ligne</th><th class="text-end">Dû au locataire</th><th class="text-end">Dû par le locataire</th><th>Justificatifs</th><th></th></tr></thead>
                <tbody>
                @foreach([$credits, $debits] as $groupe)
                    @foreach($groupe as $ligne)
                        @php($auto = in_array($ligne->type, ['depot_garantie', 'caution_sodeci', 'caution_cie', 'impaye'], true))
                        <tr>
                            <td>
                                @if($modifiable && !$auto)
                                    <input form="l-{{ $ligne->id }}" name="libelle" value="{{ $ligne->libelle }}" class="form-control form-control-sm" aria-label="Libellé">
                                @else
                                    {{ $ligne->libelle }}
                                @endif
                                <div class="small text-muted">{{ DecompteSortie::TYPES[$ligne->type][0] }}{{ $auto ? ' — calculé par l\'application' : '' }}</div>
                            </td>
                            @foreach(['credit', 'debit'] as $sens)
                                <td class="text-end" style="width: 16%">
                                    @if($ligne->sens === $sens)
                                        @if($modifiable && !$auto)
                                            <input form="l-{{ $ligne->id }}" type="number" step="0.01" min="0" name="montant" value="{{ (float) $ligne->montant }}" class="form-control form-control-sm text-end" aria-label="Montant">
                                        @else
                                            <span class="montant">{{ montant($ligne->montant) }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td class="small">
                                @foreach($ligne->justificatifs as $j)
                                    <a href="{{ route('ged.show', $j) }}" class="d-block"><i class="ti {{ $j->icone() }}"></i> {{ \Illuminate\Support\Str::limit($j->nom_original, 30) }}</a>
                                @endforeach
                                @if($ligne->exigeJustificatif() && $ligne->justificatifs->isEmpty())
                                    <span class="text-danger"><i class="ti ti-alert-circle"></i> justificatif requis</span>
                                @endif
                                @if(auth()->user()->can('baux-sortie') && app(\App\Support\AgenceCourante::class)->moduleActif('ged'))
                                    <input form="l-{{ $ligne->id }}" type="file" name="justificatif" class="form-control form-control-sm mt-1" aria-label="Ajouter un justificatif">
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('baux-sortie')
                                    <form method="POST" action="{{ route('decomptes.lignes.update', [$decompte, $ligne]) }}" id="l-{{ $ligne->id }}" enctype="multipart/form-data" class="d-inline">
                                        @csrf<button class="btn btn-sm btn-light" title="Enregistrer"><i class="ti ti-device-floppy"></i></button></form>
                                    @if($modifiable && !in_array($ligne->type, ['depot_garantie', 'caution_sodeci', 'caution_cie'], true))
                                        <form method="POST" action="{{ route('decomptes.lignes.destroy', [$decompte, $ligne]) }}" class="d-inline" data-confirm="Supprimer cette ligne ?">
                                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="ti ti-trash"></i></button></form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold"><td>Totaux</td><td class="text-end montant">{{ montant($decompte->total_credits) }}</td><td class="text-end montant">{{ montant($decompte->total_retenues) }}</td><td colspan="2"></td></tr>
                    <tr class="fw-bold {{ (float) $decompte->reste_du > 0 ? 'table-danger' : 'table-success' }}">
                        <td>{{ (float) $decompte->reste_du > 0 ? 'Reste dû par le locataire' : 'Solde à restituer au locataire' }}</td>
                        <td colspan="2" class="text-end montant">{{ montant((float) $decompte->reste_du > 0 ? $decompte->reste_du : $decompte->montant_a_rembourser) }}</td><td colspan="2"></td></tr>
                </tfoot>
            </table>
        </div>
        @if($modifiable)
            <div class="card-body border-top">
                <form method="POST" action="{{ route('decomptes.lignes.store', $decompte) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <x-form.select name="type" label="Ajouter une ligne" :options="$typesAjout" required col="col-md-3" />
                    <x-form.input name="libelle" label="Libellé" required col="col-md-4" />
                    <x-form.input name="montant" label="Montant" type="number" step="0.01" min="0" required col="col-md-2" />
                    @if(app(\App\Support\AgenceCourante::class)->moduleActif('ged'))<x-form.input name="justificatif" label="Justificatif" type="file" col="col-md-2" />@endif
                    <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-plus"></i></button></div>
                </form>
                <p class="small text-muted mb-0">
                    <i class="ti ti-info-circle"></i> Compteur CIE à crédit : saisissez le total remboursé par le locataire (prélevé à chaque rechargement) et joignez les reçus.
                    Factures SODECI / CIE : laissez 0 si la dernière facture est réglée (joignez la preuve de paiement) ; sinon saisissez le montant dû, il sera déduit des cautions.
                </p>
            </div>
        @endif
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header">Comparatif des états des lieux</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Pièce</th><th>Entrée {{ $entree?->date?->format('d/m/Y') }}</th><th>Sortie {{ $sortie?->date?->format('d/m/Y') }}</th></tr></thead>
                        <tbody>
                        @forelse(collect($sortie?->pieces ?? [])->merge(collect($entree?->pieces ?? [])->whereNotIn('piece', collect($sortie?->pieces ?? [])->pluck('piece'))) as $p)
                            @php($e = $piecesEntree->get($p['piece']))
                            @php($degrade = $e && $sortie && array_search($p['etat'] ?? '', array_keys(EtatLieux::ETATS)) > array_search($e['etat'] ?? '', array_keys(EtatLieux::ETATS)))
                            <tr class="{{ $degrade ? 'table-warning' : '' }}">
                                <td>{{ $p['piece'] }}</td>
                                <td class="small">{{ EtatLieux::ETATS[$e['etat'] ?? ''] ?? '—' }} {{ $e['commentaire'] ?? '' }}</td>
                                <td class="small">{{ $sortie ? (EtatLieux::ETATS[$p['etat'] ?? ''] ?? '—') . ' ' . ($p['commentaire'] ?? '') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="small text-muted">Aucun état des lieux : enregistrez l'état des lieux de sortie depuis le bail.</td></tr>
                        @endforelse
                        <tr class="small"><td>Index CIE / SODECI</td>
                            <td>{{ $entree?->compteurs['cie_index'] ?? '—' }} / {{ $entree?->compteurs['sodeci_index'] ?? '—' }}</td>
                            <td>{{ $sortie?->compteurs['cie_index'] ?? '—' }} / {{ $sortie?->compteurs['sodeci_index'] ?? '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            @if($decompte->encaissement)
                <div class="alert alert-light border small"><i class="ti ti-arrows-exchange"></i> Compensation sur les garanties :
                    <a href="{{ route('encaissements.show', $decompte->encaissement) }}">{{ $decompte->encaissement->numero }}</a> ({{ montant($decompte->montant_compense) }}).</div>
            @endif
            @if($decompte->depense)
                <div class="alert alert-light border small"><i class="ti ti-cash-banknote"></i> Restitution : dépense {{ $decompte->depense->numero }} du {{ $decompte->rembourse_le?->format('d/m/Y') }}
                    ({{ \App\Models\Referentiel::libelle('mode_paiement', $decompte->depense->mode_paiement) }} {{ $decompte->depense->reference }}).</div>
            @endif
            <x-ged :objet="$decompte" titre="Documents du décompte" categorie="DECOMPTE" />
        </div>
    </div>

    @if($decompte->statut === 'valide')
        <div class="modal fade" id="modalRestitution" tabindex="-1" aria-labelledby="modalRestitution-titre" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('decomptes.restituer', $decompte) }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title" id="modalRestitution-titre">Restitution de {{ montant($decompte->montant_a_rembourser) }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="mode_paiement" label="Mode" :options="$modes" required col="col-md-6" />
                        <x-form.input name="date" label="Date" type="date" :value="today()" required col="col-md-6" />
                        <x-form.input name="reference" label="Référence (chèque, virement, transaction)" col="col-12" />
                        <x-form.input name="preuve" label="Preuve de paiement (reçu signé, capture…)" type="file" :required="(bool) parametre('sortie.preuve_obligatoire')" col="col-12" />
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary">Enregistrer et notifier le locataire</button></div>
                </form>
            </div>
        </div>
    @endif
</x-page>
@endsection
