@extends('layouts.backLayout.designadmin')

@php
    use App\Models\Referentiel;
    /** @var \App\Models\Bien $element */
    $bien = $element;
    $photos = $bien->medias->where('collection', 'photos');
    $documents = $bien->medias->whereIn('collection', ['plans', 'documents']);
    $unite = $bien->unite_superficie === 'ha' ? 'ha' : 'm²';
@endphp

@section('content')
<x-page :titre="$bien->reference . ' — ' . $bien->libelle" module="Patrimoine" :retour="route('biens.index')">
    <x-slot:badge><x-statut :valeur="$bien->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        @if($bien->estDisponible() && $bien->a_vendre)
            @can('reservations-create')
                <a href="{{ route('reservations.create', ['bien_id' => $bien->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-lock"></i> Réserver</a>
            @endcan
        @endif
        @if($bien->estDisponible() && $bien->a_louer)
            @can('baux-create')
                <a href="{{ route('baux.create', ['bien_id' => $bien->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-key"></i> Créer un bail</a>
            @endcan
        @endif
        @if($bien->meuble)
            @can('sejours-create')
                <a href="{{ route('sejours.create', ['bien_id' => $bien->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-bed"></i> Nouveau séjour</a>
            @endcan
        @endif
        @can('biens-edit')
            <a href="{{ route('biens.edit', $bien) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Caractéristiques</div>
                <div class="card-body">
                    <dl class="row fiche mb-0">
                        <div class="col-md-4"><dt>Type</dt><dd>{{ Referentiel::libelle('type_bien', $bien->type_bien) }} ({{ $bien->usage }})</dd></div>
                        <div class="col-md-4"><dt>Programme</dt><dd>{{ $bien->programme?->nom ?? '—' }}</dd></div>
                        <div class="col-md-4"><dt>Lotissement / immeuble</dt><dd>{{ $bien->lotissement?->nom ?? $bien->immeuble?->nom ?? '—' }}</dd></div>
                        <div class="col-md-4"><dt>Îlot / lot</dt><dd>{{ collect([$bien->ilot ? 'Îlot ' . $bien->ilot : null, $bien->numero_lot ? 'Lot ' . $bien->numero_lot : null])->filter()->implode(' — ') ?: '—' }}</dd></div>
                        <div class="col-md-4"><dt>Étage / porte</dt><dd>{{ collect([$bien->etage !== null ? 'Étage ' . $bien->etage : null, $bien->porte ? 'Porte ' . $bien->porte : null])->filter()->implode(' — ') ?: '—' }}</dd></div>
                        <div class="col-md-4"><dt>Superficie</dt><dd>{{ $bien->superficie ? number_format((float) $bien->superficie, 2, ',', ' ') . ' ' . $unite : '—' }}</dd></div>
                        <div class="col-md-12"><dt>Composition</dt>
                            <dd>
                                @foreach(['nb_pieces' => 'pièce(s)', 'nb_chambres' => 'chambre(s)', 'nb_salons' => 'salon(s)', 'nb_salles_bain' => 'salle(s) d\'eau', 'nb_toilettes' => 'toilette(s)', 'nb_cuisines' => 'cuisine(s)'] as $champ => $libelle)
                                    @if($bien->{$champ})<span class="badge bg-light text-dark border me-1">{{ $bien->{$champ} }} {{ $libelle }}</span>@endif
                                @endforeach
                                @foreach($bien->equipements ?? [] as $equipement)
                                    <span class="badge bg-primary-subtle text-primary me-1">{{ Referentiel::libelle('equipement', $equipement) }}</span>
                                @endforeach
                            </dd>
                        </div>
                        @if($bien->description)
                            <div class="col-12"><dt>Description</dt><dd class="text-pre-line">{{ $bien->description }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Photos</span>
                    @can('biens-edit')
                        <form method="POST" action="{{ route('biens.medias.store', $bien) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="collection" value="photos">
                            <input type="file" name="fichiers[]" multiple accept="image/*" class="form-control form-control-sm" required>
                            <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="ti ti-upload"></i> Ajouter</button>
                        </form>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @forelse($photos as $photo)
                            <div class="col-6 col-md-3 position-relative">
                                <img src="{{ $photo->url }}" alt="{{ $bien->libelle }}" class="img-fluid rounded" style="aspect-ratio: 4/3; object-fit: cover; width: 100%;">
                                @can('biens-edit')
                                    <div class="position-absolute top-0 end-0 m-1">
                                        <x-action :url="route('biens.medias.destroy', [$bien, $photo])" methode="DELETE" icone="ti-x" classe="btn btn-sm btn-light" confirmer="Supprimer cette photo ?" />
                                    </div>
                                @endcan
                            </div>
                        @empty
                            <div class="col-12"><x-vide message="Aucune photo." icone="ti-photo" /></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Plans et documents</span>
                    @can('biens-edit')
                        <form method="POST" action="{{ route('biens.medias.store', $bien) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <select name="collection" class="form-select form-select-sm" aria-label="Type de document">
                                <option value="plans">Plan</option>
                                <option value="documents">Document privé</option>
                            </select>
                            <input type="file" name="fichiers[]" multiple class="form-control form-control-sm" required>
                            <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="ti ti-upload"></i> Ajouter</button>
                        </form>
                    @endcan
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($documents as $doc)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ $doc->url }}" target="_blank" rel="noopener"><i class="ti ti-file"></i> {{ $doc->nom_original }}</a>
                            <span class="d-flex gap-2 align-items-center">
                                <span class="badge bg-light text-dark">{{ $doc->collection === 'plans' ? 'Plan' : 'Privé' }}</span>
                                @can('biens-edit')
                                    <x-action :url="route('biens.medias.destroy', [$bien, $doc])" methode="DELETE" icone="ti-trash" classe="btn btn-sm btn-light text-danger" confirmer="Supprimer ce document ?" />
                                @endcan
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item"><x-vide message="Aucun document." icone="ti-file" /></li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Commercialisation</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>À vendre</span>
                        <span>@if($bien->a_vendre)<strong class="montant">{{ montant($bien->prix_vente) }}</strong>@else — @endif</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>À louer</span>
                        <span>@if($bien->a_louer)<strong class="montant">{{ montant($bien->loyer_mensuel) }}</strong> /mois @else — @endif</span></li>
                    @if($bien->charges_mensuelles)
                        <li class="list-group-item d-flex justify-content-between"><span>Charges</span><span class="montant">{{ montant($bien->charges_mensuelles) }}</span></li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between"><span>Meublé</span>
                        <span>@if($bien->meuble)<strong class="montant">{{ montant($bien->prix_nuitee) }}</strong> /nuit @else — @endif</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Site vitrine</span><span>{{ $bien->publie ? 'Publié' : 'Non publié' }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Propriétaire</span>
                        <span>@if($bien->proprietaire)<a href="{{ route('tiers.show', $bien->proprietaire) }}">{{ $bien->proprietaire->nom_complet }}</a>@else Agence / promoteur @endif</span></li>
                    @if($bien->mandat)
                        <li class="list-group-item d-flex justify-content-between"><span>Mandat</span><span>{{ $bien->mandat->numero }} ({{ $bien->mandat->taux_commission }} %)</span></li>
                    @endif
                </ul>
            </div>

            <div class="card mb-4">
                <div class="card-header">Historique</div>
                <ul class="list-group list-group-flush small">
                    @foreach($bien->venteBiens as $ligne)
                        <li class="list-group-item">
                            <i class="ti ti-home-dollar text-primary"></i>
                            <a href="{{ route('ventes.show', $ligne->vente) }}">Vente {{ $ligne->vente->numero ?? 'brouillon' }}</a>
                            — {{ $ligne->vente->acquereur?->nom_complet }} <x-statut :valeur="$ligne->vente->statut" />
                        </li>
                    @endforeach
                    @foreach($bien->baux as $bail)
                        <li class="list-group-item">
                            <i class="ti ti-key text-primary"></i>
                            <a href="{{ route('baux.show', $bail) }}">Bail {{ $bail->numero ?? 'brouillon' }}</a>
                            — {{ $bail->locataire?->nom_complet }} <x-statut :valeur="$bail->statut" />
                        </li>
                    @endforeach
                    @foreach($bien->reservations as $reservation)
                        <li class="list-group-item">
                            <i class="ti ti-lock text-primary"></i> Réservation {{ $reservation->numero }} — {{ $reservation->tiers?->nom_complet }}
                            <x-statut :valeur="$reservation->statut" />
                        </li>
                    @endforeach
                    @if($bien->venteBiens->isEmpty() && $bien->baux->isEmpty() && $bien->reservations->isEmpty())
                        <li class="list-group-item text-muted">Aucun mouvement.</li>
                    @endif
                </ul>
            </div>

            @if($bien->latitude && $bien->longitude)
                <div class="card mb-4">
                    <div class="card-header">Localisation</div>
                    <div class="card-body p-0">
                        <iframe title="Carte" width="100%" height="220" style="border:0" loading="lazy"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bien->longitude - 0.005 }},{{ $bien->latitude - 0.005 }},{{ $bien->longitude + 0.005 }},{{ $bien->latitude + 0.005 }}&marker={{ $bien->latitude }},{{ $bien->longitude }}"></iframe>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <x-ged :objet="$bien" categorie="PHOTO" />
</x-page>
@endsection
