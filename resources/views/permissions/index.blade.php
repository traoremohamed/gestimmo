@if(auth()->user()->can('permission-index'))
@extends('layouts.backLayout.designadmin')

@section('content')

    @php($Module='Configuration')
    @php($titre='Liste des permissions')
    @php($lien='permissions')

    <!-- BEGIN: Content-->
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
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{$titre}}</h5>
                    <small class="text-muted float-end">
                        @can('permission-create')
                            <a href="{{ route($lien.'.create') }}"
                               class="btn btn-sm btn-primary waves-effect waves-light">
                                <i class="menu-icon tf-icons ti ti-plus"></i> Ajouter </a>
                        @endcan
                    </small>
                </div>
                <div class="card-body">
                    <!--begin: Datatable-->
                    <table id="exampleData" class="table  table-bordered table-striped table-hover table-sm ">
                        <thead>
                        <tr>
                            <th>No</th>
                            <th>Sous menu</th>
                            <th>Permission</th>
                            <th>Code</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($data as $key => $permission)
                            <tr>
                                <td>{{ $permission->id }}</td>
                                <td>{{ @$permission->sousmenu->libelle }}</td>
                                <td>{{ $permission->lib_permission }}</td>
                                <td>{{ $permission->name }}</td>
                                <td align="center">
                                        <?php if ($permission->is_valide == true){ ?>
                                    <span class="badge bg-success">Actif</span>
                                    <?php } else { ?>
                                    <span class="badge bg-danger">Inactif</span>
                                    <?php } ?>
                                </td>
                                <td align="center">
                                    @can('permission-edit')
                                        <a href="{{ route('permissions.edit',\App\Helpers\Crypt::UrlCrypt($permission->id)) }}"
                                           class=" "
                                           title="Modifier"><img src='/assets/img/editing.png'></a>
                                    @endcan

                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <!--end: Datatable-->
                </div>
            </div>
        </div>
    </div>
    <!-- END: Content-->
@endsection
@else
 <script type="text/javascript">
    window.location = "{{ url('/403') }}";//here double curly bracket
</script>
@endif
