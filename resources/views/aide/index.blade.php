@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Guide d'utilisation" module="Aide">
    <p class="text-muted">Un chapitre par module. Sur chaque écran, le bouton <span class="badge bg-info-subtle text-info"><i class="ti ti-help"></i> Aide</span> explique son fonctionnement.</p>
    <div class="row">
        @foreach($chapitres as $code => $c)
            <div class="col-sm-6 col-xl-4 mb-3">
                <a href="{{ route('aide.chapitre', $code) }}" class="card h-100 text-decoration-none text-reset {{ $c['actif'] ? '' : 'opacity-50' }}">
                    <div class="card-body d-flex gap-3">
                        <div class="stat-icon"><i class="ti {{ $c['icone'] }}"></i></div>
                        <div>
                            <div class="fw-semibold">{{ $c['titre'] }}</div>
                            <div class="small text-muted">{{ $c['resume'] }}</div>
                            @unless($c['actif'])<span class="badge bg-secondary-subtle text-secondary mt-1">Module non activé</span>@endunless
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</x-page>
@endsection
