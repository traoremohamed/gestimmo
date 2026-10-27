@extends('vitrine.layout')

@section('titre', $bien->libelle . ' — ' . $agenceCourante->nom)
@section('description', \Illuminate\Support\Str::limit($bien->description, 160))

@section('contenu')
<div class="container py-5">
    <nav class="small mb-3"><a href="{{ route('vitrine.biens') }}"><i class="ti ti-arrow-left"></i> Tous les biens</a></nav>
    <div class="row g-4">
        <div class="col-lg-8">
            @if($bien->photos->isNotEmpty())
                <div id="galerie" class="carousel slide rounded overflow-hidden mb-4" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        @foreach($bien->photos as $photo)
                            <div class="carousel-item @if($loop->first) active @endif">
                                <img src="{{ $photo->url }}" class="d-block w-100" alt="{{ $bien->libelle }}" style="aspect-ratio: 16/10; object-fit: cover">
                            </div>
                        @endforeach
                    </div>
                    @if($bien->photos->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#galerie" data-bs-slide="prev" aria-label="Précédente"><span class="carousel-control-prev-icon"></span></button>
                        <button class="carousel-control-next" type="button" data-bs-target="#galerie" data-bs-slide="next" aria-label="Suivante"><span class="carousel-control-next-icon"></span></button>
                    @endif
                </div>
            @else
                <div class="placeholder-photo rounded mb-4"><i class="ti ti-home"></i></div>
            @endif

            <h1 class="h3">{{ $bien->libelle }}</h1>
            <div class="text-muted mb-3">{{ \App\Models\Referentiel::libelle('type_bien', $bien->type_bien) }} {{ $bien->programme ? '· ' . $bien->programme->nom : '' }} · Réf. {{ $bien->reference }}</div>

            <div class="d-flex flex-wrap gap-3 mb-4">
                @if($bien->superficie)<span class="badge bg-light text-dark fs-6 fw-normal"><i class="ti ti-ruler-measure"></i> {{ number_format((float) $bien->superficie, 0, ',', ' ') }} {{ $bien->unite_superficie === 'ha' ? 'ha' : 'm²' }}</span>@endif
                @if($bien->nb_pieces)<span class="badge bg-light text-dark fs-6 fw-normal"><i class="ti ti-layout"></i> {{ $bien->nb_pieces }} pièces</span>@endif
                @if($bien->nb_chambres)<span class="badge bg-light text-dark fs-6 fw-normal"><i class="ti ti-bed"></i> {{ $bien->nb_chambres }} chambres</span>@endif
                @if($bien->nb_salles_bain)<span class="badge bg-light text-dark fs-6 fw-normal"><i class="ti ti-bath"></i> {{ $bien->nb_salles_bain }} salles d'eau</span>@endif
                @foreach($bien->equipements ?? [] as $e)<span class="badge bg-light text-dark fs-6 fw-normal"><i class="ti ti-check"></i> {{ \App\Models\Referentiel::libelle('equipement', $e) }}</span>@endforeach
            </div>

            @if($bien->description)<div style="white-space: pre-line">{{ $bien->description }}</div>@endif

            @if($bien->latitude && $bien->longitude)
                <iframe title="Localisation" class="w-100 rounded mt-4" height="300" style="border: 0" loading="lazy"
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bien->longitude - 0.01 }},{{ $bien->latitude - 0.01 }},{{ $bien->longitude + 0.01 }},{{ $bien->latitude + 0.01 }}&marker={{ $bien->latitude }},{{ $bien->longitude }}"></iframe>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 90px">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-marque mb-1">
                        @if($bien->a_vendre && $bien->prix_vente){{ montant($bien->prix_vente) }}
                        @elseif($bien->a_louer && $bien->loyer_mensuel){{ montant($bien->loyer_mensuel) }} <small class="fs-6 text-muted fw-normal">/ mois</small>
                        @elseif($bien->meuble && $bien->prix_nuitee){{ montant($bien->prix_nuitee) }} <small class="fs-6 text-muted fw-normal">/ nuit</small>
                        @else Prix sur demande @endif
                    </div>
                    @if($bien->statut->value === 'reserve')<span class="badge bg-warning text-dark mb-2">Actuellement réservé</span>@endif
                    <h2 class="h6 mt-3">Demander une visite</h2>
                    <form method="POST" action="{{ route('vitrine.visite', $bien->reference) }}">
                        @csrf
                        <input type="text" name="site_web" class="piege" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="mb-2"><input name="nom" value="{{ old('nom') }}" class="form-control" placeholder="Nom et prénoms" required aria-label="Nom"></div>
                        <div class="mb-2"><input name="telephone" value="{{ old('telephone') }}" class="form-control" placeholder="Téléphone" aria-label="Téléphone"></div>
                        <div class="mb-2"><input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="E-mail" aria-label="E-mail"></div>
                        <div class="mb-2"><label class="form-label small mb-0" for="date_souhaitee">Date souhaitée</label>
                            <input type="datetime-local" name="date_souhaitee" id="date_souhaitee" value="{{ old('date_souhaitee') }}" class="form-control" required></div>
                        <div class="mb-3"><textarea name="message" class="form-control" rows="2" placeholder="Message (facultatif)" aria-label="Message">{{ old('message') }}</textarea></div>
                        <button class="btn btn-marque w-100"><i class="ti ti-calendar-check"></i> Envoyer la demande</button>
                    </form>
                    @if($agenceCourante->telephone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $agenceCourante->telephone) }}" class="btn btn-outline-secondary w-100 mt-2"><i class="ti ti-phone"></i> {{ $agenceCourante->telephone }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($similaires->isNotEmpty())
        <h2 class="h4 mt-5 mb-3">Biens similaires</h2>
        <div class="row g-4">
            @foreach($similaires as $bien)<div class="col-md-4">@include('vitrine._carte')</div>@endforeach
        </div>
    @endif
</div>
@endsection
