<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 align-items-center justify-content-center text-center">
            @foreach ($el as $x)
                @if (!empty($x['image']))
                    <div class="col-6 col-md-2 s-partenaire">
                        @if (!empty($x['lien']))<a href="{{ \App\Services\Site\SiteService::lien($x['lien']) }}" target="_blank" rel="noopener">@endif
                        <img src="{{ \App\Services\Site\SiteService::url($x['image']) }}" alt="{{ $x['nom'] ?? '' }}" loading="lazy">
                        @if (!empty($x['lien']))</a>@endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
