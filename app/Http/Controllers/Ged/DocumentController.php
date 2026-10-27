<?php

namespace App\Http\Controllers\Ged;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Ged\GedService;
use App\Support\Export;
use App\Support\Pdf;
use App\Support\QrCode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * GED : tous les documents de l'agence (téléversés ou générés), rattachés aux dossiers métier.
 */
class DocumentController extends Controller
{
    /** Objets auxquels un document peut être rattaché (alias de la morph map). */
    public const OBJETS = [
        'tiers' => 'Tiers', 'bien' => 'Bien', 'bail' => 'Bail', 'vente' => 'Contrat de vente', 'mandat' => 'Mandat', 'encaissement' => 'Encaissement',
        'depense' => 'Dépense', 'reversement' => 'Reversement', 'intervention' => 'Intervention', 'copropriete' => 'Copropriété', 'assemblee' => 'Assemblée',
        'appel_fonds' => 'Appel de fonds', 'etat_lieux' => 'État des lieux', 'decompte_sortie' => 'Décompte de sortie', 'decompte_ligne' => 'Justificatif de sortie',
        'employe' => 'Employé', 'programme' => 'Programme', 'loyer' => 'Loyer',
    ];

    public function __construct(protected GedService $ged)
    {
    }

    public function index(Request $request)
    {
        $query = Document::with(['tiers', 'auteur'])
            ->when(!$request->user()->can('ged-confidentiel'), fn ($q) => $q->where('confidentiel', false))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('titre', 'ilike', '%' . $request->q . '%')
                ->orWhere('numero', 'ilike', '%' . $request->q . '%')->orWhere('nom_original', 'ilike', '%' . $request->q . '%')))
            ->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('objet'), fn ($q) => $q->where('documentable_type', $request->objet))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('tiers_id'), fn ($q) => $q->where('tiers_id', $request->integer('tiers_id')))
            ->when($request->filled('format_fichier'), fn ($q) => $q->whereIn('extension', match ($request->format_fichier) {
                'pdf' => ['pdf'], 'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'bmp', 'tif', 'tiff'],
                'tableur' => ['xls', 'xlsx', 'csv', 'ods'], 'texte' => ['doc', 'docx', 'odt', 'rtf', 'txt'], default => [$request->format_fichier],
            }))
            ->latest('id');

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            return Export::telecharger($request->query('format'), 'Documents (GED)', ['N°', 'Titre', 'Catégorie', 'Rattaché à', 'Tiers', 'Fichier', 'Taille', 'Source', 'Ajouté le', 'Empreinte SHA-256'],
                $query->limit(20000)->get()->map(fn ($d) => [$d->numero, $d->titre, Referentiel::libelle('categorie_document', $d->categorie), self::OBJETS[$d->documentable_type] ?? '',
                    $d->tiers?->nom_complet, $d->nom_original, $d->tailleLisible(), $d->source === 'genere' ? 'Généré' : 'Téléversé', $d->created_at->format('d/m/Y H:i'), $d->empreinte_sha256])->all());
        }

        return view('ged.index', [
            'documents' => $query->paginate(30)->withQueryString(),
            'categories' => Referentiel::options('categorie_document'),
            'objets' => self::OBJETS,
            'volume' => Document::selectRaw('count(*) as n, coalesce(sum(taille), 0) as octets')->first(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'fichiers' => ['required', 'array', 'max:20'],
            'fichiers.*' => ['required', 'file'],
            'titre' => ['nullable', 'string', 'max:255'],
            'categorie' => ['required', Rule::in(Referentiel::options('categorie_document')->keys())],
            'description' => ['nullable', 'string', 'max:2000'],
            'confidentiel' => ['nullable', 'boolean'],
            'documentable_type' => ['nullable', Rule::in(array_keys(self::OBJETS))],
            'documentable_id' => ['nullable', 'required_with:documentable_type', 'integer'],
        ]);
        $objet = $this->objet($donnees['documentable_type'] ?? null, $donnees['documentable_id'] ?? null);

        $documents = DB::transaction(fn () => collect($donnees['fichiers'])->map(fn ($f) => $this->ged->televerser($f, [
            'titre' => count($donnees['fichiers']) === 1 ? ($donnees['titre'] ?? null) : null, 'categorie' => $donnees['categorie'],
            'description' => $donnees['description'] ?? null, 'confidentiel' => $request->boolean('confidentiel') && $request->user()->can('ged-confidentiel'),
        ], $objet)));
        Audit::logSave(['action' => 'GED AJOUT', 'code_piece' => $documents->pluck('numero')->implode(', '), 'menu' => 'GED', 'etat' => 'Succès', 'objet' => 'GED']);

        return back()->with('success', $documents->count() . ' document(s) ajouté(s) : ' . $documents->pluck('numero')->implode(', ') . '.');
    }

    public function show(Request $request, Document $document)
    {
        $this->autoriser($request, $document);

        return view('ged.show', [
            'document' => $document->load(['tiers', 'auteur', 'documentable', 'jeton']),
            'versions' => Document::where(fn ($q) => $q->whereKey($document->document_parent_id ?? $document->id)
                ->orWhere('document_parent_id', $document->document_parent_id ?? $document->id))->orderByDesc('version')->get(),
            'integre' => $this->ged->integre($document),
            'signature' => $document->jeton ? QrCode::signature($document->jeton) : null,
            'verifications' => $document->jeton ? DB::table('qr_verifications')->where('qr_jeton_id', $document->jeton->id)->latest('verifie_at')->limit(10)->get() : collect(),
        ]);
    }

    public function telecharger(Request $request, Document $document)
    {
        return $this->servir($request, $document, 'attachment');
    }

    public function apercu(Request $request, Document $document)
    {
        abort_unless($document->apercuPossible(), 404);

        return $this->servir($request, $document, 'inline');
    }

    public function version(Request $request, Document $document)
    {
        $this->autoriser($request, $document);
        $request->validate(['fichier' => ['required', 'file']]);
        $nouvelle = $this->ged->televerser($request->file('fichier'), [], null, $document);

        return redirect()->route('ged.show', $nouvelle)->with('success', 'Version ' . $nouvelle->version . ' enregistrée.');
    }

    /** Attestation d'archivage signée QR : preuve imprimable pour tout format de fichier (Excel, image…). */
    public function attestation(Request $request, Document $document)
    {
        $this->autoriser($request, $document);

        return Pdf::telecharger('pdf.attestation_ged', ['document' => $document->load(['tiers', 'auteur'])], 'attestation-' . $document->numero,
            objet: $document, type: 'ATTESTATION_GED', etat: $document->empreinte_sha256);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->autoriser($request, $document);
        $document->delete();
        Audit::logSave(['action' => 'GED SUPPRESSION', 'code_piece' => $document->numero, 'menu' => 'GED', 'etat' => 'Succès', 'objet' => 'GED']);

        return back()->with('success', 'Document ' . $document->numero . ' supprimé (conservé dans la corbeille de la base).');
    }

    protected function servir(Request $request, Document $document, string $disposition)
    {
        $this->autoriser($request, $document);
        $contenu = $this->ged->contenu($document);
        abort_if($contenu === null, 404, 'Fichier introuvable sur le stockage.');

        return response($contenu, 200, [
            'Content-Type' => $disposition === 'inline' ? $document->mime : 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . addcslashes($document->nom_original, '"\\') . '"',
            'X-Content-Type-Options' => 'nosniff',
            // aperçu limité aux PDF et images sûres (Document::APERCU), les autres formats sont toujours téléchargés
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; object-src 'self'",
        ]);
    }

    protected function autoriser(Request $request, Document $document): void
    {
        abort_if($document->confidentiel && !$request->user()->can('ged-confidentiel'), 403, 'Document confidentiel.');
    }

    protected function objet(?string $type, ?int $id)
    {
        if (!$type) {
            return null;
        }
        $classe = Relation::getMorphedModel($type) ?? abort(422);

        return $classe::findOrFail($id);   // portée agence : un objet d'une autre agence renvoie 404
    }
}
