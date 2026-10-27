@php
    $liste = collect($el)->map(fn ($x) => (object) ['nom' => $x['nom'] ?? '', 'fonction' => null, 'texte' => $x['texte'] ?? '', 'note' => (int) ($x['note'] ?? 5), 'image' => \App\Services\Site\SiteService::url($x['image'] ?? null)])
        ->merge(($d ?? collect())->map(fn ($t) => (object) ['nom' => $t->auteur, 'fonction' => $t->fonction, 'texte' => $t->contenu, 'note' => (int) ($t->note ?: 5), 'image' => $t->photo ? asset('storage/' . $t->photo) : null]));
@endphp
@if ($liste->isNotEmpty())
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 row-cols-1 row-cols-md-3">
            @foreach ($liste as $x)
                <div class="col"><div class="card s-carte p-4 d-flex flex-column">
                    <div class="text-warning mb-2">@for ($i = 1; $i <= 5; $i++)<i class="ti ti-star{{ $i <= $x->note ? '-filled' : '' }}"></i>@endfor</div>
                    <p class="fst-italic">« {{ strip_tags($x->texte) }} »</p>
                    <div class="d-flex align-items-center gap-2 mt-auto">@if ($x->image)<img src="{{ $x->image }}" class="rounded-circle" width="44" height="44" style="object-fit:cover" alt="">@endif
                        <div><strong>{{ $x->nom }}</strong>@if ($x->fonction)<div class="small text-body-secondary">{{ $x->fonction }}</div>@endif</div></div>
                </div></div>
            @endforeach
        </div>
    </div>
</section>
@endif
