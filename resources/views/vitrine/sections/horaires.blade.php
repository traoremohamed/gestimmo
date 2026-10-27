<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container text-center" style="max-width:720px">
        @include('vitrine.sections._entete')
        <div class="fs-5">{!! $s->contenu ?: nl2br(e($siteVitrine->horaires)) !!}</div>
    </div>
</section>
