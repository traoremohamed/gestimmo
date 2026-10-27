@extends('layouts.backLayout.designadmin')

@php
    $score = \App\Services\Securite\DiagnosticSecurite::score($controles);
    $couleur = $score >= 85 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
    $badges = ['CRITIQUE' => 'danger', 'ALERTE' => 'warning', 'INFO' => 'info'];
@endphp

@section('content')
<x-page titre="Sécurité de l'installation" module="Plateforme">
    <p class="text-muted">Contrôles automatiques (aussi : <code>php artisan immobilier:securite</code>). À viser : 100 % avant la mise en production.</p>
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card text-center"><div class="card-body">
                <div class="small text-muted">Score de sécurité</div>
                <div class="display-5 fw-bold text-{{ $couleur }}">{{ $score }} %</div>
                <div class="small">{{ collect($controles)->where('ok', false)->where('gravite', 'CRITIQUE')->count() }} point(s) critique(s) à corriger</div>
                <a href="{{ route('sauvegardes.index') }}" class="btn btn-sm btn-outline-primary mt-3"><i class="ti ti-database-export"></i> Sauvegardes</a>
                <a href="{{ route('audits.index') }}" class="btn btn-sm btn-outline-secondary mt-3"><i class="ti ti-history"></i> Journal d'audit</a>
            </div></div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <ul class="list-group list-group-flush small">
                    @foreach (collect($controles)->sortBy(fn ($c) => ($c['ok'] ? 10 : 0) + ['CRITIQUE' => 0, 'ALERTE' => 1, 'INFO' => 2][$c['gravite']]) as $c)
                        <li class="list-group-item d-flex gap-3 align-items-start">
                            <i class="ti {{ $c['ok'] ? 'ti-circle-check text-success' : 'ti-alert-triangle text-' . $badges[$c['gravite']] }} fs-4"></i>
                            <div class="flex-grow-1"><strong>{{ $c['libelle'] }}</strong> @unless($c['ok'])<span class="badge bg-{{ $badges[$c['gravite']] }}">{{ strtolower($c['gravite']) }}</span>@endunless
                                <div class="text-muted">{{ $c['detail'] }}</div></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-page>
@endsection
