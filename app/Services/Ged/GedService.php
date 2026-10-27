<?php

namespace App\Services\Ged;

use App\Models\Document;
use App\Models\QrJeton;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\QrCode;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GED : stockage privé de tout fichier (PDF, images, Excel, Word, archives…) rattaché à un objet métier,
 * avec empreinte SHA-256, versions et signature QR. Les documents générés (reçus, quittances, contrats…)
 * y sont archivés automatiquement à leur émission.
 */
class GedService
{
    /** Formats exécutables ou interprétables refusés (sécurité du serveur et des postes qui ouvriront le fichier). */
    public const INTERDITS = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif',
        'sh', 'bash', 'ps1', 'vbs', 'vbe', 'js', 'mjs', 'jse', 'wsf', 'jar', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'hta', 'dll', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp'];

    public function __construct(protected Numerotation $numerotation, protected Parametres $parametres)
    {
    }

    /**
     * Téléversement d'un fichier ; avec $remplace, le fichier devient la nouvelle version de ce document.
     */
    public function televerser(UploadedFile $fichier, array $meta = [], ?Model $objet = null, ?Document $remplace = null): Document
    {
        $extension = Str::lower($fichier->getClientOriginalExtension());
        if (in_array($extension, self::INTERDITS, true) || in_array(Str::lower($fichier->extension()), self::INTERDITS, true)) {
            throw new DomainException("Format « .$extension » refusé pour des raisons de sécurité (fichier exécutable ou script).");
        }
        $maxMo = (int) $this->parametres->get('ged.taille_max_mo');
        if ($maxMo > 0 && $fichier->getSize() > $maxMo * 1048576) {
            throw new DomainException("Fichier trop volumineux (maximum $maxMo Mo).");
        }

        $chemin = $fichier->storeAs($this->dossier(), Str::uuid() . ($extension ? '.' . $extension : ''), 'local');

        return $this->enregistrer([
            'titre' => $meta['titre'] ?? ($remplace?->titre ?? pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME)),
            'categorie' => $meta['categorie'] ?? ($remplace?->categorie ?? 'AUTRE'),
            'description' => $meta['description'] ?? $remplace?->description,
            'confidentiel' => (bool) ($meta['confidentiel'] ?? $remplace?->confidentiel ?? false),
            'chemin' => $chemin, 'nom_original' => Str::limit($fichier->getClientOriginalName(), 250, ''), 'extension' => $extension ?: null,
            'mime' => $fichier->getMimeType(), 'taille' => $fichier->getSize(), 'empreinte_sha256' => hash_file('sha256', $fichier->getRealPath()),
        ], $objet ?? $remplace?->documentable, $remplace);
    }

    /** Archivage d'un document généré par l'application (contenu binaire), relié à sa signature QR. */
    public function archiverGenere(string $contenu, string $nomFichier, string $categorie, string $titre, ?Model $objet, ?QrJeton $jeton, string $mime = 'application/pdf'): Document
    {
        $extension = Str::lower(pathinfo($nomFichier, PATHINFO_EXTENSION)) ?: 'pdf';
        $chemin = $this->dossier() . '/' . Str::uuid() . '.' . $extension;
        Storage::disk('local')->put($chemin, $contenu);

        $document = $this->enregistrer([
            'titre' => $titre, 'categorie' => $categorie, 'source' => 'genere', 'chemin' => $chemin, 'nom_original' => $nomFichier,
            'extension' => $extension, 'mime' => $mime, 'taille' => strlen($contenu), 'empreinte_sha256' => hash('sha256', $contenu),
            'qr_jeton_id' => $jeton?->id,
        ], $objet);

        $jeton?->forceFill(['document_id' => $document->id, 'empreinte_sha256' => $document->empreinte_sha256])->save();

        return $document;
    }

    public function contenu(Document $document): ?string
    {
        return Storage::disk($document->disque)->exists($document->chemin) ? Storage::disk($document->disque)->get($document->chemin) : null;
    }

    /** Contrôle d'intégrité : le fichier stocké a-t-il toujours son empreinte d'origine ? */
    public function integre(Document $document): bool
    {
        $contenu = $this->contenu($document);

        return $contenu !== null && hash('sha256', $contenu) === $document->empreinte_sha256;
    }

    /** Tiers concerné par un objet métier (pour retrouver tous les documents d'un client). */
    public static function tiersDe(?Model $objet): ?int
    {
        if (!$objet) {
            return null;
        }
        if ($objet instanceof Tiers) {
            return $objet->id;
        }
        foreach (['tiers_id', 'locataire_id', 'acquereur_id', 'coproprietaire_id', 'proprietaire_id', 'beneficiaire_id'] as $champ) {
            if (!empty($objet->{$champ})) {
                return (int) $objet->{$champ};
            }
        }

        return null;
    }

    protected function enregistrer(array $donnees, ?Model $objet, ?Document $remplace = null): Document
    {
        $document = Document::create($donnees + [
            'numero' => $remplace?->numero ? $remplace->numero . '-V' . ($remplace->version + 1) : $this->numerotation->suivant('ged'),
            'documentable_type' => $objet?->getMorphClass(), 'documentable_id' => $objet?->getKey(), 'tiers_id' => self::tiersDe($objet) ?? $remplace?->tiers_id,
            'version' => $remplace ? $remplace->version + 1 : 1, 'document_parent_id' => $remplace?->document_parent_id ?? $remplace?->id,
            'user_id' => Auth::id(),
        ]);

        // chaque document a sa signature QR : la page de vérification compare l'empreinte d'un fichier présenté
        if (!$document->qr_jeton_id) {
            $jeton = QrCode::jetonPour($document, 'DOCUMENT_GED', $document->numero, ['libelle' => $document->titre, 'empreinte' => $document->empreinte_sha256]);
            $jeton->forceFill(['document_id' => $document->id])->save();
            $document->forceFill(['qr_jeton_id' => $jeton->id])->save();
        }

        return $document;
    }

    protected function dossier(): string
    {
        return 'ged/' . (app(\App\Support\AgenceCourante::class)->id() ?? 0) . '/' . now()->format('Y/m');
    }
}
