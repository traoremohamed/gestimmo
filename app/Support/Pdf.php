<?php

namespace App\Support;

use App\Models\Document;
use App\Models\QrJeton;
use App\Services\Ged\GedService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Database\Eloquent\Model;

/**
 * Documents PDF (reçus, quittances, contrats, avis d'appel de fonds…) avec l'en-tête de l'agence.
 *
 * Avec un objet métier, le document porte une signature QR (vérifiable sur /verifier/{jeton}) et est archivé dans la GED.
 * Tant que l'objet n'a pas changé ($etat), une réimpression renvoie l'original archivé à l'identique ;
 * s'il a changé, une nouvelle version est archivée et l'ancienne signature est révoquée.
 */
class Pdf
{
    public static function telecharger(string $vue, array $donnees, string $nomFichier, string $orientation = 'portrait',
        ?Model $objet = null, ?string $type = null, ?string $etat = null, ?string $titre = null)
    {
        $nom = str($nomFichier)->slug() . '.pdf';
        [$contenu] = self::generer($vue, $donnees, $nom, $orientation, $objet, $type, $etat, $titre);

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '"',
        ]);
    }

    /**
     * @return array{0: string, 1: ?Document} contenu PDF et document archivé
     */
    public static function generer(string $vue, array $donnees, string $nomFichier, string $orientation = 'portrait',
        ?Model $objet = null, ?string $type = null, ?string $etat = null, ?string $titre = null): array
    {
        $donnees['agence'] ??= agence();
        if (!$objet) {
            return [self::rendre($vue, $donnees, $orientation), null];
        }

        $type ??= strtoupper(class_basename($objet));
        $etat ??= (string) $objet->updated_at?->getTimestamp();
        $jeton = QrCode::jetonPour($objet, $type, null, ['libelle' => $titre, 'montant' => $objet->montant ?? null]);
        $archive = $jeton->document_id ? Document::withoutGlobalScopes()->find($jeton->document_id) : null;
        $ged = app(GedService::class);

        if ($archive && $archive->etat_source === $etat && ($original = $ged->contenu($archive)) !== null) {
            return [$original, $archive];
        }
        if ($archive) {                                   // le document a changé : nouvelle version, ancienne signature révoquée
            QrCode::revoquer($jeton, 'Document mis à jour, remplacé par une nouvelle version');
            $jeton = QrCode::jetonPour($objet, $type, null, ['libelle' => $titre, 'montant' => $objet->montant ?? null]);
        }

        $contenu = self::rendre($vue, $donnees + ['signature' => QrCode::signature($jeton)], $orientation);
        if (!app(AgenceCourante::class)->existe()) {
            return [$contenu, null];
        }
        $document = $ged->archiverGenere($contenu, $nomFichier, self::categorie($type), $titre ?? self::titre($type, $objet), $objet, $jeton);
        $document->forceFill(['etat_source' => $etat, 'version' => ($archive?->version ?? 0) + 1, 'document_parent_id' => $archive?->document_parent_id ?? $archive?->id])->save();

        return [$contenu, $document];
    }

    protected static function rendre(string $vue, array $donnees, string $orientation): string
    {
        return DomPdf::loadView($vue, $donnees)
            ->setPaper('a4', $orientation)
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])
            ->output();
    }

    /** Catégorie GED d'un type de document généré. */
    public static function categorie(string $type): string
    {
        return match ($type) {
            'RECU' => 'RECU', 'QUITTANCE' => 'QUITTANCE', 'CONTRAT_BAIL', 'CONTRAT_VENTE' => 'CONTRAT', 'DECOMPTE_SORTIE' => 'DECOMPTE',
            'ETAT_LIEUX' => 'ETAT_LIEUX', 'BULLETIN', 'LIVRE_PAIE' => 'BULLETIN', 'LETTRE_RELANCE' => 'COURRIER', 'EXPORT' => 'COMPTABILITE',
            default => 'AUTRE',
        };
    }

    protected static function titre(string $type, Model $objet): string
    {
        return (config("immobilier.referentiels.categorie_document.1.$type") ?? str_replace('_', ' ', ucfirst(strtolower($type)))) . ' ' . ($objet->numero ?? '#' . $objet->getKey());
    }

    /** Le jeton QR courant d'un objet (pour l'afficher à l'écran). */
    public static function jeton(Model $objet, string $type): ?QrJeton
    {
        return QrJeton::where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())->where('type_document', $type)
            ->whereNull('revoque_at')->latest('id')->first();
    }
}
