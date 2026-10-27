@extends('vitrine.layout')

@section('titre', 'Actualités — ' . $agenceCourante->nom)

@section('contenu')
<div class="container py-5">
    <h1 class="h3 mb-4">Actualités</h1>
    <div class="row g-4">
        @forelse($publications as $p)
            <div class="col-md-4">
                <div class="card carte-bien">
                    @if($p->image)<img src="{{ asset('storage/' . $p->image) }}" class="card-img-top" alt="" loading="lazy">@endif
                    <div class="card-body">
                        <div class="small text-muted">{{ ($p->publie_le ?? $p->created_at)->format('d/m/Y') }} · {{ \App\Models\Referentiel::libelle('categorie_publication', $p->categorie) }}</div>
                        <h2 class="h6"><a href="{{ route('vitrine.actualite', $p->slug) }}" class="text-reset">{{ $p->titre }}</a></h2>
                        <p class="small mb-0">{{ $p->resume }}</p>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted">Aucune actualité pour le moment.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $publications->links() }}</div>
</div>
@endsection
