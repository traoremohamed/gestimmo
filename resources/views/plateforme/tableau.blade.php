@extends('layouts.backLayout.designadmin')

@php
    $m = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $couleurScore = $score >= 85 ? 'succes' : ($score >= 60 ? 'alerte' : 'danger');
    $couleursSv = ['SUCCES' => 'success', 'PARTIEL' => 'warning', 'ECHEC' => 'danger', 'EN_COURS' => 'info'];
@endphp

@section('content')
<x-page titre="Tableau de bord plateforme" module="Plateforme">
    <x-slot:actions>
        <a href="{{ route('agences.create') }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Nouvelle agence</a>
    </x-slot:actions>

    <div class="row g-3 mb-3">
        @foreach ([['Agences clientes', $stats['agences'], 'ti-building-skyscraper', ''], ['Abonnements valides', $stats['actives'], 'ti-circle-check', 'succes'],
                   ['Expirent sous 30 jours', $stats['expirant'], 'ti-alert-triangle', 'alerte'], ['Sites vitrine publiés', $stats['vitrines'], 'ti-world', 'info'],
                   ['Utilisateurs', $stats['utilisateurs'], 'ti-users', 'info'], ['Encaissé sur 30 jours', $m($stats['encaisse']) . ' F', 'ti-cash', 'succes'],
                   ['Score de sécurité', $score . ' %', 'ti-shield-check', $couleurScore],
                   ['Dernière sauvegarde', $sauvegarde ? $sauvegarde->debut_at->format('d/m H:i') : 'aucune', 'ti-database-export', $sauvegarde?->statut === 'SUCCES' ? 'succes' : 'danger']] as [$lib, $val, $ico, $cls])
            <div class="col-sm-6 col-xl-3">
                <div class="card ui-kpi"><div class="ui-kpi-ico {{ $cls }}"><i class="ti {{ $ico }}"></i></div>
                    <div><div class="ui-kpi-libelle">{{ $lib }}</div><div class="ui-kpi-valeur">{{ $val }}</div></div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center"><span>Agences clientes</span>
                    <a href="{{ route('agences.index') }}" class="btn btn-sm btn-light">Gérer les agences</a></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Agence</th><th class="text-end">Utilisateurs</th><th class="text-end">Biens</th><th class="text-end">Encaissé 30 j</th><th>Abonnement</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($agences as $a)
                            <tr>
                                <td><span class="fw-semibold">{{ $a->raison_sociale }}</span> <small class="text-muted">{{ $a->code }}</small>
                                    @if ($a->vitrine_active)<i class="ti ti-world text-success" title="Site vitrine publié"></i>@endif
                                    @unless ($a->actif)<span class="badge badge-soft-danger">inactive</span>@endunless</td>
                                <td class="text-end tabular">{{ $utilisateurs[$a->id] ?? 0 }}</td>
                                <td class="text-end tabular">{{ $biens[$a->id] ?? 0 }}</td>
                                <td class="text-end tabular">{{ $m($encaisse[$a->id] ?? 0) }}</td>
                                <td>
                                    @if (!$a->abonnement_fin)<span class="badge badge-soft-secondary">sans échéance</span>
                                    @else<span class="badge {{ $a->abonnement_fin->isPast() ? 'badge-soft-danger' : ($a->abonnement_fin->lte(today()->addDays(30)) ? 'badge-soft-warning' : 'badge-soft-success') }}">{{ $a->abonnement_fin->format('d/m/Y') }}</span>@endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('agences.basculer') }}">@csrf<input type="hidden" name="agence_id" value="{{ $a->id }}">
                                        <button class="btn btn-sm btn-soft" title="Travailler dans cette agence"><i class="ti ti-login-2"></i> Entrer</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="ui-vide"><i class="ti ti-building-off"></i>Aucune agence.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center"><span>Sécurité</span><a href="{{ route('securite.index') }}" class="btn btn-sm btn-light">Diagnostic</a></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($critiques as $c)
                        <li class="list-group-item"><i class="ti ti-alert-triangle text-danger"></i> <strong>{{ $c['libelle'] }}</strong><div class="text-muted">{{ $c['detail'] }}</div></li>
                    @empty
                        <li class="list-group-item text-success"><i class="ti ti-circle-check"></i> Aucun point critique.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"><span>Sauvegardes</span><a href="{{ route('sauvegardes.index') }}" class="btn btn-sm btn-light">Configurer</a></div>
                <div class="card-body small">
                    @if ($sauvegarde)
                        <div>{{ $sauvegarde->numero }} du {{ $sauvegarde->debut_at->format('d/m/Y H:i') }}
                            <span class="badge bg-{{ $couleursSv[$sauvegarde->statut] ?? 'secondary' }}">{{ strtolower($sauvegarde->statut) }}</span></div>
                        @if ($sauvegarde->erreur)<div class="text-danger mt-1">{{ \Illuminate\Support\Str::limit($sauvegarde->erreur, 160) }}</div>@endif
                    @else
                        <span class="text-danger">Aucune sauvegarde : configurez la sauvegarde automatique.</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-page>
@endsection
