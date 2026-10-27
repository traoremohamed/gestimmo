@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Modifier le sous module" module="Configuration" :retour="route('sousmenus.index')">
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('sousmenus.update', $sousmenus) }}">
            @method('PUT')
            @include('sousmenus._form')
        </form>
    </div></div>
</x-page>
@endsection
