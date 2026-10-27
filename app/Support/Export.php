<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export d'un tableau (états, listes, écritures comptables…) en Excel (.xlsx), CSV ou PDF.
 * Le PDF porte une signature QR : l'empreinte des données exportées est enregistrée et vérifiable.
 *
 * Valeurs : nombres (int/float) écrits comme nombres dans Excel ; le reste comme texte.
 */
class Export
{
    public const FORMATS = ['xlsx' => 'Excel', 'pdf' => 'PDF', 'csv' => 'CSV'];

    /**
     * @param array<int, string> $colonnes
     * @param iterable<int, array> $lignes
     * @param array<int, mixed>|null $total ligne de total
     */
    public static function telecharger(string $format, string $titre, array $colonnes, iterable $lignes, ?array $total = null,
        ?string $sousTitre = null, string $orientation = 'landscape'): Response
    {
        $nom = str($titre)->slug() . '-' . now()->format('Ymd-Hi');
        $lignes = is_array($lignes) ? $lignes : iterator_to_array($lignes, false);

        return match ($format) {
            'xlsx' => self::xlsx($nom . '.xlsx', $titre, $colonnes, $lignes, $total, $sousTitre),
            'pdf' => self::pdf($nom . '.pdf', $titre, $colonnes, $lignes, $total, $sousTitre, $orientation),
            default => self::csv($nom . '.csv', $colonnes, $lignes, $total),
        };
    }

    protected static function xlsx(string $nom, string $titre, array $colonnes, array $lignes, ?array $total, ?string $sousTitre): Response
    {
        $fichier = tempnam(sys_get_temp_dir(), 'xlsx');
        $options = new Options();
        foreach ($colonnes as $i => $c) {
            $options->setColumnWidth(max(10, min(45, mb_strlen((string) $c) + 6)), $i + 1);
        }
        $writer = new Writer($options);
        $writer->openToFile($fichier);
        $writer->getCurrentSheet()->setName(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $titre), 0, 31));

        $gras = (new Style())->setFontBold();
        $entete = (new Style())->setFontBold()->setBackgroundColor('E9EEF5');
        $nombre = (new Style())->setFormat('#,##0.##');

        $writer->addRow(Row::fromValues([$titre], (new Style())->setFontBold()->setFontSize(13)));
        $writer->addRow(Row::fromValues([trim((agence()?->raison_sociale ?? '') . ' — ' . ($sousTitre ? $sousTitre . ' — ' : '') . 'édité le ' . now()->format('d/m/Y H:i'), ' —')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($colonnes, $entete));
        foreach ($lignes as $ligne) {
            $writer->addRow(self::ligneXlsx($ligne, $nombre));
        }
        if ($total) {
            $writer->addRow(self::ligneXlsx($total, (new Style())->setFontBold()->setFormat('#,##0.##'), $gras));
        }
        $writer->close();

        return response()->download($fichier, $nom, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    protected static function ligneXlsx(array $ligne, Style $nombre, ?Style $texte = null): Row
    {
        $valeurs = array_map(fn ($v) => is_numeric($v) && !is_string($v) ? $v : self::texte($v), array_values($ligne));
        $styles = [];
        foreach ($valeurs as $i => $v) {
            if (is_int($v) || is_float($v)) {
                $styles[$i] = $nombre;
            }
        }

        return Row::fromValuesWithStyles($valeurs, $texte, $styles);
    }

    protected static function csv(string $nom, array $colonnes, array $lignes, ?array $total): Response
    {
        return response()->streamDownload(function () use ($colonnes, $lignes, $total) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");   // BOM : accents corrects sous Excel
            fputcsv($sortie, $colonnes, ';');
            foreach (array_merge($lignes, $total ? [$total] : []) as $ligne) {
                fputcsv($sortie, array_map(fn ($v) => is_float($v) || is_int($v) ? number_format((float) $v, 2, ',', '') : self::texte($v), array_values($ligne)), ';');
            }
            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected static function pdf(string $nom, string $titre, array $colonnes, array $lignes, ?array $total, ?string $sousTitre, string $orientation): Response
    {
        $empreinte = hash('sha256', json_encode([$titre, $colonnes, $lignes, $total]));
        $jeton = QrCode::jetonPour(null, 'EXPORT', $titre . ($sousTitre ? ' — ' . $sousTitre : ''), ['libelle' => count($lignes) . ' ligne(s)', 'empreinte' => $empreinte]);

        $contenu = DomPdf::loadView('pdf.tableau', [
            'agence' => agence(), 'titre' => $titre, 'sousTitre' => $sousTitre, 'colonnes' => $colonnes, 'lignes' => $lignes, 'total' => $total,
            'signature' => QrCode::signature($jeton),
        ])->setPaper('a4', $orientation)->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])->output();

        return response($contenu, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $nom . '"']);
    }

    protected static function texte(mixed $v): string
    {
        return match (true) {
            $v instanceof \BackedEnum => method_exists($v, 'libelle') ? $v->libelle() : (string) $v->value,
            $v instanceof \DateTimeInterface => $v->format('d/m/Y'),
            is_bool($v) => $v ? 'Oui' : 'Non',
            default => (string) $v,
        };
    }

    /**
     * Cellule d'une liste HTML vers une valeur exportable : texte sans balises (sauts de ligne → « — »),
     * montant (« 150 000 FCFA ») converti en nombre pour Excel.
     */
    public static function depuisHtml(mixed $v): mixed
    {
        if (is_int($v) || is_float($v) || $v === null) {
            return $v;
        }
        $texte = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/div>\s*<div[^>]*>/i', ' — ', (string) $v)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $texte = trim($texte, " —");
        $chiffres = preg_replace('/[\s\x{00A0}\x{202F}]|F\s?CFA|FCFA|XOF/u', '', $texte);
        if ($texte !== '' && preg_match('/^-?\d+(,\d+)?$/', $chiffres)) {
            return (float) str_replace(',', '.', $chiffres);
        }

        return $texte;
    }

    /** Valeur pour la vue PDF. */
    public static function cellule(mixed $v): string
    {
        return is_float($v) || is_int($v) ? Montant::formater($v) : self::texte($v);
    }
}
