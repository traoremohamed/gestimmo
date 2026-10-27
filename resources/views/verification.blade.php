<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Vérification d'un document</title>
    <style>
        :root { --vert: #1a8f3c; --rouge: #c62828; --gris: #667085; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; background: #f2f4f7; color: #101828; }
        main { max-width: 560px; margin: 0 auto; padding: 24px 16px; }
        .carte { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
        .statut { display: flex; gap: 12px; align-items: center; padding: 14px 16px; border-radius: 10px; font-weight: 600; margin-bottom: 18px; }
        .ok { background: #e7f6ec; color: var(--vert); } .ko { background: #fdecec; color: var(--rouge); }
        .rond { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 20px; flex: none; }
        .ok .rond { background: var(--vert); } .ko .rond { background: var(--rouge); }
        dl { display: grid; grid-template-columns: 40% 60%; gap: 8px 0; margin: 0; font-size: 14px; }
        dt { color: var(--gris); } dd { margin: 0; overflow-wrap: anywhere; }
        h1 { font-size: 18px; margin: 0 0 16px; } h2 { font-size: 15px; margin: 22px 0 8px; }
        p, .petit { font-size: 13px; color: var(--gris); }
        input[type=file] { width: 100%; margin: 6px 0 10px; }
        button { background: #0d6efd; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font-size: 14px; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
<main>
    <div class="carte">
        <h1>Vérification d'un document</h1>

        @if($resultat === \App\Support\QrCode::VALIDE)
            <div class="statut ok"><span class="rond">✓</span>Document authentique, émis par {{ $qr->agence?->raison_sociale ?? config('rbac.app_name') }}</div>
        @elseif($resultat === \App\Support\QrCode::REVOQUE)
            <div class="statut ko"><span class="rond">!</span>Document révoqué{{ $qr->motif_revocation ? ' : ' . $qr->motif_revocation : '' }}</div>
        @else
            <div class="statut ko"><span class="rond">✕</span>Code inconnu : ce document n'a pas été émis par l'application</div>
        @endif

        @if($conforme === true)
            <div class="statut ok"><span class="rond">✓</span>Le fichier présenté est identique à l'original archivé</div>
        @elseif($conforme === false)
            <div class="statut ko"><span class="rond">✕</span>Le fichier présenté est différent de l'original (modifié ou autre document)</div>
        @endif

        @if($qr)
            <dl>
                <dt>Type</dt><dd>{{ ucfirst(strtolower(str_replace('_', ' ', $qr->type_document))) }}</dd>
                @if($qr->reference)<dt>Référence</dt><dd>{{ $qr->reference }}</dd>@endif
                @if($qr->libelle)<dt>Libellé</dt><dd>{{ $qr->libelle }}</dd>@endif
                @if($qr->montant !== null)<dt>Montant</dt><dd>{{ \App\Support\Montant::formater($qr->montant) }}</dd>@endif
                <dt>Émis le</dt><dd>{{ $qr->emis_at->format('d/m/Y à H:i') }}</dd>
                <dt>Code</dt><dd>{{ strtoupper(substr($qr->jeton, 0, 8)) }}</dd>
                @if($qr->empreinte_sha256)<dt>Empreinte</dt><dd class="petit">{{ $qr->empreinte_sha256 }}</dd>@endif
            </dl>

            @if($qr->empreinte_sha256 && $resultat !== \App\Support\QrCode::INCONNU)
                <h2>Contrôler un fichier</h2>
                <p>Déposez le fichier reçu (PDF, image, Excel…) : son empreinte est comparée à celle de l'original. Le fichier n'est pas conservé.</p>
                <form method="POST" action="{{ route('verifier.comparer', $jetonPresente) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="fichier" class="petit">Fichier à contrôler</label>
                    <input type="file" name="fichier" id="fichier" required>
                    <button>Comparer à l'original</button>
                </form>
            @endif
        @endif
    </div>
    <p style="text-align: center">Vérification enregistrée le {{ now()->format('d/m/Y à H:i') }}.</p>
</main>
</body>
</html>
