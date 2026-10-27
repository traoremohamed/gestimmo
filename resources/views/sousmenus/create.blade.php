@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Ajouter un sous module" module="Configuration" :retour="route('sousmenus.index')">
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('sousmenus.store') }}">
            
            @include('sousmenus._form')
        </form>
    </div></div>
</x-page>
@endsection
