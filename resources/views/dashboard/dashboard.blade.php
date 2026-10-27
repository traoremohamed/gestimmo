@extends('layouts.backLayout.designadmin')

@section('content')
    <h5 class="py-2 mb-3">
        <span class="text-muted fw-light"><i class="ti ti-home"></i> Accueil /</span> Tableau de bord
    </h5>

    @if ($message = Session::get('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    @endif

    <p class="text-muted">Profil : <strong>{{ $naroles }}</strong> — {{ $date }}</p>

    <div class="row g-3">
        @foreach ($stats as $libelle => $nombre)
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">{{ $libelle }}</div>
                        <div class="fs-3 fw-semibold">{{ $nombre }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
