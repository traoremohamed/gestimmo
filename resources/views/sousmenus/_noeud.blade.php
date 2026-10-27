{{-- Ligne d'un sous-menu et, récursivement, de ses enfants --}}
<tr>
    <td>
        <div class="d-flex align-items-center gap-2" style="padding-left: {{ $profondeur * 1.6 }}rem">
            @if ($profondeur > 0)<i class="ti ti-corner-down-right text-muted"></i>@endif
            <i class="{{ $sousmenu->icone ?: ($sousmenu->estGroupe() ? 'ti ti-folder' : 'ti ti-file') }} text-primary"></i>
            <span class="fw-medium">{{ $sousmenu->libelle }}</span>
            @if ($sousmenu->estGroupe())<span class="badge badge-soft-info">groupe</span>@endif
            @if ($sousmenu->flag_is_admin)<span class="badge badge-soft-danger">plateforme</span>@endif
        </div>
    </td>
    <td><code>{{ $sousmenu->sousmenu ?: '—' }}</code></td>
    <td class="text-center">{{ $sousmenu->priorite_sousmenu }}</td>
    <td class="text-center">{{ $sousmenu->permissions_count }}</td>
    <td class="text-center"><span class="badge {{ $sousmenu->is_valide ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $sousmenu->is_valide ? 'actif' : 'inactif' }}</span></td>
    <td class="text-end text-nowrap">
        @can('sous-module-create')
            <a href="{{ route('sousmenus.create', ['parent' => $sousmenu->id_sousmenu]) }}" class="btn btn-sm btn-soft" title="Ajouter un sous-niveau"><i class="ti ti-subtask"></i></a>
        @endcan
        @can('sous-module-edit')
            <a href="{{ route('sousmenus.edit', $sousmenu) }}" class="btn btn-sm btn-soft" title="Modifier"><i class="ti ti-pencil"></i></a>
        @endcan
    </td>
</tr>
@foreach ($parParent[$sousmenu->id_sousmenu] ?? [] as $enfant)
    @include('sousmenus._noeud', ['sousmenu' => $enfant, 'profondeur' => $profondeur + 1])
@endforeach
