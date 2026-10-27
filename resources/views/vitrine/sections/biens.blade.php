<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        @if (($d ?? collect())->isEmpty())
            <p class="text-center text-body-secondary">Aucun bien publié pour le moment.</p>
        @elseif (($o['affichage'] ?? 'GRILLE') === 'CARROUSEL')
            <div class="d-flex gap-4 overflow-auto pb-3" style="scroll-snap-type: x mandatory">
                @foreach ($d as $bien)<div style="min-width: 300px; max-width: 340px; scroll-snap-align: start">@include('vitrine._carte', ['bien' => $bien])</div>@endforeach
            </div>
        @else
            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3">
                @foreach ($d as $bien)<div class="col">@include('vitrine._carte', ['bien' => $bien])</div>@endforeach
            </div>
        @endif
        @if (!empty($o['bouton']))
            <div class="text-center mt-4"><a href="{{ route('vitrine.biens', array_filter(['destination' => ['VENTE' => 'vente', 'LOCATION' => 'location', 'MEUBLE' => 'meuble'][$o['destination'] ?? ''] ?? null])) }}" class="btn btn-s">Voir tous les biens</a></div>
        @endif
    </div>
</section>
