@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Paramètres métier" module="Paramétrage">
    <form method="POST" action="{{ route('parametres.update') }}">
        @csrf @method('PUT')

        <ul class="nav nav-pills mb-3" role="tablist">
            @foreach($groupes as $groupe => $parametres)
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="pill" data-bs-target="#groupe{{ $loop->index }}" type="button" role="tab">{{ $groupe }}</button>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="card-body tab-content">
                @foreach($groupes as $groupe => $parametres)
                    <div class="tab-pane fade @if($loop->first) show active @endif" id="groupe{{ $loop->index }}" role="tabpanel">
                        <div class="row">
                            @foreach($parametres as $cle => $parametre)
                                @php($champ = str_replace('.', '__', $cle))
                                @switch($parametre['type'])
                                    @case('booleen')
                                        <x-form.checkbox :name="$champ" :label="$parametre['libelle']" :value="$parametre['valeur']" />
                                        @break
                                    @case('choix')
                                        <x-form.select :name="$champ" :label="$parametre['libelle']" :options="$parametre['options']" :value="$parametre['valeur']" placeholder="" />
                                        @break
                                    @case('heure')
                                        <x-form.input :name="$champ" :label="$parametre['libelle']" type="time" :value="$parametre['valeur']" />
                                        @break
                                    @case('entier')
                                    @case('decimal')
                                    @case('pourcentage')
                                        <x-form.input :name="$champ" :label="$parametre['libelle']" type="number" step="{{ $parametre['type'] === 'entier' ? 1 : 0.01 }}" min="0"
                                            :value="$parametre['valeur']" />
                                        @break
                                    @default
                                        <x-form.input :name="$champ" :label="$parametre['libelle']" :value="$parametre['valeur']" />
                                @endswitch
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button>
            </div>
        </div>
    </form>
</x-page>
@endsection
