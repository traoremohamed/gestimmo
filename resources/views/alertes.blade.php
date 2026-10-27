@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Alertes" :module="$agenceCourante?->raison_sociale">
    <x-slot:actions>
        <a href="{{ route('alertes', ['actualiser' => 1]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-refresh"></i> Actualiser</a>
    </x-slot:actions>

    <div class="list-group">
        @forelse($alertes as $a)
            <a href="{{ $a['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                <span class="stat-icon text-{{ $a['niveau'] }}" style="width: 40px; height: 40px; border-radius: .5rem; display: grid; place-items: center; background: var(--bs-{{ $a['niveau'] }}-bg-subtle)"><i class="ti {{ $a['icone'] }} fs-4"></i></span>
                <span class="flex-grow-1"><strong>{{ $a['nombre'] }}</strong> {{ $a['message'] }}</span>
                <i class="ti ti-chevron-right text-muted"></i>
            </a>
        @empty
            <div class="card"><div class="card-body"><x-vide message="Aucune alerte : tout est à jour." icone="ti-bell-check" /></div></div>
        @endforelse
    </div>
</x-page>
@endsection
