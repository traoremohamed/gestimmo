@extends('layouts.backLayout.designadmin')

@section('content')

    @php($Module='Audit')
    @php($titre='Liste des logs')
    @php($lien='audit')
                <h5 class="py-2 mb-1">
                    <span class="text-muted fw-light"> <a href="{{ route('dashboard') }}"><i class="ti ti-home"></i> Accueil</a> / {{$Module}} / </span> {{$titre}}
                </h5>

                @if ($message = Session::get('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <div class="alert-body">
                            {{ $message }}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                        <div class="row">
                            <!-- Basic Layout -->
                            <div class="col-xxl">
                                <div class="card mb-4">
                                        <div class="card-body mb-2">
                                            <h6>Zone de recherche</h6>
                                            <form action="{{ route('audits.index') }}" method="GET">
                                                <div class="row">
                                                    <div class="col-md-4 col-12">
                                                        <div class="mb-1">
                                                            <label>Date de début</label>
                                                            <input type="date" name="date_debut" id="date_debut"
                                                                   class="form-control form-control-sm"
                                                                   value="{{ request('date_debut') }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-12">
                                                        <div class="mb-1">
                                                            <label>Date de fin</label>
                                                            <input type="date" name="date_fin" id="date_fin"
                                                                   class="form-control form-control-sm"
                                                                   value="{{ request('date_fin') }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-12">
                                                        <div class="mb-1">
                                                            <label>Adresse IP</label>
                                                            <input type="text" name="ip" id="ip"
                                                                   class="form-control form-control-sm"
                                                                   placeholder="127.0.0.1"
                                                                   value="{{ request('ip') }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-12">
                                                        <div class="mb-1">
                                                            <label>Module</label>
                                                            <input type="text" name="module" id="module"
                                                                   class="form-control form-control-sm"
                                                                   placeholder="Module"
                                                                   value="{{ request('module') }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-12">
                                                        <div class="mb-1">
                                                            <label>Utilisateur</label>
                                                            <input type="text" name="utilisateur" id="utilisateur"
                                                                   class="form-control form-control-sm"
                                                                   placeholder="Utilisateur"
                                                                   value="{{ request('utilisateur') }}">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-12">
                                                        <div class="mb-1">
                                                            <label>Privilège</label>
                                                            <select name="privilege" class="form-control form-control-sm">
                                                                <option value="">-- Choisir --</option>
                                                                @foreach($roles as $role)
                                                                    <option {{ request('privilege') == $role->name ? 'selected' : '' }} value="{{$role->name}}">{{$role->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-12">
                                                        <div class="mb-1">
                                                            <label>&nbsp;</label>
                                                            <button type="submit" class="w-100 btn btn-primary btn-sm">Rechercher</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>


                                        </div>

                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="mb-0">{{$titre}}</h5>
                                    </div>

                                    <div class="card-body">
                                    <!--begin: Datatable-->
                                    <table class="table table-bordered table-striped table-hover table-sm "
                                           id="" >
                                        <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Date d'action</th>
                                            <th>Nom d'hôte</th>
                                            <th>Adresse IP</th>
                                            <th>Etat</th>
                                            <th>Utilisateur</th>
                                            <th>Privilège</th>
                                            <th>Module</th>
                                            <th>Action</th>
                                            <th>Pièce</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($logs as $key => $log)
                                                <tr>
                                                    <td>{{ $key+1 }}</td>
                                                    <td>{{ $log->created_at }}</td>
                                                    <td>{{ $log->nom_hote_log }}</td>
                                                    <td>{{ is_numeric($log->ip_addr_log) ? long2ip((int) $log->ip_addr_log) : $log->ip_addr_log }}</td>
                                                    <td>{{ $log->etat_log }}</td>
                                                    <td>{{ $log->identifiant_log }} / {{ $log->utilisateur_log }}</td>
                                                    <td>{{ $log->role_log }}</td>
                                                    <td>{{ $log->menu_log }}</td>
                                                    <td>{{ $log->action_log }}</td>
                                                    <td>{{ $log->code_piece_log }}</td>
                                                </tr>

                                            @endforeach
                                        </tbody>
                                    </table>
                                    <!--end: Datatable-->
                                    </div>
                                    <div class="mt-2">
                                        @isset($logs)
                                            {{ $logs->appends(request()->query())->links('pagination::bootstrap-5') }}
                                        @endisset
                                    </div>
            </div>
        </div>
    </div>
    <!-- END: Content-->
@endsection


