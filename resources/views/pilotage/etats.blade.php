@extends('layouts.backLayout.designadmin')

@php($parModule = collect($rapports)->groupBy(fn ($r) => $r[2], true))

@section('content')
<x-page titre="États & rapports" module="Tableaux de bord">
    @foreach($parModule as $module => $liste)
        <h6 class="text-muted text-uppercase small mt-3 mb-2">{{ config("immobilier.modules.$module.libelle") ?? $module }}</h6>
        <div class="row">
            @foreach($liste as $code => [$libelle, $icone])
                <div class="col-sm-6 col-xl-3 mb-3">
                    <a href="{{ route('etats.show', $code) }}" class="card h-100 text-decoration-none text-reset">
                        <div class="card-body stat-tile">
                            <div class="stat-icon"><i class="ti {{ $icone }}"></i></div>
                            <div class="fw-medium">{{ $libelle }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</x-page>
@endsection
