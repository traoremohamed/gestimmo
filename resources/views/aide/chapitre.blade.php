@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$c['titre']" module="Guide d'utilisation" :retour="route('aide.index')">
    <div class="row">
        <div class="col-lg-3 mb-3 no-print">
            <div class="list-group small">
                @foreach($chapitres as $k => $ch)
                    <a href="{{ route('aide.chapitre', $k) }}" class="list-group-item list-group-item-action {{ $k === $code ? 'active' : '' }}">
                        <i class="ti {{ $ch['icone'] }}"></i> {{ $ch['titre'] }}
                    </a>
                @endforeach
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <p class="lead fs-6">{{ $c['resume'] }}</p>
                    @unless($c['actif'])
                        <div class="alert alert-secondary small">Ce module n'est pas activé pour votre agence (Administration › Modules activés).</div>
                    @endunless
                    @foreach($c['sections'] as $s)
                        <h6 class="mt-4">{{ $s['titre'] }}</h6>
                        @isset($s['texte'])<p>{{ $s['texte'] }}</p>@endisset
                        @isset($s['etapes'])<ol>@foreach($s['etapes'] as $e)<li class="mb-1">{{ $e }}</li>@endforeach</ol>@endisset
                        @isset($s['astuces'])<ul>@foreach($s['astuces'] as $a)<li class="mb-1">{{ $a }}</li>@endforeach</ul>@endisset
                    @endforeach
                    @if($c['actif'] && $c['liens'])
                        <div class="d-flex flex-wrap gap-2 mt-4 no-print">
                            @foreach($c['liens'] as $libelle => $url)
                                <a href="{{ url($url) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-arrow-right"></i> {{ $libelle }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-page>
@endsection
