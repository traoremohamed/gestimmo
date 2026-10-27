{{-- Nœud du menu latéral, rendu récursivement : autant de niveaux que l'arborescence en contient. --}}
@php
    $actif = \App\Helpers\Menu::noeudActif($noeud);
    $aEnfants = $noeud['enfants'] !== [];
    $icone = $noeud['icone'] ?: ($noeud['niveau'] === 1 ? 'ti ti-point' : null);
@endphp
<li class="ui-noeud {{ $actif ? 'actif ouvert' : '' }}">
    @if ($aEnfants)
        <a href="#" class="ui-lien ui-groupe" title="{{ $noeud['libelle'] }}">
            @if ($icone) <i class="ui-ico {{ $icone }}"></i> @else <span class="ui-puce"></span> @endif
            <span class="ui-libelle">{{ $noeud['libelle'] }}</span>
            <i class="ti ti-chevron-right ui-chevron"></i>
        </a>
        <ul>
            @if ($noeud['lien'])
                {{-- le parent est aussi une page : accès direct en tête de ses enfants --}}
                <li class="ui-noeud {{ request()->is($noeud['lien']) ? 'actif' : '' }}">
                    <a href="{{ url('/' . $noeud['lien']) }}" class="ui-lien"><span class="ui-puce"></span><span class="ui-libelle">Vue d'ensemble</span></a>
                </li>
            @endif
            @foreach ($noeud['enfants'] as $enfant)
                @include('layouts.partials.menu-noeud', ['noeud' => $enfant])
            @endforeach
        </ul>
    @else
        <a href="{{ url('/' . $noeud['lien']) }}" class="ui-lien" title="{{ $noeud['libelle'] }}">
            @if ($icone) <i class="ui-ico {{ $icone }}"></i> @else <span class="ui-puce"></span> @endif
            <span class="ui-libelle">{{ $noeud['libelle'] }}</span>
        </a>
    @endif
</li>
