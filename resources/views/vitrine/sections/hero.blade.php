@php $fond = \App\Services\Site\SiteService::url($s->image_fond); @endphp
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-slide s-h-{{ $o['hauteur'] ?? 'GRANDE' }}" style="{{ $fond ? "background-image:url('" . $fond . "');" : '' }}{{ $s->couleur_fond ? 'background-color:' . $s->couleur_fond : '' }}">
    <div class="container py-5 text-center">
        @if ($s->titre)<h1 class="display-3 fw-bold">{{ $s->titre }}</h1>@endif
        @if ($s->sous_titre)<p class="fs-4 mt-2">{{ $s->sous_titre }}</p>@endif
        @if ($s->contenu)<div class="fs-5 mt-3 mx-auto" style="max-width:720px">{!! $s->contenu !!}</div>@endif
        @if (!empty($o['bouton_libelle']) && !empty($o['bouton_lien']))<a href="{{ \App\Services\Site\SiteService::lien($o['bouton_lien']) }}" class="btn btn-lg btn-s mt-4">{{ $o['bouton_libelle'] }}</a>@endif
        @if (!empty($o['recherche']))<div class="mt-4 mx-auto" style="max-width:1000px">@include('vitrine.sections._formulaire_recherche')</div>@endif
    </div>
</section>
