@extends('layouts.backLayout.designadmin')

@php use App\Models\Rh\Presence; @endphp

@section('content')
<x-page titre="Présences" module="RH & personnel">
    <div class="card mb-4">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.input name="date" label="Journée" type="date" :value="$date" max="{{ today()->toDateString() }}" col="col-md-3" />
            <div class="col-auto mb-3">
                <button class="btn btn-outline-primary">Afficher</button>
                <a href="{{ route('presences.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-light"><i class="ti ti-chevron-left"></i></a>
                @if($date->lt(today()))<a href="{{ route('presences.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-light"><i class="ti ti-chevron-right"></i></a>@endif
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('presences.store') }}" class="card">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <div class="card-header d-flex justify-content-between">
            <span>Pointage du {{ $date->locale('fr')->translatedFormat('l d F Y') }}</span>
            <span class="small text-muted">Mois en cours : jours présents / absences</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Employé</th><th style="width: 170px">Statut</th><th>Arrivée</th><th>Départ</th><th style="width: 110px">Heures sup.</th><th>Commentaire</th><th class="text-end">Mois</th></tr></thead>
                <tbody>
                    @forelse($employes as $e)
                        @php($p = $presences->get($e->id))
                        @php($m = $mois->get($e->id, collect()))
                        <tr>
                            <td>{{ $e->nom_complet }}<div class="small text-muted">{{ $e->matricule }}</div></td>
                            <td>
                                <select name="lignes[{{ $e->id }}][statut]" class="form-select form-select-sm" aria-label="Statut">
                                    @foreach(Presence::STATUTS as $code => [$libelle])
                                        <option value="{{ $code }}" @selected(($p?->statut ?? ($date->isSunday() ? 'repos' : 'present')) === $code)>{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="time" name="lignes[{{ $e->id }}][heure_arrivee]" value="{{ $p?->heure_arrivee ? substr($p->heure_arrivee, 0, 5) : '' }}" class="form-control form-control-sm" aria-label="Arrivée"></td>
                            <td><input type="time" name="lignes[{{ $e->id }}][heure_depart]" value="{{ $p?->heure_depart ? substr($p->heure_depart, 0, 5) : '' }}" class="form-control form-control-sm" aria-label="Départ"></td>
                            <td><input type="number" step="0.25" min="0" name="lignes[{{ $e->id }}][heures_supplementaires]" value="{{ $p ? (float) $p->heures_supplementaires : '' }}" class="form-control form-control-sm" aria-label="Heures supplémentaires"></td>
                            <td><input name="lignes[{{ $e->id }}][commentaire]" value="{{ $p?->commentaire }}" class="form-control form-control-sm" aria-label="Commentaire"></td>
                            <td class="text-end small">{{ $m->whereIn('statut', ['present', 'retard'])->count() + $m->where('statut', 'demi_journee')->count() / 2 }} / <span class="text-danger">{{ $m->where('statut', 'absent')->count() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-vide message="Aucun employé actif." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('presences-edit')
            @if($employes->isNotEmpty())<div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer le pointage</button></div>@endif
        @endcan
    </form>
</x-page>
@endsection
