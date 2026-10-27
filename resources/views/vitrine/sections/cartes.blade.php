<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-{{ (int) ($o['colonnes'] ?? 3) }}">
            @foreach ($el as $x)
                <div class="col"><div class="card s-carte p-4">
                    @if (!empty($x['image']))<img src="{{ \App\Services\Site\SiteService::url($x['image']) }}" class="rounded-3 mb-3" alt="" style="aspect-ratio:16/9; object-fit:cover">
                    @elseif (!empty($x['icone']))<div class="s-ico mb-3"><i class="{{ preg_replace('/[^a-z0-9 -]/', '', $x['icone']) }}"></i></div>@endif
                    <h5 class="fw-bold">{{ $x['titre'] ?? '' }}</h5>
                    <p class="mb-0 text-body-secondary">{!! nl2br(e(strip_tags($x['texte'] ?? ''))) !!}</p>
                </div></div>
            @endforeach
        </div>
    </div>
</section>
