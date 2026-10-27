{{-- Carte d'un bien publié. Variable : $bien --}}
<div class="card carte-bien">
    <a href="{{ route('vitrine.bien', $bien->reference) }}" class="text-decoration-none">
        @if($photo = $bien->photos->first())
            <img src="{{ $photo->url }}" class="card-img-top" alt="{{ $bien->libelle }}" loading="lazy">
        @else
            <div class="placeholder-photo card-img-top"><i class="ti ti-home"></i></div>
        @endif
    </a>
    <div class="card-body">
        <div class="small text-muted mb-1">
            {{ \App\Models\Referentiel::libelle('type_bien', $bien->type_bien) }}
            @if($bien->a_vendre)<span class="badge bg-light text-dark">À vendre</span>@endif
            @if($bien->a_louer)<span class="badge bg-light text-dark">À louer</span>@endif
            @if($bien->meuble)<span class="badge bg-light text-dark">Meublé</span>@endif
            @if($bien->statut->value === 'reserve')<span class="badge bg-warning text-dark">Réservé</span>@endif
        </div>
        <h5 class="card-title h6"><a href="{{ route('vitrine.bien', $bien->reference) }}" class="text-reset text-decoration-none">{{ $bien->libelle }}</a></h5>
        <div class="small text-muted mb-2">
            @if($bien->superficie)<i class="ti ti-ruler-measure"></i> {{ number_format((float) $bien->superficie, 0, ',', ' ') }} {{ $bien->unite_superficie === 'ha' ? 'ha' : 'm²' }}@endif
            @if($bien->nb_chambres) · <i class="ti ti-bed"></i> {{ $bien->nb_chambres }} ch.@endif
            @if($bien->programme) · {{ $bien->programme->nom }}@endif
        </div>
        <div class="prix">
            @if($bien->a_vendre && $bien->prix_vente){{ montant($bien->prix_vente) }}
            @elseif($bien->a_louer && $bien->loyer_mensuel){{ montant($bien->loyer_mensuel) }} <small class="text-muted fw-normal">/ mois</small>
            @elseif($bien->meuble && $bien->prix_nuitee){{ montant($bien->prix_nuitee) }} <small class="text-muted fw-normal">/ nuit</small>
            @else Prix sur demande @endif
        </div>
    </div>
</div>
