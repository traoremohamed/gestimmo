{{-- Diaporama : diapositives saisies, biens en vedette (photo, prix, situation — comme l'ancien site) ou bannières de l'agence --}}
@php
    $h = 's-h-' . ($o['hauteur'] ?? 'GRANDE'); $id = 'carrousel-' . $s->id; $intervalle = (int) ($o['intervalle'] ?? 5);
    $source = $o['source'] ?? 'MANUEL';
    $diapos = match (true) {
        $source === 'BIENS' && $d && $d->isNotEmpty() => $d->map(fn ($b) => ['image' => $b->photos->first()?->url, 'titre' => $b->libelle,
            'texte' => trim(collect([\App\Models\Referentiel::libelle('type_bien', $b->type_bien), $b->programme?->nom, $b->ville ?? null])->filter()->implode(' · ')),
            'prix' => $b->a_vendre && $b->prix_vente ? montant($b->prix_vente) : ($b->a_louer && $b->loyer_mensuel ? montant($b->loyer_mensuel) . ' / mois' : null),
            'bouton_libelle' => 'Voir le bien', 'lien' => route('vitrine.bien', $b->reference)])->all(),
        $source === 'SLIDES' && $d && $d->isNotEmpty() => $d->map(fn ($x) => ['image' => $x->image ? asset('storage/' . $x->image) : null, 'titre' => $x->titre, 'texte' => $x->sous_titre,
            'bouton_libelle' => $x->bouton_libelle, 'lien' => \App\Services\Site\SiteService::lien($x->bouton_lien)])->all(),
        default => collect($el)->map(fn ($x) => $x + ['image' => \App\Services\Site\SiteService::url($x['image'] ?? null), 'lien' => \App\Services\Site\SiteService::lien($x['bouton_lien'] ?? null)])->all(),
    };
@endphp
@if (count($diapos))
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}">
    <div id="{{ $id }}" class="carousel slide carousel-fade" data-bs-ride="{{ $intervalle > 0 ? 'carousel' : 'false' }}" data-bs-interval="{{ max(1, $intervalle) * 1000 }}">
        @if (count($diapos) > 1)<div class="carousel-indicators">@foreach ($diapos as $i => $x)<button type="button" data-bs-target="#{{ $id }}" data-bs-slide-to="{{ $i }}" class="{{ $i ? '' : 'active' }}" aria-label="Diapositive {{ $i + 1 }}"></button>@endforeach</div>@endif
        <div class="carousel-inner">
            @foreach ($diapos as $i => $x)
                <div class="carousel-item {{ $i ? '' : 'active' }}">
                    <div class="s-slide {{ $h }}" style="{{ !empty($x['image']) ? "background-image:url('" . $x['image'] . "');" : '' }}">
                        <div class="container py-5">
                            <div class="col-lg-7">
                                @if (!empty($x['titre']))<h1 class="display-4 fw-bold">{{ $x['titre'] }}</h1>@endif
                                @if (!empty($x['texte']))<p class="fs-5 my-3">{!! nl2br(e(strip_tags($x['texte']))) !!}</p>@endif
                                @if (!empty($x['prix']))<div class="fs-3 fw-bold mb-3"><span class="badge bg-light text-dark">{{ $x['prix'] }}</span></div>@endif
                                @if (!empty($x['bouton_libelle']) && !empty($x['lien']))<a href="{{ $x['lien'] }}" class="btn btn-lg btn-s">{{ $x['bouton_libelle'] }}</a>@endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if (count($diapos) > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#{{ $id }}" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span><span class="visually-hidden">Précédent</span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#{{ $id }}" data-bs-slide="next"><span class="carousel-control-next-icon"></span><span class="visually-hidden">Suivant</span></button>
        @endif
    </div>
</section>
@endif
