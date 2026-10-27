
@if(auth()->user()->can('parametresysteme-index'))
@extends('layouts.backLayout.designadmin')

@section('content')

    @php($Module='Configuration')
    @php($titre='Liste des paramètres généraux')
    @php($lien='parametresysteme')


    <h5 class="py-2 mb-1">
        <span class="text-muted fw-light"> <i
                class="ti ti-home"></i>  Accueil / {{$Module}} /</span> {{$titre}}
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
                        @can('parametresysteme-create')
                            <a href="{{ route('creerparametresysteme') }}"
                               class="btn btn-sm btn-primary waves-effect waves-light">
                                <i class="menu-icon tf-icons ti ti-plus"></i> Ajouter </a>
                        @endcan
                    </small>
                </div>
                <div class="card-body">


                        <table id="exampleData" class="table  table-bordered table-striped table-hover table-sm ">
                            <thead>
                            <tr>
                                <th>No</th>
                                <th>Parametre</th>
                                <th>Type valeur</th>
                                <th>Valeur</th>
                                <th>Image</th>
                                <th>statut</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($logos as $key => $logo)
                                <tr>
                                    <td>{{ $logo->id_logo  }}</td>
                                    <td>{{ $logo->titre_logo }} </td>
                                    <td>{{ $logo->valeur  }}</td>
                                    <td><?php echo $logo->mot_cle; ?> </td>
                                    <td>
                                        @if(!empty($logo->logo_logo))

                                            <img src="{{ asset('/frontend/logo/'. $logo->logo_logo)}}" alt=""
                                                 style="width:90px;">

                                        @endif

                                    </td>
                                    <td align="center">
                                            <?php if ($logo->flag_logo == 1){ ?>
                                        <span class="badge bg-success">Actif</span>
                                        <?php } else { ?>
                                        <span class="badge bg-danger">Inactif</span>
                                        <?php } ?>
                                    </td>
                                    <td align="center">
                                        @can('parametresysteme-edit')
                                            <a href="{{ route('modifierparametresysteme',\App\Helpers\Crypt::UrlCrypt($logo->id_logo)) }}"
                                               class=" "
                                               title="Modifier"><img src='/assets/img/editing.png'></a>
                                        @endcan


                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
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
