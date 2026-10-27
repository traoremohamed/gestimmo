<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-{{ (int) ($o['colonnes'] ?? 3) }} s-galerie">
            @foreach ($el as $x)
                @if (!empty($x['image']))<div class="col"><figure class="mb-0"><img src="{{ \App\Services\Site\SiteService::url($x['image']) }}" alt="{{ $x['legende'] ?? '' }}" loading="lazy">
                    @if (!empty($x['legende']))<figcaption class="small text-center mt-1 text-body-secondary">{{ $x['legende'] }}</figcaption>@endif</figure></div>@endif
            @endforeach
        </div>
    </div>
</section>
