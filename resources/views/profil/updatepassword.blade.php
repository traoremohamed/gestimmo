@extends('layouts.backLayout.designadmin')

@section('content')
    <h5 class="py-2 mb-3">
        <span class="text-muted fw-light"><i class="ti ti-home"></i> Accueil / Profil /</span> Modifier mon mot de passe
    </h5>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    @if (!Auth::user()->flag_mdp)
                        <div class="alert alert-info small">
                            Première connexion : vous devez définir un nouveau mot de passe pour accéder à l'application.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('modifier.mot.passe') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="cpwd">Ancien mot de passe</label>
                            <input type="password" class="form-control" id="cpwd" name="cpwd" required>
                            @error('cpwd') <label class="error">{{ $message }}</label> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="npwd">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="npwd" name="npwd" minlength="8" required>
                            <small class="text-muted">8 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial.</small>
                            @error('npwd') <br><label class="error">{{ $message }}</label> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vpwd">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" id="vpwd" name="vpwd" minlength="8" required>
                            @error('vpwd') <label class="error">{{ $message }}</label> @enderror
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
