<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4 row-cols-1 row-cols-md-2">
            @forelse ($d ?? [] as $p)
                <div class="col"><div class="card s-carte p-4">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="s-ico"><i class="ti ti-building-estate"></i></div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1">{{ $p->nom }}</h5>
                            <div class="small text-body-secondary mb-2">{{ collect([$p->ville, $p->commune])->filter()->implode(', ') }}
                                @if ($p->date_livraison_prevue) · livraison {{ \Illuminate\Support\Carbon::parse($p->date_livraison_prevue)->translatedFormat('F Y') }}@endif</div>
                            @if ($p->description)<p class="mb-2">{{ \Illuminate\Support\Str::limit(strip_tags($p->description), 180) }}</p>@endif
                            <a href="{{ route('vitrine.biens', ['programme_id' => $p->id]) }}" class="btn btn-sm btn-s">{{ $p->disponibles }} bien(s) disponible(s)</a>
                        </div>
                    </div>
                </div></div>
            @empty
                <p class="text-center text-body-secondary w-100">Aucun programme publié.</p>
            @endforelse
        </div>
    </div>
</section>
