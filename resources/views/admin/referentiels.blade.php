@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Listes de valeurs" module="Paramétrage">
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="list-group">
                @foreach($categories as $code => $libelle)
                    <a href="{{ route('referentiels', ['categorie' => $code]) }}" class="list-group-item list-group-item-action @if($code === $categorie) active bg-primary border-primary @endif">{{ $libelle }}</a>
                @endforeach
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">{{ $categories[$categorie] }}</div>
                <div class="table-responsive">
                    {{-- les champs sont rattachés à leur formulaire par l'attribut form (un <form> ne peut pas envelopper un <tr>) --}}
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Code</th><th>Libellé</th><th style="width: 100px">Ordre</th><th>Actif</th><th></th></tr></thead>
                        <tbody>
                            @foreach($valeurs as $valeur)
                                @php($f = 'ref' . $valeur->id)
                                <tr>
                                    <td><code>{{ $valeur->code }}</code></td>
                                    <td><input form="{{ $f }}" name="libelle" value="{{ $valeur->libelle }}" class="form-control form-control-sm" required aria-label="Libellé"></td>
                                    <td><input form="{{ $f }}" name="ordre" type="number" value="{{ $valeur->ordre }}" class="form-control form-control-sm" aria-label="Ordre"></td>
                                    <td>
                                        <input form="{{ $f }}" type="hidden" name="actif" value="0">
                                        <div class="form-check form-switch"><input form="{{ $f }}" class="form-check-input" type="checkbox" name="actif" value="1" @checked($valeur->actif) aria-label="Actif"></div>
                                    </td>
                                    <td class="text-end">
                                        @can('referentiels-edit')<button form="{{ $f }}" class="btn btn-sm btn-light" title="Enregistrer"><i class="ti ti-device-floppy"></i></button>@endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @can('referentiels-edit')
                            <tfoot>
                                <tr>
                                    <td><input form="refNouveau" name="code" class="form-control form-control-sm" placeholder="Code (auto)" aria-label="Code"></td>
                                    <td><input form="refNouveau" name="libelle" class="form-control form-control-sm" placeholder="Nouvelle valeur" required aria-label="Libellé"></td>
                                    <td><input form="refNouveau" name="ordre" type="number" class="form-control form-control-sm" aria-label="Ordre"></td>
                                    <td></td>
                                    <td class="text-end"><button form="refNouveau" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Ajouter</button></td>
                                </tr>
                            </tfoot>
                        @endcan
                    </table>
                </div>
            </div>

            @foreach($valeurs as $valeur)
                <form id="ref{{ $valeur->id }}" method="POST" action="{{ route('referentiels.update', $valeur) }}">@csrf @method('PUT')</form>
            @endforeach
            <form id="refNouveau" method="POST" action="{{ route('referentiels.store') }}">
                @csrf
                <input type="hidden" name="categorie" value="{{ $categorie }}">
            </form>

            <p class="small text-muted mt-2"><i class="ti ti-info-circle"></i> Une valeur utilisée n'est jamais supprimée : désactivez-la pour qu'elle ne soit plus proposée.</p>
        </div>
    </div>
</x-page>
@endsection
