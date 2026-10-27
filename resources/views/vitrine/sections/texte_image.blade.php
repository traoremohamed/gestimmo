<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        <div class="row g-5 align-items-center {{ ($o['position_image'] ?? 'DROITE') === 'GAUCHE' ? 'flex-row-reverse' : '' }}">
            <div class="{{ $s->image ? 'col-lg-6' : 'col-lg-8 mx-auto text-center' }}">
                @if ($s->sous_titre)<div class="text-uppercase fw-semibold small" style="color:var(--s-p)">{{ $s->sous_titre }}</div>@endif
                @if ($s->titre)<h2 class="s-titre display-6 mb-3">{{ $s->titre }}</h2>@endif
                <div class="fs-5 lh-lg">{!! $s->contenu !!}</div>
                @if (!empty($o['bouton_libelle']) && !empty($o['bouton_lien']))<a href="{{ \App\Services\Site\SiteService::lien($o['bouton_lien']) }}" class="btn btn-s mt-3">{{ $o['bouton_libelle'] }}</a>@endif
            </div>
            @if ($s->image)<div class="col-lg-6"><img src="{{ \App\Services\Site\SiteService::url($s->image) }}" alt="{{ $s->titre }}" class="img-fluid rounded-4 shadow"></div>@endif
        </div>
    </div>
</section>
