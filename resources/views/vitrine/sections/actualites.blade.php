<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 row-cols-1 row-cols-md-3">
            @forelse ($d ?? [] as $a)
                <div class="col"><div class="card s-carte overflow-hidden">
                    @if ($a->image)<img src="{{ asset('storage/' . $a->image) }}" alt="{{ $a->titre }}" style="aspect-ratio:16/9; object-fit:cover">@endif
                    <div class="p-4">
                        <div class="small text-body-secondary mb-1">{{ $a->publie_le?->translatedFormat('d F Y') }}</div>
                        <h5 class="fw-bold"><a href="{{ route('vitrine.actualite', $a->slug) }}" class="text-reset text-decoration-none">{{ $a->titre }}</a></h5>
                        <p class="mb-0 text-body-secondary">{{ \Illuminate\Support\Str::limit(strip_tags($a->resume ?: $a->contenu), 140) }}</p>
                    </div>
                </div></div>
            @empty
                <p class="text-center text-body-secondary w-100">Aucune actualité.</p>
            @endforelse
        </div>
        <div class="text-center mt-4"><a href="{{ route('vitrine.actualites') }}" class="btn btn-outline-secondary">Toutes les actualités</a></div>
    </div>
</section>
