@php
    $membres = collect($el)->map(fn ($x) => $x + ['image' => \App\Services\Site\SiteService::url($x['image'] ?? null)])->merge($d ?? collect());
@endphp
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 row-cols-2 row-cols-md-4 text-center">
            @forelse ($membres as $x)
                <div class="col"><div class="card s-carte p-4">
                    @if (!empty($x['image']))<img src="{{ $x['image'] }}" alt="{{ $x['nom'] ?? '' }}" class="s-avatar mb-3">
                    @else<div class="s-avatar mb-3">{{ mb_strtoupper(mb_substr($x['nom'] ?? '?', 0, 1)) }}</div>@endif
                    <div class="fw-bold">{{ $x['nom'] ?? '' }}</div><div class="small text-body-secondary">{{ $x['fonction'] ?? '' }}</div>
                    <div class="d-flex justify-content-center gap-3 mt-2">
                        @if (!empty($x['telephone']))<a href="tel:{{ preg_replace('/\s+/', '', $x['telephone']) }}" aria-label="Appeler"><i class="ti ti-phone"></i></a>@endif
                        @if (!empty($x['email']))<a href="mailto:{{ $x['email'] }}" aria-label="Écrire"><i class="ti ti-mail"></i></a>@endif
                    </div>
                </div></div>
            @empty
                <p class="text-center text-body-secondary w-100">Équipe à présenter.</p>
            @endforelse
        </div>
    </div>
</section>
