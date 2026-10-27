<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>@yield('titre')</title>
    <style>
        @page { margin: 28px 34px 60px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
        .entete { border-bottom: 2px solid {{ $agence?->couleur ?? '#01619b' }}; padding-bottom: 8px; margin-bottom: 16px; }
        .entete table { width: 100%; }
        .agence-nom { font-size: 15px; font-weight: bold; color: {{ $agence?->couleur ?? '#01619b' }}; }
        .petit { font-size: 9px; color: #666; }
        h1 { font-size: 15px; text-align: center; text-transform: uppercase; margin: 6px 0 14px; letter-spacing: .5px; }
        h2 { font-size: 11.5px; color: {{ $agence?->couleur ?? '#01619b' }}; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin: 14px 0 6px; }
        table.grille { width: 100%; border-collapse: collapse; }
        table.grille th { background: #f1f3f6; text-align: left; font-size: 9.5px; text-transform: uppercase; }
        table.grille th, table.grille td { border: 1px solid #d6d9de; padding: 5px 6px; }
        .droite { text-align: right; }
        .centre { text-align: center; }
        .total td { font-weight: bold; background: #f7f7f9; }
        .cadre { border: 1px solid #d6d9de; padding: 8px 10px; border-radius: 4px; }
        .montant-lettres { font-style: italic; }
        .signatures { width: 100%; margin-top: 30px; }
        .signatures td { width: 50%; vertical-align: top; height: 80px; }
        .pied { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #777; border-top: 1px solid #ddd; padding-top: 4px; }
        .tampon { display: inline-block; border: 2px solid #1a8f3c; color: #1a8f3c; padding: 3px 10px; font-weight: bold; transform: rotate(-8deg); }
        .annule { border-color: #c62828; color: #c62828; }
    </style>
</head>
<body>
    <div class="pied">
        {{ $agence?->raison_sociale }} — {{ collect([$agence?->adresse, $agence?->ville])->filter()->implode(', ') }}
        {{ $agence?->telephone ? '— Tél. ' . $agence->telephone : '' }} {{ $agence?->email ? '— ' . $agence->email : '' }}
        {{ $agence?->rccm ? '— RCCM ' . $agence->rccm : '' }} {{ $agence?->compte_contribuable ? '— CC ' . $agence->compte_contribuable : '' }}
    </div>

    <div class="entete">
        <table>
            <tr>
                <td>
                    @if($agence?->logo && file_exists(storage_path('app/public/' . $agence->logo)))
                        <img src="{{ storage_path('app/public/' . $agence->logo) }}" alt="" style="height: 46px">
                    @endif
                    <div class="agence-nom">{{ $agence?->raison_sociale }}</div>
                    <div class="petit">{{ collect([$agence?->adresse, $agence?->ville])->filter()->implode(', ') }}</div>
                </td>
                <td class="droite petit">
                    @yield('reference')<br>
                    Édité le {{ now()->format('d/m/Y à H:i') }}
                </td>
            </tr>
        </table>
    </div>

    @yield('contenu')

    @isset($signature)
        {{-- signature QR : authenticité vérifiable par toute personne qui reçoit le document --}}
        <table style="width: 100%; margin-top: 18px; page-break-inside: avoid; border-top: 1px dashed #ccc; padding-top: 6px">
            <tr>
                <td style="width: 92px; vertical-align: top"><img src="{{ $signature['image'] }}" alt="QR code de vérification" style="width: 84px; height: 84px"></td>
                <td class="petit" style="vertical-align: middle">
                    <strong>Document signé électroniquement par {{ $agence?->raison_sociale }}</strong><br>
                    Pour vérifier son authenticité, scannez ce QR code ou rendez-vous sur :<br>
                    {{ $signature['url'] }}<br>
                    Code de vérification : <strong>{{ $signature['code'] }}</strong> — émis le {{ $signature['jeton']->emis_at->format('d/m/Y à H:i') }}
                </td>
            </tr>
        </table>
    @endisset
</body>
</html>
