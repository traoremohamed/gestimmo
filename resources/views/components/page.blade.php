@props(['titre', 'module' => null, 'retour' => null])

{{-- En-tête de page : fil d'Ariane, titre, actions, message de succès --}}
@section('titre', $titre)

<div class="ui-entete">
    <div>
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="ti ti-home"></i></a></li>
                @if($module)<li class="breadcrumb-item"><span class="text-muted">{{ $module }}</span></li>@endif
                <li class="breadcrumb-item active" aria-current="page">{{ $titre }}</li>
            </ol>
        </nav>
        <h4 class="mb-0 d-flex align-items-center gap-2">
            @if($retour)
                <a href="{{ $retour }}" class="btn btn-sm btn-light no-print" title="Retour"><i class="ti ti-arrow-left"></i></a>
            @endif
            {{ $titre }}
            {{ $badge ?? '' }}
        </h4>
    </div>
    @php($aide = \App\Support\Aide::pour(request()->route()?->getName()))
    <div class="d-flex flex-wrap gap-2 no-print">
        {{ $actions ?? '' }}
        @if($aide)
            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="offcanvas" data-bs-target="#aide-page" aria-controls="aide-page"><i class="ti ti-help"></i> Aide</button>
        @endif
    </div>
</div>

@if($aide)
    <div class="offcanvas offcanvas-end" tabindex="-1" id="aide-page" aria-labelledby="aide-page-titre">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="aide-page-titre"><i class="ti ti-help-circle text-info"></i> {{ $aide['titre'] ?? $titre }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
        </div>
        <div class="offcanvas-body small">
            @if($aide['objectif'])<p>{{ $aide['objectif'] }}</p>@endif
            @if($aide['etapes'])
                <h6 class="text-uppercase text-muted small mt-3">Comment faire</h6>
                <ol class="ps-3">@foreach($aide['etapes'] as $e)<li class="mb-1">{{ $e }}</li>@endforeach</ol>
            @endif
            @if($aide['regles'])
                <h6 class="text-uppercase text-muted small mt-3">Bon à savoir</h6>
                <ul class="ps-3">@foreach($aide['regles'] as $r)<li class="mb-1">{{ $r }}</li>@endforeach</ul>
            @endif
            @if($aide['chapitre'] && config("aide.chapitres.{$aide['chapitre']}"))
                <a href="{{ route('aide.chapitre', $aide['chapitre']) }}" class="btn btn-sm btn-light mt-2"><i class="ti ti-book"></i> Guide : {{ config("aide.chapitres.{$aide['chapitre']}.titre") }}</a>
            @endif
        </div>
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-circle-check"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Veuillez corriger les erreurs suivantes :</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $erreur)
                <li>{{ $erreur }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{ $slot }}
