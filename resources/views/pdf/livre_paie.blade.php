@extends('pdf.layout')

@section('titre', 'Livre de paie ' . $periode->libelle)
@section('reference', 'Livre de paie — ' . $periode->libelle)

@section('contenu')
    <h1>Livre de paie — {{ $periode->libelle }}</h1>
    <table class="grille">
        <thead><tr><th>Matricule</th><th>Employé</th><th class="droite">Jours</th><th class="droite">Brut</th><th class="droite">Brut imposable</th><th class="droite">Cot. salariales</th>
            <th class="droite">ITS</th><th class="droite">Retenues</th><th class="droite">Net</th><th class="droite">Charges patronales</th><th class="droite">Coût</th></tr></thead>
        <tbody>
            @foreach($periode->bulletins->sortBy('employe.nom') as $b)
                <tr><td>{{ $b->employe->matricule }}</td><td>{{ $b->employe->nom_complet }}</td><td class="droite">{{ (float) $b->jours_travailles }}</td>
                    <td class="droite">{{ montant($b->brut, false) }}</td><td class="droite">{{ montant($b->brut_imposable, false) }}</td><td class="droite">{{ montant($b->cotisations_salariales, false) }}</td>
                    <td class="droite">{{ montant($b->impots, false) }}</td><td class="droite">{{ montant($b->retenues, false) }}</td><td class="droite">{{ montant($b->net_a_payer, false) }}</td>
                    <td class="droite">{{ montant($b->cotisations_patronales, false) }}</td><td class="droite">{{ montant($b->cout_employeur, false) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="3">Total ({{ $periode->bulletins->count() }} salarié(s))</td>
                @foreach(['brut', 'brut_imposable', 'cotisations_salariales', 'impots', 'retenues', 'net_a_payer', 'cotisations_patronales', 'cout_employeur'] as $c)
                    <td class="droite">{{ montant($periode->bulletins->sum($c), false) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
@endsection
