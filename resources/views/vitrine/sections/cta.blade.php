<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section text-center text-white" style="background: {{ $s->image_fond ? 'linear-gradient(rgba(0,0,0,.55), rgba(0,0,0,.55)), url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover' : 'var(--s-p)' }}">
    <div class="container">
        @if ($s->titre)<h2 class="s-titre display-6">{{ $s->titre }}</h2>@endif
        @if ($s->contenu)<div class="fs-5 mx-auto mt-3" style="max-width:720px">{!! $s->contenu !!}</div>@endif
        @if (!empty($o['bouton_libelle']) && !empty($o['bouton_lien']))<a href="{{ \App\Services\Site\SiteService::lien($o['bouton_lien']) }}" class="btn btn-lg btn-s2 mt-4">{{ $o['bouton_libelle'] }}</a>@endif
    </div>
</section>
