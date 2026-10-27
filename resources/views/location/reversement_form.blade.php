@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Préparer un reversement" module="Gestion locative" :retour="route('reversements.index')">
    <div class="card mb-4">
        <form method="GET" class="card-body row g-2 align-items-end">
            <x-form.select name="proprietaire_id" label="Propriétaire" :options="$proprietaires" :value="$proprietaire?->id" required recherche col="col-md-5" />
            <x-form.input name="debut" label="Du" type="date" :value="$debut" col="col-md-3" />
            <x-form.input name="fin" label="Au" type="date" :value="$fin" col="col-md-2" />
            <div class="col-md-2 mb-3"><button class="btn btn-outline-primary w-100"><i class="ti ti-search"></i> Calculer</button></div>
        </form>
    </div>

    @if($proprietaire)
        @php
            $encaisse = $loyers->sum(fn ($l) => (float) $l->montant_paye);
            $deductions = $depenses->sum(fn ($d) => (float) $d->montant);
        @endphp
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">Loyers encaissés non reversés ({{ $loyers->count() }})</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Échéance</th><th>Bien</th><th>Libellé</th><th class="text-end">Encaissé</th><th class="text-end">Taux</th></tr></thead>
                            <tbody>
                                @forelse($loyers as $loyer)
                                    <tr>
                                        <td>{{ $loyer->date_echeance->format('d/m/Y') }}</td>
                                        <td>{{ $loyer->bail->bien->reference }}</td>
                                        <td>{{ $loyer->libelle }}</td>
                                        <td class="text-end montant">{{ montant($loyer->montant_paye) }}</td>
                                        <td class="text-end">{{ $loyer->bail->mandat?->taux_commission ?? parametre('location.taux_commission_gestion') }} %</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-vide message="Aucun loyer soldé à reverser jusqu'à cette date." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($depenses->isNotEmpty())
                    <div class="card mb-4">
                        <div class="card-header">Dépenses refacturées au propriétaire</div>
                        <ul class="list-group list-group-flush small">
                            @foreach($depenses as $depense)
                                <li class="list-group-item d-flex justify-content-between"><span>{{ $depense->date_depense->format('d/m/Y') }} — {{ $depense->libelle }}</span><span class="montant">{{ montant($depense->montant) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <div class="col-lg-4">
                <form method="POST" action="{{ route('reversements.store') }}" class="card">
                    @csrf
                    <input type="hidden" name="proprietaire_id" value="{{ $proprietaire->id }}">
                    <input type="hidden" name="debut" value="{{ $debut->toDateString() }}">
                    <input type="hidden" name="fin" value="{{ $fin->toDateString() }}">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span>Loyers encaissés</span><strong class="montant">{{ montant($encaisse) }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Dépenses à déduire</span><span class="montant">- {{ montant($deductions) }}</span></li>
                        <li class="list-group-item small text-muted">La commission de gestion est calculée à la préparation selon le taux de chaque mandat.</li>
                    </ul>
                    <div class="card-footer">
                        <button class="btn btn-primary w-100" @disabled($loyers->isEmpty())><i class="ti ti-file-plus"></i> Préparer le reversement</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-page>
@endsection
