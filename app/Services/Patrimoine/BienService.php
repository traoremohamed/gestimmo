<?php

namespace App\Services\Patrimoine;

use App\Enums\RoleTiers;
use App\Enums\StatutBien;
use App\Models\Bien;
use App\Models\Media;
use App\Models\Tiers;
use App\Support\AgenceCourante;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Biens : référence automatique, génération en masse (lots d'un lotissement,
 * appartements d'un immeuble) et médias.
 */
class BienService
{
    public const MIMES_PHOTOS = 'jpg,jpeg,png,webp';

    public const MIMES_DOCUMENTS = 'pdf,jpg,jpeg,png,doc,docx';

    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    public function creer(array $donnees): Bien
    {
        $donnees['reference'] = ($donnees['reference'] ?? null) ?: $this->prochaineReference();
        $donnees['statut'] ??= StatutBien::Disponible;
        $donnees['created_by'] = auth()->id();

        $bien = Bien::create($donnees);
        $this->rattacherProprietaire($bien);

        return $bien;
    }

    public function modifier(Bien $bien, array $donnees): Bien
    {
        $bien->update($donnees);
        $this->rattacherProprietaire($bien);

        return $bien;
    }

    /**
     * Génère des biens numérotés de $debut à $fin à partir d'un modèle :
     *   lots 1 à 50 de l'îlot 12 (terrains de 500 m² à 5 000 000), ou appartements des étages 1 à 4.
     *
     * @param  array  $modele  attributs communs (type_bien, superficie, prix_vente, lotissement_id, immeuble_id…)
     * @return int nombre de biens créés
     */
    public function generer(array $modele, int $debut, int $fin, string $prefixeLibelle, int $parEtage = 0): int
    {
        if ($fin < $debut) {
            throw new DomainException('Le numéro de fin doit être supérieur ou égal au numéro de début.');
        }
        if ($fin - $debut + 1 > 1000) {
            throw new DomainException('La génération est limitée à 1 000 biens à la fois.');
        }

        return DB::transaction(function () use ($modele, $debut, $fin, $prefixeLibelle, $parEtage) {
            $crees = 0;
            for ($numero = $debut; $numero <= $fin; $numero++) {
                $attributs = $modele + ['statut' => StatutBien::Disponible];
                $attributs['libelle'] = trim($prefixeLibelle . ' ' . $numero);

                if (!empty($modele['immeuble_id'])) {
                    $attributs['porte'] = (string) $numero;
                    if ($parEtage > 0) {
                        $attributs['etage'] = (string) intdiv($numero - $debut, $parEtage);
                    }
                } else {
                    $attributs['numero_lot'] = (string) $numero;
                }

                $existe = Bien::query()
                    ->when(!empty($modele['lotissement_id']), fn ($q) => $q->where('lotissement_id', $modele['lotissement_id'])
                        ->where('ilot', $modele['ilot'] ?? null)->where('numero_lot', (string) $numero))
                    ->when(!empty($modele['immeuble_id']), fn ($q) => $q->where('immeuble_id', $modele['immeuble_id'])->where('porte', (string) $numero))
                    ->when(empty($modele['lotissement_id']) && empty($modele['immeuble_id']), fn ($q) => $q->whereRaw('1 = 0'))
                    ->exists();

                if ($existe) {
                    continue;
                }

                $this->creer($attributs);
                $crees++;
            }

            return $crees;
        });
    }

    public function ajouterMedia($modele, UploadedFile $fichier, string $collection = 'photos', ?string $libelle = null): Media
    {
        $disque = $collection === 'documents' ? config('immobilier.stockage.prive') : config('immobilier.stockage.public');
        $chemin = $fichier->store('agences/' . $this->agenceCourante->id() . '/' . $collection, $disque);

        return $modele->medias()->create([
            'agence_id' => $this->agenceCourante->id(),
            'collection' => $collection,
            'disque' => $disque,
            'chemin' => $chemin,
            'nom_original' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getMimeType(),
            'taille' => $fichier->getSize(),
            'libelle' => $libelle,
            'ordre' => $modele->medias()->count(),
            'created_by' => auth()->id(),
        ]);
    }

    public function prochaineReference(): string
    {
        $prefixe = parametre('numerotation.bien') ?: 'B';
        $dernier = Bien::withTrashed()->where('reference', 'like', $prefixe . '-%')->count();

        do {
            $reference = sprintf('%s-%05d', $prefixe, ++$dernier);
        } while (Bien::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    protected function rattacherProprietaire(Bien $bien): void
    {
        if ($bien->proprietaire_id) {
            Tiers::find($bien->proprietaire_id)?->ajouterRole(RoleTiers::Proprietaire);
        }
    }
}
