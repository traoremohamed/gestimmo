{{-- Formulaire d'un canal. Variables : $code, $canal, $def --}}
@php
    $fournisseurCourant = old('fournisseur', $canal->fournisseur ?: array_key_first($def['fournisseurs']));
    $placeholderTest = ['EMAIL' => 'adresse@exemple.com', 'SMS' => '0700000000', 'WHATSAPP' => '0700000000'][$code] ?? '';
@endphp
<div class="row g-4">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('integrations.enregistrer', $code) }}" autocomplete="off">
            @csrf
            <div class="d-flex flex-wrap gap-4 mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="actif" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="actif" value="1" id="actif-{{ $code }}" @checked($canal->actif)>
                    <label class="form-check-label" for="actif-{{ $code }}"><strong>Canal actif</strong><div class="small text-muted">Les messages de l'application passent par ce canal.</div></label>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="sandbox" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="sandbox" value="1" id="sandbox-{{ $code }}" @checked($canal->sandbox)>
                    <label class="form-check-label" for="sandbox-{{ $code }}"><strong>Mode sandbox</strong><div class="small text-muted">Simule les envois (journalisés, non transmis).</div></label>
                </div>
            </div>

            <h6 class="text-uppercase text-muted small">Fournisseur</h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <select name="fournisseur" class="form-select choix-fournisseur" aria-label="Fournisseur">
                        @foreach ($def['fournisseurs'] as $f => $fd)<option value="{{ $f }}" @selected($fournisseurCourant === $f)>{{ $fd['libelle'] }}</option>@endforeach
                    </select>
                </div>
            </div>

            @foreach ($def['fournisseurs'] as $f => $fd)
                <div class="groupe-fournisseur {{ $f === $fournisseurCourant ? '' : 'd-none' }}" data-fournisseur="{{ $f }}">
                    <h6 class="text-uppercase text-muted small">Paramètres</h6>
                    <div class="row g-3 mb-3">
                        @forelse (\App\Services\Integration\CatalogueCanaux::champs($code, $f) as $nom => [$label, $type, $secret, $aide, $defaut])
                            @php
                                $valeur = old('valeurs.' . $nom, $secret ? '' : ($canal->parametres[$nom] ?? (is_array($defaut) ? null : $defaut)));
                                $idChamp = "c-$code-$f-$nom";
                            @endphp
                            <div class="col-md-{{ $type === 'number' ? 3 : 6 }}">
                                <label class="form-label" for="{{ $idChamp }}">{{ $label }}</label>
                                @if ($type === 'select')
                                    <select name="valeurs[{{ $nom }}]" id="{{ $idChamp }}" class="form-select" @disabled($f !== $fournisseurCourant)>
                                        @foreach ($defaut as $k => $l)<option value="{{ $k }}" @selected((string) $valeur === (string) $k)>{{ $l }}</option>@endforeach
                                    </select>
                                @else
                                    <input type="{{ $type === 'password' ? 'password' : ($type === 'number' ? 'number' : 'text') }}" name="valeurs[{{ $nom }}]" id="{{ $idChamp }}" class="form-control"
                                           value="{{ $secret ? '' : $valeur }}" @disabled($f !== $fournisseurCourant) autocomplete="new-password"
                                           placeholder="{{ $secret && $canal->exists && $canal->aSecret($nom) ? '•••••••• (enregistré — laisser vide pour conserver)' : '' }}">
                                @endif
                                @if ($aide)<div class="form-text">{{ $aide }}</div>@endif
                            </div>
                        @empty
                            <div class="col-12 text-muted small">Aucun paramètre : les messages sont seulement écrits dans le journal de l'application.</div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            @can('integrations-edit')<button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button>@endcan
        </form>
    </div>
    <div class="col-xl-4">
        <div class="border rounded-3 p-3 bg-light-subtle">
            <h6><i class="ti ti-flask text-danger"></i> Tester le canal</h6>
            <p class="small text-muted">Envoie un message de test avec les paramètres enregistrés. Enregistrez d'abord vos modifications.</p>
            <form method="POST" action="{{ route('integrations.tester', $code) }}">
                @csrf
                <label class="form-label" for="test-{{ $code }}">Destinataire</label>
                <input type="text" name="destinataire" id="test-{{ $code }}" class="form-control mb-2" placeholder="{{ $placeholderTest }}" value="{{ $code === 'EMAIL' ? auth()->user()->email : auth()->user()->cel_users }}" required>
                @can('integrations-edit')<button class="btn btn-outline-primary w-100" @disabled(!$canal->exists)><i class="ti ti-send"></i> Envoyer un test</button>@endcan
            </form>
            @if ($canal->dernier_test_at)
                <div class="small mt-3 {{ $canal->dernier_test_statut === 'SUCCES' ? 'text-success' : 'text-danger' }}">
                    <i class="ti {{ $canal->dernier_test_statut === 'SUCCES' ? 'ti-circle-check' : 'ti-alert-circle' }}"></i>
                    Dernier test le {{ $canal->dernier_test_at->format('d/m/Y H:i') }} : {{ \Illuminate\Support\Str::limit((string) $canal->dernier_test_message, 200) }}</div>
            @endif
        </div>
    </div>
</div>
