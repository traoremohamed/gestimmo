@extends('layouts.backLayout.designadmin')

@php
    use App\Models\Recouvrement;
    use App\Models\RegleRelance;
    use App\Models\PromessePaiement;
    $tiers = $dossier->tiers;
@endphp

@section('content')
<x-page :titre="'Recouvrement — ' . $tiers->nom_complet" module="Recouvrement" :retour="route('recouvrements.index')">
    <x-slot:badge><span class="badge bg-{{ $dossier->couleurStatut() }} fs-6">{{ $dossier->libelleStatut() }}</span></x-slot:badge>
    <x-slot:actions>
        @can('encaissements-create')
            <a href="{{ route('encaissements.create', ['tiers_id' => $tiers->id, 'activite' => $dossier->activite]) }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>
        @endcan
        @if($dossier->estOuvert())
            @can('recouvrements-relancer')
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalRelance"><i class="ti ti-send"></i> Relancer</button>
            @endcan
            @can('recouvrements-edit')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPromesse"><i class="ti ti-calendar-check"></i> Promesse de paiement</button>
            @endcan
        @endif
        @can('rendezvous-create')
            <a href="{{ route('rendezvous.create', ['tiers_id' => $tiers->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-calendar-plus"></i> Rendez-vous</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Montant dû" :valeur="montant($dossier->montant_du)" icone="ti-alarm" :detail="$dossier->nombre_impayes . ' impayé(s) — ' . Recouvrement::ACTIVITES[$dossier->activite]" />
        <x-stat libelle="Retard" :valeur="$dossier->joursRetard() . ' jours'" icone="ti-clock" :detail="'depuis le ' . $dossier->plus_ancienne_echeance?->format('d/m/Y')" />
        <x-stat libelle="Niveau de relance" :valeur="$dossier->niveau ?: 'Aucune'" icone="ti-stairs-up" :detail="$dossier->relances->count() . ' relance(s)'" />
        <x-stat libelle="Prochaine action" :valeur="$dossier->prochaine_action?->format('d/m/Y') ?? '—'" icone="ti-flag" />
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">Débiteur</div>
                <div class="card-body">
                    <a href="{{ route('tiers.show', $tiers) }}" class="fw-semibold">{{ $tiers->nom_complet }}</a>
                    <div class="small">{{ $tiers->contact }}</div>
                    <div class="small text-muted">{{ $tiers->adresse }}</div>
                    @if($tiers->telephone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $tiers->telephone) }}" class="btn btn-sm btn-light mt-2"><i class="ti ti-phone"></i> Appeler</a>
                    @endif
                </div>
            </div>

            @can('recouvrements-edit')
                <form method="POST" action="{{ route('recouvrements.traiter', $dossier) }}" class="card mb-4">
                    @csrf
                    <div class="card-header">Suivi du dossier</div>
                    <div class="card-body row">
                        <x-form.select name="statut" label="Statut" :options="collect(Recouvrement::STATUTS)->except(['solde', 'promesse'])->map(fn ($s) => $s[0])" :value="$dossier->statut === 'promesse' ? 'amiable' : $dossier->statut" required col="col-12" />
                        <x-form.select name="gestionnaire_id" label="Gestionnaire" :options="$gestionnaires" :value="$dossier->gestionnaire_id" col="col-12" />
                        <x-form.input name="prochaine_action" label="Prochaine action le" type="date" :value="$dossier->prochaine_action" col="col-12" />
                        <x-form.textarea name="commentaire" label="Note de suivi (ajoutée à l'historique)" rows="2" />
                        @if($dossier->commentaire)<div class="col-12 small text-muted" style="white-space: pre-line">{{ $dossier->commentaire }}</div>@endif
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-sm btn-primary">Enregistrer</button></div>
                </form>
            @endcan
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Impayés échus</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Objet</th><th>Échéance</th><th class="text-end">Reste dû</th></tr></thead>
                        <tbody>
                            @forelse($dettes as $d)
                                @php($date = $d->date_echeance ?? $d->appel?->date_echeance ?? $d->date_depart)
                                <tr><td>{{ $d->libelleAffectation() }}</td><td>{{ date_fr($date) }} <span class="small text-muted">({{ (int) \Illuminate\Support\Carbon::parse($date)->diffInDays(today()) }} j)</span></td>
                                    <td class="text-end montant">{{ montant($d->resteAPayer()) }}</td></tr>
                            @empty
                                <tr><td colspan="3"><x-vide message="Plus aucun impayé : le dossier sera soldé à la prochaine actualisation." icone="ti-mood-happy" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Historique des relances</div>
                <ul class="list-group list-group-flush">
                    @forelse($dossier->relances->sortByDesc('created_at') as $r)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><strong>{{ $r->created_at->format('d/m/Y H:i') }}</strong> — niveau {{ $r->niveau }} · {{ RegleRelance::CANAUX[$r->canal] ?? $r->canal }}
                                    {{ $r->automatique ? '(automatique)' : '— ' . $r->auteur?->nom_complet }}</span>
                                <span>
                                    <span class="badge bg-{{ ['envoyee' => 'success', 'echec' => 'danger', 'a_imprimer' => 'warning text-dark'][$r->statut] ?? 'secondary' }}">{{ ['envoyee' => 'Envoyée', 'echec' => 'Non envoyée', 'a_imprimer' => 'À imprimer', 'realisee' => 'Réalisée'][$r->statut] ?? $r->statut }}</span>
                                    @if(in_array($r->canal, ['courrier', 'email'], true))
                                        <a href="{{ route('recouvrements.lettre', [$dossier, $r]) }}" target="_blank" class="btn btn-sm btn-light" title="Lettre PDF"><i class="ti ti-printer"></i></a>
                                    @endif
                                </span>
                            </div>
                            <div class="small text-muted text-truncate">{{ $r->objet }} — {{ \Illuminate\Support\Str::limit($r->message, 160) }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Aucune relance.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-4">
                <div class="card-header">Promesses de paiement</div>
                <ul class="list-group list-group-flush small">
                    @forelse($dossier->promesses->sortByDesc('date_promesse') as $p)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ montant($p->montant) }} pour le {{ $p->date_promesse->format('d/m/Y') }} {{ $p->commentaire ? '— ' . $p->commentaire : '' }}</span>
                            <span class="badge bg-{{ PromessePaiement::STATUTS[$p->statut][1] }}">{{ PromessePaiement::STATUTS[$p->statut][0] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucune promesse.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if($dossier->estOuvert())
        <div class="modal fade" id="modalRelance" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('recouvrements.relancer', $dossier) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Relancer {{ $tiers->nom_complet }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.select name="regle_id" label="Niveau" :options="$regles->mapWithKeys(fn ($r) => [$r->id => $r->niveau . ' — ' . $r->libelle . ' (J+' . $r->delai_jours . ')'])"
                            :value="$regles->firstWhere('niveau', '>', $dossier->niveau)?->id" col="col-md-7" />
                        <x-form.select name="canal" label="Canal" :options="RegleRelance::CANAUX" :value="$regles->firstWhere('niveau', '>', $dossier->niveau)?->canal ?? 'sms'" required col="col-md-5" />
                        <x-form.textarea name="message" label="Message (laisser vide pour utiliser le modèle du niveau)" :value="$apercu" rows="8"
                            aide="Les variables {nom}, {montant}, {retard}, {details}, {agence} sont remplacées à l'envoi." />
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary"><i class="ti ti-send"></i> Relancer</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalPromesse" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('recouvrements.promettre', $dossier) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Promesse de paiement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                    <div class="modal-body row">
                        <x-form.input name="date_promesse" label="Date promise" type="date" :value="today()->addWeek()" required col="col-md-6" />
                        <x-form.input name="montant" label="Montant promis" type="number" step="0.01" min="0" :value="$dossier->montant_du" required col="col-md-6" />
                        <x-form.textarea name="commentaire" label="Commentaire" rows="2" />
                        <p class="small text-muted mb-0">Les relances automatiques sont suspendues jusqu'à cette date ; la promesse est ensuite marquée tenue ou non tenue selon les encaissements reçus.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    @endif
</x-page>
@endsection
