@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Modules activés" module="Administration">
    <x-slot:badge><span class="badge badge-soft-primary">{{ count($actifs) }} / {{ count($modules) }} actifs</span></x-slot:badge>

    <form method="POST" action="{{ route('modules.update') }}">
        @csrf @method('PUT')
        <p class="text-muted">Un module désactivé disparaît du menu de tous les utilisateurs de l'agence ; ses données sont conservées et réapparaissent à sa réactivation.</p>
        <fieldset class="row g-3" @cannot('modules-edit') disabled @endcannot>
            @foreach ($modules as $code => $module)
                @php($obligatoire = $module['obligatoire'] ?? false)
                @php($souscrit = in_array($code, $souscrits, true))
                <div class="col-md-6 col-xl-4">
                    <label class="card h-100 mb-0 {{ $souscrit ? '' : 'opacity-50' }}" for="module_{{ $code }}" style="cursor:pointer">
                        <div class="card-body d-flex gap-3">
                            <div class="ui-kpi-ico"><i class="{{ $module['icone'] }}"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <strong>{{ $module['libelle'] }}</strong>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="modules[]" value="{{ $code }}" id="module_{{ $code }}"
                                            @checked(in_array($code, $actifs, true)) @disabled($obligatoire || !$souscrit)>
                                    </div>
                                </div>
                                <div class="small text-muted mt-1">{{ $module['description'] }}</div>
                                @if ($obligatoire)<span class="badge badge-soft-secondary mt-2">socle, toujours actif</span>
                                @elseif (!$souscrit)<span class="badge badge-soft-warning mt-2">non souscrit : contactez la plateforme</span>@endif
                            </div>
                        </div>
                    </label>
                </div>
            @endforeach
        </fieldset>
        @can('modules-edit')
            <div class="text-end mt-3"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button></div>
        @endcan
    </form>
</x-page>
@endsection
