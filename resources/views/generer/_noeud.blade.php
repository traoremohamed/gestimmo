{{-- Nœud de l'arbre des droits, récursif --}}
@php($aEnfants = $noeud['enfants'] !== [])
<div class="droit-noeud {{ $noeud['niveau'] === 1 ? 'ouvert' : '' }}">
    <div class="droit-ligne">
        @if ($aEnfants)
            <button type="button" class="bascule" title="Déplier"><i class="ti ti-chevron-right"></i></button>
            <input type="checkbox" class="form-check-input coche-groupe" title="Cocher toute la branche">
        @else
            <span class="bascule"></span>
        @endif

        @if ($noeud['lien'])
            <input type="checkbox" class="form-check-input coche-page" name="route[{{ $noeud['id'] }}]" value="{{ $noeud['id'] }}"
                   id="page-{{ $noeud['id'] }}" @checked(isset($roleSousmenus[$noeud['id']]))>
        @endif

        <label for="page-{{ $noeud['id'] }}" class="d-flex align-items-center gap-2 mb-0">
            <i class="{{ $noeud['icone'] ?: ($aEnfants ? 'ti ti-folder' : 'ti ti-file') }} text-primary"></i>
            <span class="fw-medium">{{ $noeud['libelle'] }}</span>
            @if ($noeud['lien'])<code class="small text-muted">/{{ $noeud['lien'] }}</code>@if($noeud['admin'] ?? false)<span class="badge badge-soft-danger">plateforme</span> @endif @else<span class="badge badge-soft-info">groupe</span>@endif
        </label>

        @if (count($noeud['permissions']) > 0)
            <div class="droit-permissions">
                @foreach ($noeud['permissions'] as $permission)
                    <label title="{{ $permission->name }}">
                        <input type="checkbox" class="form-check-input coche-permission m-0" name="permission[{{ $permission->id }}]" value="{{ $permission->id }}"
                               @checked(isset($rolePermissions[$permission->id]))>
                        <span>{{ $permission->lib_permission ?: $permission->name }}</span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    @if ($aEnfants)
        <div class="droit-enfants">
            @foreach ($noeud['enfants'] as $enfant)
                @include('generer._noeud', ['noeud' => $enfant])
            @endforeach
        </div>
    @endif
</div>
