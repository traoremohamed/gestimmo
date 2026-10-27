{{-- Liste compacte des biens d'un programme / lotissement / immeuble. Variables : $biens, $filtre (paramètre de filtre de la liste complète) --}}
@php
    $parStatut = $biens->groupBy(fn ($b) => $b->statut->value)->map->count();
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Biens ({{ $biens->count() }})</span>
        <span class="d-flex gap-2">
            @foreach(\App\Enums\StatutBien::cases() as $statut)
                @if($parStatut[$statut->value] ?? 0)
                    <span class="badge bg-{{ $statut->couleur() }}">{{ $statut->libelle() }} : {{ $parStatut[$statut->value] }}</span>
                @endif
            @endforeach
            <a href="{{ route('biens.index', $filtre) }}" class="btn btn-sm btn-light">Liste complète</a>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 exampleData">
            <thead><tr><th>Référence</th><th>Libellé</th><th>Îlot / étage</th><th class="text-end">Superficie</th><th class="text-end">Prix / loyer</th><th>Statut</th></tr></thead>
            <tbody>
                @foreach($biens as $bien)
                    <tr>
                        <td><a href="{{ route('biens.show', $bien) }}">{{ $bien->reference }}</a></td>
                        <td>{{ $bien->libelle }}</td>
                        <td>{{ $bien->ilot ?? $bien->etage }}</td>
                        <td class="text-end">{{ $bien->superficie ? number_format((float) $bien->superficie, 0, ',', ' ') : '' }}</td>
                        <td class="text-end montant">{{ $bien->a_vendre ? montant($bien->prix_vente, false) : ($bien->a_louer ? montant($bien->loyer_mensuel, false) : '') }}</td>
                        <td><x-statut :valeur="$bien->statut" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
