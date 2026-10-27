@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Avances & prêts au personnel" module="RH & personnel">
    <div class="row">
        <x-stat libelle="Encours à rembourser" :valeur="montant($encours)" icone="ti-coin" col="col-md-4" />
    </div>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>N°</th><th>Employé</th><th>Date</th><th class="text-end">Montant</th><th class="text-end">Retenue mensuelle</th><th class="text-end">Remboursé</th><th>Statut</th></tr></thead>
                        <tbody>
                            @forelse($avances as $a)
                                <tr>
                                    <td>{{ $a->numero }}<div class="small text-muted">{{ $a->type === 'pret' ? 'Prêt' : 'Avance' }}</div></td>
                                    <td><a href="{{ route('employes.show', $a->employe) }}">{{ $a->employe->nom_complet }}</a></td>
                                    <td>{{ $a->date_octroi->format('d/m/Y') }}</td>
                                    <td class="text-end montant">{{ montant($a->montant) }}</td>
                                    <td class="text-end montant">{{ montant($a->montant_echeance) }} × {{ $a->nombre_echeances }}</td>
                                    <td class="text-end montant">{{ montant($a->montant_rembourse) }}</td>
                                    <td><span class="badge bg-{{ ['en_cours' => 'warning text-dark', 'soldee' => 'success', 'annulee' => 'secondary'][$a->statut] }}">{{ ['en_cours' => 'En cours', 'soldee' => 'Soldée', 'annulee' => 'Annulée'][$a->statut] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-vide message="Aucune avance." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($avances->hasPages())<div class="card-footer">{{ $avances->links() }}</div>@endif
            </div>
        </div>
        <div class="col-xl-4">
            @can('avances-create')
                <form method="POST" action="{{ route('avances.store') }}" class="card">
                    @csrf
                    <div class="card-header">Accorder une avance ou un prêt</div>
                    <div class="card-body row">
                        <x-form.select name="employe_id" label="Employé" :options="$employes" required recherche col="col-12" />
                        <x-form.select name="type" label="Type" :options="['avance' => 'Avance sur salaire', 'pret' => 'Prêt']" value="avance" required col="col-6" />
                        <x-form.input name="date_octroi" label="Date" type="date" :value="today()" required col="col-6" />
                        <x-form.input name="montant" label="Montant" type="number" step="0.01" min="0" required col="col-6" />
                        <x-form.input name="nombre_echeances" label="Nombre de retenues" type="number" min="1" value="1" required col="col-6" />
                        <x-form.select name="mode_paiement" label="Versé par" :options="$modes" value="ESPECES" required col="col-12" />
                        <x-form.textarea name="motif" label="Motif" rows="2" />
                        <p class="small text-muted mb-0">Le remboursement est retenu automatiquement sur les bulletins suivants, dans la limite du net disponible.</p>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary btn-sm">Accorder</button></div>
                </form>
            @endcan
        </div>
    </div>
</x-page>
@endsection
