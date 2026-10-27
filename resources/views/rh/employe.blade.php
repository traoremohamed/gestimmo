@extends('layouts.backLayout.designadmin')

@php
    use App\Models\Referentiel;
    use App\Models\Rh\Absence;
    use App\Models\Rh\Bulletin;
    use App\Models\Rh\Contrat;
    use App\Models\Rh\Employe;
    use App\Models\Rh\Historique;
    $contrat = $employe->contrats->firstWhere('statut', 'actif');
@endphp

@section('content')
<x-page :titre="$employe->nom_complet" module="RH & personnel" :retour="route('employes.index')">
    <x-slot:badge><span class="badge bg-{{ Employe::STATUTS[$employe->statut][1] }} fs-6">{{ Employe::STATUTS[$employe->statut][0] }}</span></x-slot:badge>
    <x-slot:actions>
        @can('employes-edit')
            @if($employe->statut !== 'sorti')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalContrat"><i class="ti ti-file-plus"></i> Nouveau contrat / avenant</button>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEvenement"><i class="ti ti-timeline-event-plus"></i> Événement de carrière</button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalSortie"><i class="ti ti-door-exit"></i> Sortie</button>
            @endif
            <a href="{{ route('employes.edit', $employe) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Salaire de base" :valeur="$contrat ? montant($contrat->salaire_base) : '—'" icone="ti-cash" :detail="$contrat ? Contrat::TYPES[$contrat->type_contrat] . ' — ' . Contrat::MODES[$contrat->mode_remuneration] : 'Aucun contrat actif'" />
        <x-stat libelle="Ancienneté" :valeur="$employe->ancienneteAnnees() . ' an(s)'" icone="ti-hourglass" :detail="'depuis le ' . $employe->date_embauche->format('d/m/Y')" />
        <x-stat libelle="Solde de congés" :valeur="(float) $employe->solde_conges . ' jour(s)'" icone="ti-beach" />
        <x-stat libelle="Avances en cours" :valeur="montant($employe->avances->where('statut', 'en_cours')->sum(fn ($a) => $a->reste()))" icone="ti-coin" />
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Matricule</dt><dd>{{ $employe->matricule }}</dd>
                        <dt>Poste / département</dt><dd>{{ Referentiel::libelle('poste', $employe->poste) ?? '—' }} / {{ Referentiel::libelle('departement', $employe->departement) ?? '—' }}</dd>
                        <dt>Contact</dt><dd>{{ collect([$employe->telephone, $employe->email])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt>Naissance</dt><dd>{{ date_fr($employe->date_naissance) ?: '—' }} {{ $employe->lieu_naissance ? 'à ' . $employe->lieu_naissance : '' }}</dd>
                        <dt>Situation</dt><dd>{{ Employe::SITUATIONS[$employe->situation_familiale] ?? '—' }}, {{ $employe->nombre_enfants }} enfant(s) — {{ (float) $employe->nombre_parts }} part(s)</dd>
                        <dt>CNPS</dt><dd>{{ $employe->numero_cnps ?: '—' }}</dd>
                        <dt>Banque</dt><dd>{{ collect([$employe->banque, $employe->rib])->filter()->implode(' — ') ?: '—' }}</dd>
                        <dt>Compte applicatif</dt><dd>{{ $employe->compte?->login_users ?? 'Aucun' }}</dd>
                        @if($employe->date_sortie)<dt>Sortie</dt><dd>{{ $employe->date_sortie->format('d/m/Y') }} — {{ $employe->motif_sortie }}</dd>@endif
                    </dl>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">Contrats</div>
                <ul class="list-group list-group-flush small">
                    @foreach($employe->contrats->sortByDesc('date_debut') as $c)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ Contrat::TYPES[$c->type_contrat] }} du {{ $c->date_debut->format('d/m/Y') }}{{ $c->date_fin ? ' au ' . $c->date_fin->format('d/m/Y') : '' }}</span>
                            <span><span class="montant me-1">{{ montant($c->salaire_base) }}</span><span class="badge bg-{{ $c->statut === 'actif' ? 'success' : 'secondary' }}">{{ $c->statut }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Carrière</div>
                <ul class="list-group list-group-flush small">
                    @forelse($employe->historique as $h)
                        <li class="list-group-item">
                            <strong>{{ $h->date_effet->format('d/m/Y') }}</strong> — <span class="badge bg-light text-dark">{{ Historique::TYPES[$h->type] ?? $h->type }}</span> {{ $h->libelle }}
                            @if(!empty($h->details['note']))<span class="text-warning">{{ str_repeat('★', $h->details['note']) }}</span>@endif
                            @if(!empty($h->details['commentaire']))<div class="text-muted">{{ $h->details['commentaire'] }}</div>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucun événement.</li>
                    @endforelse
                </ul>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">Congés & absences @can('absences-index')<a href="{{ route('absences.index', ['employe_id' => $employe->id]) }}" class="small">Tout voir</a>@endcan</div>
                        <ul class="list-group list-group-flush small">
                            @forelse($employe->absences->sortByDesc('date_debut')->take(8) as $a)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ Referentiel::libelle('type_absence', $a->type) }} — {{ $a->date_debut->format('d/m') }} au {{ $a->date_fin->format('d/m/Y') }} ({{ (float) $a->nombre_jours }} j)</span>
                                    <span class="badge bg-{{ Absence::STATUTS[$a->statut][1] }}">{{ Absence::STATUTS[$a->statut][0] }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Aucune absence.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">Bulletins de paie</div>
                        <ul class="list-group list-group-flush small">
                            @forelse($employe->bulletins->sortByDesc('periode.date_debut')->take(8) as $b)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $b->periode->libelle }}</span>
                                    <span><span class="montant me-1">{{ montant($b->net_a_payer) }}</span>
                                        <a href="{{ route('paie.bulletin', [$b->periode, $b]) }}" target="_blank" title="Bulletin PDF"><i class="ti ti-file-type-pdf"></i></a></span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Aucun bulletin.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('employes-edit')
        <div class="modal fade" id="modalContrat" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('employes.contrat', $employe) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Nouveau contrat / avenant</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="motif" label="Motif" :options="['augmentation' => 'Augmentation', 'promotion' => 'Promotion', 'mutation' => 'Mutation / renouvellement']" required col="col-md-4" />
                        <x-form.select name="type_contrat" label="Type" :options="Contrat::TYPES" :value="$contrat?->type_contrat ?? 'CDI'" required col="col-md-4" />
                        <x-form.input name="date_debut" label="Prise d'effet" type="date" :value="today()->startOfMonth()" required col="col-md-4" />
                        <x-form.select name="mode_remuneration" label="Rémunération" :options="Contrat::MODES" :value="$contrat?->mode_remuneration ?? 'FIXE'" required col="col-md-6" />
                        <x-form.input name="date_fin" label="Fin (CDD)" type="date" col="col-md-6" />
                        <x-form.input name="salaire_base" label="Salaire de base" type="number" step="0.01" :value="$contrat?->salaire_base" col="col-md-3" />
                        <x-form.input name="sursalaire" label="Sursalaire" type="number" step="0.01" :value="$contrat?->sursalaire" col="col-md-3" />
                        <x-form.input name="taux_journalier" label="Taux journalier" type="number" step="0.01" :value="$contrat?->taux_journalier" col="col-md-3" />
                        <x-form.input name="taux_commission" label="Commission (%)" type="number" step="0.001" :value="$contrat?->taux_commission" col="col-md-3" />
                        <x-form.input name="categorie" label="Catégorie" :value="$contrat?->categorie" col="col-md-6" />
                        <x-form.select name="mode_paiement" label="Paiement" :options="Referentiel::options('mode_paiement')" :value="$contrat?->mode_paiement ?? 'VIREMENT'" required col="col-md-6" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="modalEvenement" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('employes.evenement', $employe) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Événement de carrière</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="type" label="Type" :options="['formation' => 'Formation', 'evaluation' => 'Évaluation annuelle', 'sanction' => 'Sanction / avertissement', 'promotion' => 'Promotion', 'mutation' => 'Mutation']" required col="col-md-6" />
                        <x-form.input name="date_effet" label="Date" type="date" :value="today()" required col="col-md-6" />
                        <x-form.input name="libelle" label="Intitulé" required col="col-12" />
                        <x-form.select name="note" label="Note (évaluation)" :options="[5 => '★★★★★ Excellent', 4 => '★★★★ Très bien', 3 => '★★★ Satisfaisant', 2 => '★★ À améliorer', 1 => '★ Insuffisant']" col="col-12" />
                        <x-form.textarea name="commentaire" label="Commentaire" rows="3" />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Ajouter</button></div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="modalSortie" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('employes.sortie', $employe) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Sortie de l'effectif</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.input name="date_sortie" label="Date de sortie" type="date" :value="today()" required col="col-md-6" />
                        <x-form.select name="motif_sortie" label="Motif" :options="['Démission' => 'Démission', 'Licenciement' => 'Licenciement', 'Fin de contrat' => 'Fin de contrat', 'Retraite' => 'Retraite', 'Décès' => 'Décès', 'Autre' => 'Autre']" required col="col-md-6" />
                        <p class="small text-muted">Le contrat est clos ; la paie du mois de sortie est calculée au prorata.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Enregistrer la sortie</button></div>
                </form>
            </div>
        </div>
    @endcan
    <x-ged :objet="$employe" categorie="CONTRAT" />
</x-page>
@endsection
