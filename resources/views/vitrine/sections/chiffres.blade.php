@php $chiffres = array_merge($d ?? [], $el); @endphp
@if (count($chiffres))
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 text-center">
            @foreach ($chiffres as $x)<div class="col-6 col-md"><div class="s-chiffre">{{ $x['valeur'] ?? '' }}</div><div class="fw-semibold">{{ $x['libelle'] ?? '' }}</div></div>@endforeach
        </div>
    </div>
</section>
@endif
