@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Simulateur de décision" module="Informatique décisionnelle" :retour="route('analyse')">
    <div class="row">
        <div class="col-lg-5">
            <form method="GET" class="card mb-4">
                <div class="card-header">Hypothèses</div>
                <div class="card-body">
                    @foreach([
                        'recouvrement' => ['Part des arriérés recouvrée', '%', 0, 100, 5, 'Arriérés actuels : ' . montant($simulation['base']['arrieres'])],
                        'hausse_loyers' => ['Révision des loyers', '%', -20, 30, 1, 'Loyers mensuels en cours : ' . montant($simulation['base']['loyers'])],
                        'occupation' => ['Taux d\'occupation visé', '%', 0, 100, 1, 'Actuel : ' . $simulation['base']['occupation'] . ' % (' . $simulation['base']['vacants'] . ' vacant(s))'],
                        'remise_stock' => ['Remise accordée sur le stock', '%', 0, 50, 1, 'Stock disponible : ' . montant($simulation['base']['stock'])],
                        'ventes_stock' => ['Part du stock vendue en 12 mois', '%', 0, 100, 5, null],
                    ] as $cle => [$libelle, $unite, $min, $max, $pas, $aide])
                        <div class="mb-3">
                            <label class="form-label d-flex justify-content-between" for="{{ $cle }}">{{ $libelle }} <strong><output id="{{ $cle }}_val">{{ $h[$cle] }}</output> {{ $unite }}</strong></label>
                            <input type="range" class="form-range" id="{{ $cle }}" name="{{ $cle }}" min="{{ $min }}" max="{{ $max }}" step="{{ $pas }}" value="{{ $h[$cle] }}"
                                oninput="document.getElementById('{{ $cle }}_val').value = this.value">
                            @if($aide)<div class="form-text">{{ $aide }}</div>@endif
                        </div>
                    @endforeach
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-calculator"></i> Simuler</button></div>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">Encaissements supplémentaires attendus sur 12 mois</div>
                <table class="table mb-0">
                    <tbody>
                        @foreach($simulation['lignes'] as $libelle => $valeur)
                            <tr><td>{{ $libelle }}</td><td class="text-end montant">{{ montant($valeur) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fw-bold fs-5"><td>Total projeté</td><td class="text-end montant">{{ montant($simulation['total']) }}</td></tr></tfoot>
                </table>
                <div class="card-footer small text-muted">
                    Estimation indicative : les biens remis en location sont comptés en moyenne sur 6 mois ; la remise réduit le prix de vente du stock vendu.
                    Comparez plusieurs scénarios pour arbitrer entre baisse de prix, effort de recouvrement et politique locative.
                </div>
            </div>
        </div>
    </div>
</x-page>
@endsection
