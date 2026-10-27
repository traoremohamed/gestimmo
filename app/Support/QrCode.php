<?php

namespace App\Support;

use App\Models\QrJeton;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as Generateur;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signature QR des documents (repris du SIH) : le QR ne contient qu'une URL HTTPS avec un jeton aléatoire opaque,
 * aucune donnée personnelle. La page publique /verifier/{jeton} contrôle existence et révocation, compare
 * l'empreinte d'un fichier présenté à celle de l'original archivé, et journalise chaque vérification.
 */
class QrCode
{
    public const VALIDE = 'VALIDE';
    public const REVOQUE = 'REVOQUE';
    public const INCONNU = 'INCONNU';

    /** Jeton actif du document, créé à la première émission (réimpression = même jeton). */
    public static function jetonPour(?Model $objet, string $type, ?string $reference = null, array $infos = []): QrJeton
    {
        if ($objet) {
            $existant = QrJeton::where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
                ->where('type_document', $type)->whereNull('revoque_at')->latest('id')->first();
            if ($existant) {
                return $existant;
            }
        }

        return QrJeton::create([
            'agence_id' => $objet->agence_id ?? app(AgenceCourante::class)->id(),
            'jeton' => Str::random(40),
            'type_document' => $type,
            'objet_type' => $objet?->getMorphClass(),
            'objet_id' => $objet?->getKey(),
            'reference' => $reference ?? ($objet->numero ?? null),
            'libelle' => $infos['libelle'] ?? null,
            'montant' => $infos['montant'] ?? null,
            'empreinte_sha256' => $infos['empreinte'] ?? null,
            'emis_at' => now(),
        ]);
    }

    public static function url(QrJeton $jeton): string
    {
        return route('verifier.document', $jeton->jeton);
    }

    /** Image PNG en data URI (affichable par dompdf, sans ressource distante). */
    public static function image(string $contenu, int $echelle = 4): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => $echelle,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
        ]);

        return (new Generateur($options))->render($contenu);
    }

    /** Bloc à imprimer sur un document : image du QR, URL et code court de vérification. */
    public static function signature(QrJeton $jeton): array
    {
        $url = self::url($jeton);

        return ['image' => self::image($url), 'url' => $url, 'code' => strtoupper(substr($jeton->jeton, 0, 8)), 'jeton' => $jeton];
    }

    public static function revoquer(QrJeton $jeton, string $motif): void
    {
        $jeton->update(['revoque_at' => now(), 'motif_revocation' => $motif]);
    }

    /**
     * Vérification d'un jeton scanné (et, si fourni, conformité d'un fichier à l'original archivé).
     *
     * @return array{0: string, 1: ?QrJeton, 2: ?bool} résultat, jeton, conformité du fichier
     */
    public static function verifier(string $presente, ?string $empreinteFichier = null): array
    {
        $jeton = QrJeton::where('jeton', $presente)->first();
        $resultat = match (true) {
            $jeton === null => self::INCONNU,
            $jeton->revoque_at !== null => self::REVOQUE,
            default => self::VALIDE,
        };
        $conforme = $jeton && $empreinteFichier !== null
            ? in_array($empreinteFichier, array_filter([$jeton->empreinte_sha256, $jeton->document?->empreinte_sha256]), true)
            : null;

        DB::table('qr_verifications')->insert([
            'qr_jeton_id' => $jeton?->id, 'jeton_presente' => substr($presente, 0, 64),
            'resultat' => $conforme === null ? $resultat : ($conforme ? 'CONFORME' : 'NON_CONFORME'),
            'ip' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 500), 'verifie_at' => now(),
        ]);
        if ($jeton) {
            $jeton->forceFill(['nb_verifications' => $jeton->nb_verifications + 1, 'derniere_verification_at' => now()])->saveQuietly();
        }

        return [$resultat, $jeton, $conforme];
    }
}
