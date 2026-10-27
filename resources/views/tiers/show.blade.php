@extends('layouts.backLayout.designadmin')

@php
    use App\Enums\RoleTiers;
    use App\Models\Referentiel;
    $totalDu = collect($dettes)->reduce(fn ($s, $d) => $s + (float) $d->resteAPayer(), 0);
@endphp

@section('content')
<x-page :titre="$tiers->nom_complet" module="Tiers & CRM" :retour="route('tiers.index')">
    <x-slot:badge>
        @foreach($tiers->roles() as $role)
            <span class="badge bg-{{ RoleTiers::from($role)->couleur() }} fs-7">{{ RoleTiers::from($role)->libelle() }}</span>
        @endforeach
    </x-slot:badge>
    <x-slot:actions>
        @can('encaissements-create')
            @if($totalDu > 0)
                <a href="{{ route('encaissements.create', ['tiers_id' => $tiers->id]) }}" class="btn btn-sm btn-success"><i class="ti ti-cash"></i> Encaisser</a>
            @endif
        @endcan
        @can('rendezvous-create')
            <a href="{{ route('rendezvous.create', ['tiers_id' => $tiers->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-calendar-plus"></i> Rendez-vous</a>
        @endcan
        @can('ventes-create')
            <a href="{{ route('ventes.create', ['acquereur_id' => $tiers->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-home-dollar"></i> Contrat de vente</a>
        @endcan
        @can('tiers-edit')
            @if(!$tiers->user_id && $tiers->email)
                <x-action :url="route('tiers.compte', $tiers)" libelle="Ouvrir l'espace client" icone="ti-user-plus" confirmer="Créer un compte d'accès et envoyer les identifiants par e-mail ?" />
            @endif
            <a href="{{ route('tiers.edit', $tiers) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Code</dt><dd>{{ $tiers->code }}</dd>
                        <dt>Contact</dt><dd>{{ $tiers->contact ?: '—' }}</dd>
                        <dt>Adresse</dt><dd>{{ collect([$tiers->adresse, $tiers->ville, $tiers->pays?->libelle])->filter()->implode(', ') ?: '—' }}</dd>
                        @if($tiers->type_personne === 'physique')
                            <dt>Naissance</dt><dd>{{ date_fr($tiers->date_naissance) ?: '—' }} {{ $tiers->lieu_naissance ? 'à ' . $tiers->lieu_naissance : '' }}</dd>
                            <dt>Nationalité</dt><dd>{{ $tiers->nationalite?->nationalite ?? '—' }}</dd>
                            <dt>Situation</dt><dd>{{ Referentiel::libelle('situation_matrimoniale', $tiers->situation_matrimoniale) ?? '—' }}</dd>
                            <dt>Profession</dt><dd>{{ $tiers->profession ?: '—' }} {{ $tiers->employeur ? '(' . $tiers->employeur . ')' : '' }}</dd>
                        @else
                            <dt>RCCM / CC</dt><dd>{{ $tiers->rccm ?: '—' }} / {{ $tiers->compte_contribuable ?: '—' }}</dd>
                            <dt>Représentant</dt><dd>{{ $tiers->representant_legal ?: '—' }}</dd>
                        @endif
                        <dt>Canal / commercial</dt><dd>{{ Referentiel::libelle('canal_acquisition', $tiers->canal_acquisition) ?? '—' }} / {{ $tiers->commercial?->nom_complet ?? '—' }}</dd>
                        <dt>Espace client</dt><dd>{{ $tiers->compte ? 'Ouvert (' . $tiers->compte->login_users . ')' : 'Non ouvert' }}</dd>
                        @if($tiers->notes)<dt>Notes</dt><dd>{{ $tiers->notes }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Pièces d'identité</div>
                <ul class="list-group list-group-flush">
                    @forelse($tiers->pieces as $piece)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium">{{ Referentiel::libelle('type_piece_identite', $piece->type_piece) }} n° {{ $piece->numero }}</div>
                                <div class="small {{ $piece->estExpiree() ? 'text-danger' : 'text-muted' }}">
                                    {{ $piece->date_expiration ? 'Expire le ' . $piece->date_expiration->format('d/m/Y') : 'Sans expiration' }}
                                </div>
                            </div>
                            <span class="d-flex gap-1">
                                @if($piece->fichier)
                                    <a href="{{ route('tiers.pieces.show', [$tiers, $piece]) }}" class="btn btn-sm btn-light" target="_blank" title="Voir"><i class="ti ti-file"></i></a>
                                @endif
                                @can('tiers-edit')
                                    <x-action :url="route('tiers.pieces.destroy', [$tiers, $piece])" methode="DELETE" icone="ti-trash" classe="btn btn-sm btn-light text-danger" confirmer="Supprimer cette pièce ?" />
                                @endcan
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Aucune pièce enregistrée.</li>
                    @endforelse
                </ul>
                @can('tiers-edit')
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('tiers.pieces.store', $tiers) }}" enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <x-form.select name="type_piece" :options="Referentiel::options('type_piece_identite')" required col="col-6" />
                            <x-form.input name="numero" placeholder="Numéro" required col="col-6" />
                            <x-form.input name="date_delivrance" type="date" label="Délivrée le" col="col-6" />
                            <x-form.input name="date_expiration" type="date" label="Expire le" col="col-6" />
                            <div class="col-12 mb-2"><input type="file" name="fichier" class="form-control form-control-sm" accept=".pdf,image/*" aria-label="Fichier"></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100"><i class="ti ti-plus"></i> Ajouter la pièce</button></div>
                        </form>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row">
                <x-stat libelle="Reste à payer" :valeur="montant($totalDu)" icone="ti-alert-circle" col="col-md-4" />
                <x-stat libelle="Total encaissé" :valeur="montant($tiers->encaissements()->valides()->sum('montant'))" icone="ti-cash" col="col-md-4" />
                <x-stat libelle="Contrats" :valeur="$tiers->ventes->count() + $tiers->baux->count()" icone="ti-file-text" col="col-md-4" />
            </div>

            @if(count($dettes))
                <div class="card mb-4">
                    <div class="card-header">Sommes dues</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Libellé</th><th>Échéance</th><th class="text-end">Montant</th><th class="text-end">Reste</th><th>Statut</th></tr></thead>
                            <tbody>
                                @foreach($dettes as $dette)
                                    <tr>
                                        <td>{{ $dette->libelleAffectation() }}</td>
                                        <td>{{ date_fr($dette->date_echeance ?? $dette->appel?->date_echeance ?? $dette->date_arrivee ?? null) }}</td>
                                        <td class="text-end montant">{{ montant($dette->montantDu()) }}</td>
                                        <td class="text-end montant fw-medium">{{ montant($dette->resteAPayer()) }}</td>
                                        <td>@if($dette->statut instanceof \App\Enums\StatutPaiement)<x-statut :valeur="$dette->statut" />@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">Contrats et biens</div>
                <ul class="list-group list-group-flush">
                    @foreach($tiers->ventes as $vente)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="ti ti-home-dollar text-primary"></i> <a href="{{ route('ventes.show', $vente) }}">Vente {{ $vente->numero ?? 'brouillon' }}</a>
                                — {{ $vente->biens->pluck('reference')->implode(', ') }}</span>
                            <span><span class="montant me-2">{{ montant($vente->montant_net) }}</span><x-statut :valeur="$vente->statut" /></span>
                        </li>
                    @endforeach
                    @foreach($tiers->baux as $bail)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="ti ti-key text-primary"></i> <a href="{{ route('baux.show', $bail) }}">Bail {{ $bail->numero ?? 'brouillon' }}</a> — {{ $bail->bien?->designation }}</span>
                            <span><span class="montant me-2">{{ montant($bail->loyer_mensuel) }}/mois</span><x-statut :valeur="$bail->statut" /></span>
                        </li>
                    @endforeach
                    @foreach($tiers->mandats as $mandat)
                        <li class="list-group-item"><i class="ti ti-file-certificate text-primary"></i> Mandat de gestion {{ $mandat->numero }} — commission {{ $mandat->taux_commission }} %</li>
                    @endforeach
                    @foreach($tiers->biensEnGestion as $bien)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="ti ti-building text-primary"></i> <a href="{{ route('biens.show', $bien) }}">{{ $bien->designation }}</a> (en gestion)</span>
                            <x-statut :valeur="$bien->statut" />
                        </li>
                    @endforeach
                    @foreach($tiers->lotsCopropriete as $lot)
                        <li class="list-group-item"><i class="ti ti-building-community text-primary"></i>
                            <a href="{{ route('coproprietes.show', $lot->copropriete) }}">{{ $lot->copropriete->nom }}</a> — lot {{ $lot->numero }} ({{ rtrim(rtrim($lot->tantiemes, '0'), '.') }} tantièmes)</li>
                    @endforeach
                    @foreach($tiers->sejours as $sejour)
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="ti ti-bed text-primary"></i> <a href="{{ route('sejours.show', $sejour) }}">Séjour {{ $sejour->numero }}</a> — {{ $sejour->bien?->reference }}
                                du {{ $sejour->date_arrivee->format('d/m/Y') }} au {{ $sejour->date_depart->format('d/m/Y') }}</span>
                            <x-statut :valeur="$sejour->statut" />
                        </li>
                    @endforeach
                    @if($tiers->ventes->isEmpty() && $tiers->baux->isEmpty() && $tiers->biensEnGestion->isEmpty() && $tiers->lotsCopropriete->isEmpty() && $tiers->sejours->isEmpty())
                        <li class="list-group-item text-muted">Aucun contrat.</li>
                    @endif
                </ul>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">Derniers encaissements</div>
                        <ul class="list-group list-group-flush small">
                            @forelse($encaissements as $encaissement)
                                <li class="list-group-item d-flex justify-content-between">
                                    <a href="{{ route('encaissements.show', $encaissement) }}">{{ $encaissement->numero }}</a>
                                    <span>{{ $encaissement->date_encaissement->format('d/m/Y') }}</span>
                                    <span class="montant {{ $encaissement->estAnnule() ? 'text-decoration-line-through text-muted' : '' }}">{{ montant($encaissement->montant) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Aucun encaissement.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">Rendez-vous</div>
                        <ul class="list-group list-group-flush small">
                            @forelse($rendezVous as $rdv)
                                <li class="list-group-item d-flex justify-content-between">
                                    <a href="{{ route('rendezvous.edit', $rdv) }}">{{ $rdv->debut->format('d/m/Y H:i') }}</a>
                                    <span>{{ Referentiel::libelle('objet_rdv', $rdv->objet) }}</span>
                                    <x-statut :valeur="$rdv->statut" />
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Aucun rendez-vous.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-ged :objet="$tiers" categorie="IDENTITE" />
</x-page>
@endsection
