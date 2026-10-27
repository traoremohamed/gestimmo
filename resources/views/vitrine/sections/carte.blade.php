@php $carte = \App\Services\Site\SiteService::carteSure($siteVitrine->carte_url); @endphp
@if ($carte)
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <iframe src="{{ $carte }}" class="w-100 rounded-4 border-0" style="min-height:420px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Plan d'accès"></iframe>
    </div>
</section>
@endif
