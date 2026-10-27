@extends('layouts.backLayout.designadmin')

@php use App\Models\Referentiel; use App\Models\Rh\Absence; @endphp

@section('content')
<x-page titre="Congés & absences" module="RH & personnel">
    <div class="row">
        <div class="col-xl-4 order-xl-2">
            @can('absences-create')
                @if($employes->isNotEmpty())
                    <form method="POST" action="{{ route('absences.store') }}" class="card mb-4">
                        @csrf
                        <div class="card-header">Nouvelle demande</div>
                        <div class="card-body row">
                            <x-form.select name="employe_id" label="Employé" :options="$employes" :value="$monDossier?->id" required recherche col="col-12" />
                            <x-form.select name="type" label="Type" :options="$types" value="CONGE_ANNUEL" required col="col-12" />
                            <x-form.input name="date_debut" label="Du" type="date" required col="col-6" />
                            <x-form.input name="date_fin" label="Au" type="date" required col="col-6" />
                            <x-form.textarea name="motif" label="Motif" rows="2" />
                            @if($monDossier)<p class="small text-muted mb-0">Votre solde : {{ (float) $monDossier->solde_conges }} jour(s). Les dimanches ne sont pas décomptés.</p>@endif
                        </div>
                        <div class="card-footer text-end"><button class="btn btn-primary btn-sm">Envoyer la demande</button></div>
                    </form>
                @else
                    <div class="alert alert-info small">Votre compte n'est rattaché à aucun dossier du personnel : demandez au service RH de le lier.</div>
                @endif
            @endcan
            @if($enCours->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">Absents aujourd'hui</div>
                    <ul class="list-group list-group-flush small">
                        @foreach($enCours as $a)
                            <li class="list-group-item">{{ $a->employe->nom_complet }} — {{ Referentiel::libelle('type_absence', $a->type) }} jusqu'au {{ $a->date_fin->format('d/m') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="col-xl-8 order-xl-1">
            <ul class="nav nav-tabs mb-0">
                @foreach(['' => 'Toutes', 'demandee' => 'À valider', 'validee' => 'Validées', 'refusee' => 'Refusées'] as $s => $l)
                    <li class="nav-item"><a class="nav-link @if(request('statut', '') === $s) active @endif" href="{{ route('absences.index', array_filter(['statut' => $s])) }}">{{ $l }}</a></li>
                @endforeach
            </ul>
            <div class="card rounded-top-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Employé</th><th>Type</th><th>Période</th><th class="text-end">Jours</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse($absences as $a)
                                <tr>
                                    <td>{{ $a->employe->nom_complet }}</td>
                                    <td>{{ Referentiel::libelle('type_absence', $a->type) }}<div class="small text-muted">{{ $a->motif }}</div></td>
                                    <td class="small">{{ $a->date_debut->format('d/m/Y') }} → {{ $a->date_fin->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ (float) $a->nombre_jours }}</td>
                                    <td><span class="badge bg-{{ Absence::STATUTS[$a->statut][1] }}">{{ Absence::STATUTS[$a->statut][0] }}</span>
                                        @if($a->decideur)<div class="small text-muted">{{ $a->decideur->nom_complet }}{{ $a->commentaire_decision ? ' : ' . $a->commentaire_decision : '' }}</div>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if($a->statut === 'demandee')
                                            @can('absences-valider')
                                                <x-action :url="route('absences.decider', $a)" libelle="Valider" icone="ti-check" classe="btn btn-sm btn-success"><input type="hidden" name="decision" value="valider"></x-action>
                                                <x-action :url="route('absences.decider', $a)" libelle="Refuser" icone="ti-x" classe="btn btn-sm btn-outline-danger" confirmer="Refuser cette demande ?"><input type="hidden" name="decision" value="refuser"></x-action>
                                            @endcan
                                        @endif
                                        @if(in_array($a->statut, ['demandee', 'validee'], true))
                                            <x-action :url="route('absences.annuler', $a)" icone="ti-ban" classe="btn btn-sm btn-light" titre="Annuler" confirmer="Annuler cette absence ?" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-vide message="Aucune absence." icone="ti-beach" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($absences->hasPages())<div class="card-footer">{{ $absences->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-page>
@endsection
