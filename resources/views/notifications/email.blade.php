<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $sujet }}</title></head>
<body style="margin:0; padding:0; background:#f2f4f7; font-family: Arial, Helvetica, sans-serif; color:#101828;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7; padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden;">
            <tr><td style="background:{{ $gabarit['couleur'] }}; padding:20px 28px; color:#ffffff;">
                @if ($gabarit['logo'])<img src="{{ $gabarit['logo'] }}" alt="" style="max-height:44px; vertical-align:middle; margin-right:10px;">@endif
                <span style="font-size:18px; font-weight:bold; vertical-align:middle;">{{ $gabarit['entete'] }}</span>
            </td></tr>
            <tr><td style="padding:28px; font-size:15px; line-height:1.6;">
                @if ($nom)<p style="margin-top:0;">Bonjour {{ $nom }},</p>@endif
                {!! $contenu !!}
                @if ($gabarit['signature'])<p style="margin-top:24px;">{!! nl2br(e($gabarit['signature'])) !!}</p>@endif
            </td></tr>
            <tr><td style="padding:16px 28px; background:#f9fafb; color:#667085; font-size:12px; text-align:center;">{!! nl2br(e($gabarit['pied'])) !!}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
