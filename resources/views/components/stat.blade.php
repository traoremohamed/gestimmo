@props(['libelle', 'valeur', 'icone' => 'ti-chart-bar', 'lien' => null, 'detail' => null, 'col' => 'col-sm-6 col-xl-3'])

<div class="{{ $col }} mb-3">
    <div class="card h-100">
        <div class="card-body stat-tile">
            <div class="stat-icon"><i class="ti {{ $icone }}"></i></div>
            <div class="flex-grow-1">
                <div class="stat-libelle">{{ $libelle }}</div>
                <div class="stat-valeur">{{ $valeur }}</div>
                @if($detail)<div class="small text-muted">{{ $detail }}</div>@endif
            </div>
            @if($lien)
                <a href="{{ $lien }}" class="stretched-link" aria-label="{{ $libelle }}"></a>
            @endif
        </div>
    </div>
</div>
