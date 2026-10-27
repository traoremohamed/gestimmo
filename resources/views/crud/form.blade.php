@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="($element ? 'Modifier : ' : 'Nouveau / nouvelle : ') . $titreSingulier" :module="$module" :retour="route($route . '.index')">
    <form method="POST" action="{{ $element ? route($route . '.update', $element) : route($route . '.store') }}" enctype="multipart/form-data">
        @csrf
        @if($element) @method('PUT') @endif

        <div class="card">
            <div class="card-body">
                <div class="row">
                    @foreach($champs as $champ)
                        @include('crud.champ', ['champ' => $champ, 'element' => $element])
                    @endforeach
                </div>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-end">
                <a href="{{ route($route . '.index') }}" class="btn btn-light">Annuler</a>
                <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button>
            </div>
        </div>
    </form>
</x-page>
@endsection
