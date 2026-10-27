<?php

namespace App\Console\Commands;

use App\Enums\NatureLoyer;
use App\Enums\StatutBien;
use App\Models\Agence;
use App\Models\Bail;
use App\Models\Bien;
use App\Models\Echeance;
use App\Models\Encaissement;
use App\Models\FraisType;
use App\Models\Immeuble;
use App\Models\Lotissement;
use App\Models\Loyer;
use App\Models\Pays;
use App\Models\Programme;
use App\Models\Referentiel;
use App\Models\Reservation;
use App\Models\Sejour;
use App\Models\Tiers;
use App\Models\User;
use App\Models\Vente;
use App\Models\VenteFrais;
use App\Services\AgenceService;
use App\Services\Vente\VenteService;
use App\Support\AgenceCourante;
use App\Support\Montant;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reprise des données de l'ancienne application (base MySQL baseimmobilier) dans une agence.
 *
 *   php artisan immobilier:import-legacy --agence=LEGACY --raison-sociale="Ma société"
 *   php artisan immobilier:import-legacy --agence=LEGACY --reinitialiser   (rejoue l'import)
 *
 * Chaque enregistrement importé garde son identifiant d'origine (legacy_id) ; les paiements
 * historiques sont recréés sous forme d'encaissements « LEG-… » datés, affectés aux échéances.
 */
class ImporterAncienneBase extends Command
{
    protected $signature = 'immobilier:import-legacy
        {--agence=LEGACY : code de l\'agence cible (créée si absente)}
        {--raison-sociale= : raison sociale de l\'agence à créer}
        {--connexion=legacy : connexion de la base source (config/database.php)}
        {--reinitialiser : supprime les données métier de l\'agence avant l\'import}
        {--force : ne demande pas de confirmation}';

    protected $description = 'Importe les données de l\'ancienne application immobilière (MySQL) dans une agence';

    protected Connection $source;

    protected Agence $agence;

    /** @var array<string, array<int|string, mixed>> correspondances ancien id => nouvel id / code */
    protected array $map = [];

    protected array $rapport = [];

    /** profils de l'ancienne application => profils métier */
    protected const PROFILS = [
        'ADMIN' => "DIRECTEUR D'AGENCE", 'DG' => "DIRECTEUR D'AGENCE", 'DRMAKET' => 'COMMERCIAL', 'COM' => 'COMMERCIAL',
        'GESTCONTRAT' => 'COMMERCIAL', 'DRFONC' => 'COMMERCIAL', 'DRFINCOM' => 'CAISSIER / COMPTABLE', 'COMPTA' => 'CAISSIER / COMPTABLE',
        'DIRECTECH' => 'GESTIONNAIRE LOCATIF', 'CLIENT' => 'CLIENT', 'LOC' => 'CLIENT', 'BAIL' => 'CLIENT',
    ];

    public function handle(AgenceService $agences, AgenceCourante $agenceCourante): int
    {
        try {
            $this->source = DB::connection($this->option('connexion'));
            $this->source->getPdo();
        } catch (\Throwable $e) {
            $this->error('Connexion à la base source impossible : ' . $e->getMessage());

            return self::FAILURE;
        }

        $code = strtoupper($this->option('agence'));
        $this->agence = Agence::where('code', $code)->first() ?? $agences->creer([
            'code' => $code,
            'raison_sociale' => $this->option('raison-sociale') ?: $this->parametreSource('LOGO') ?: 'Agence ' . $code,
            'slug' => Str::slug($code),
            'email' => $this->parametreSource('EMAIL'),
            'telephone' => $this->parametreSource('CONTACT'),
            'adresse' => $this->parametreSource('LOCALISATION'),
        ]);
        $agenceCourante->definir($this->agence);

        $dejaImporte = Tiers::whereNotNull('legacy_id')->exists() || Bien::whereNotNull('legacy_id')->exists();
        if ($dejaImporte && !$this->option('reinitialiser')) {
            $this->error("L'agence {$code} contient déjà des données importées. Relancez avec --reinitialiser pour rejouer l'import.");

            return self::FAILURE;
        }
        if ($this->option('reinitialiser') && !$this->option('force')
            && !$this->confirm("Toutes les données métier de l'agence {$this->agence->raison_sociale} seront supprimées. Continuer ?")) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            if ($this->option('reinitialiser')) {
                $this->purger();
            }

            foreach ([
                'pays', 'referentiels', 'utilisateurs', 'programmes', 'lotissements', 'tiers', 'immeubles', 'biens', 'reservations',
                'ventes', 'baux', 'sejours', 'crm', 'vitrine',
            ] as $etape) {
                $this->{'importer' . ucfirst($etape)}();
            }

            foreach (Vente::where('statut', 'en_cours')->get() as $vente) {
                app(VenteService::class)->majStatut($vente);
            }
        });

        $this->table(['Données', 'Importées'], collect($this->rapport)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info("Import terminé dans l'agence {$this->agence->raison_sociale} ({$this->agence->code}).");
        $this->line('Les utilisateurs importés se connectent avec leur e-mail comme identifiant et leur ancien mot de passe, puis doivent le changer.');
        $this->line('Les fichiers (photos, plans, pièces scannées) restent dans l\'ancien dossier public : à recopier si besoin via l\'interface.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ étapes

    protected function importerPays(): void
    {
        foreach ($this->source->table('pays')->get() as $p) {
            $pays = Pays::firstOrCreate(['libelle' => trim($p->libelle_pays)], ['nationalite' => $p->nationalite_pays, 'indicatif' => $p->indicatif]);
            $this->map['pays'][$p->id_pays] = $pays->id;
        }
        $this->compter('Pays', count($this->map['pays'] ?? []));
    }

    protected function importerReferentiels(): void
    {
        foreach ($this->source->table('situation_matri')->get() as $s) {
            $this->map['situation'][$s->id_sit_mat] = $this->referentiel('situation_matrimoniale', $s->lib_sit_mat);
        }
        foreach ($this->source->table('type_bien')->get() as $t) {
            $this->map['type_bien'][$t->id_tbien] = $this->referentiel('type_bien', $t->lib_tbien);
        }
        foreach ($this->source->table('type_reglement')->get() as $t) {
            $this->map['mode'][$t->id_type_reg] = ['CHEQUE' => 'CHEQUE', 'ESPECE' => 'ESPECES', 'ESPECES' => 'ESPECES'][strtoupper(trim($t->lib_type_reg))]
                ?? $this->referentiel('mode_paiement', $t->lib_type_reg);
        }
        foreach ($this->source->table('canalcom')->get() as $c) {
            $this->map['canal'][$c->id_canalcom] = $this->referentiel('canal_acquisition', $c->libelle_canalcom);
        }
        foreach ($this->source->table('piece')->get() as $p) {
            if ($p->code_piece === 'ETC') {
                $this->map['piece_identite'][$p->id_piece] = $this->referentiel('type_piece_identite', $p->lib_piece);
                continue;
            }
            $frais = FraisType::firstOrCreate(
                ['code' => strtoupper(Str::slug($p->lib_piece, '_'))],
                ['libelle' => Str::ucfirst(Str::lower($p->lib_piece)), 'activite' => $p->code_piece === 'LOC' ? 'location' : 'vente',
                    'montant' => $this->nombre($p->montant_piece) ?? 0, 'actif' => (bool) $p->flag_piece]
            );
            $this->map['frais'][$p->id_piece] = $frais;
        }
        $this->compter('Listes de valeurs', Referentiel::count());
    }

    protected function importerUtilisateurs(): void
    {
        $roles = $this->source->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->pluck('roles.code_roles', 'model_has_roles.model_id');

        $n = 0;
        foreach ($this->source->table('users')->get() as $u) {
            $existant = User::where('email', $u->email)->first();
            if ($existant) {
                $this->map['user'][$u->id] = $existant->agence_id === $this->agence->id ? $existant->id : null;
                continue;
            }

            $login = Str::lower(Str::before($u->email, '@')) ?: 'utilisateur' . $u->id;
            while (User::where('login_users', $login)->exists()) {
                $login .= $u->id;
            }

            $id = DB::table('users')->insertGetId([
                'agence_id' => $this->agence->id,
                'name' => $u->name,
                'prenom_users' => $u->prenom_users,
                'email' => $u->email,
                'login_users' => $login,
                'password' => $u->password, // empreinte bcrypt conservée
                'genre_users' => $u->genre_users,
                'cel_users' => $u->cel_users,
                'tel_users' => $u->tel_users,
                'adresse_users' => $u->adresse_users,
                'flag_actif_users' => (bool) $u->flag_actif_users,
                'flag_demission_users' => (bool) $u->flag_demission_users,
                'flag_mdp' => false, // changement de mot de passe obligatoire à la première connexion
                'created_at' => $u->created_at ?? now(),
                'updated_at' => now(),
            ]);
            $this->map['user'][$u->id] = $id;

            $profil = self::PROFILS[$roles[$u->id] ?? ''] ?? null;
            if ($profil) {
                User::find($id)->assignRole($profil);
            }
            $n++;
        }
        $this->compter('Utilisateurs', $n);
    }

    protected function importerProgrammes(): void
    {
        foreach ($this->source->table('site')->get() as $s) {
            $this->map['site'][$s->id_site] = Programme::create([
                'nom' => trim($s->lib_site), 'type' => 'lotissement', 'actif' => (bool) $s->flag_site,
                'latitude' => $this->coordonnee($s->latitude_site, 90), 'longitude' => $this->coordonnee($s->longitude_site, 180),
                'legacy_id' => $s->id_site, 'created_at' => $s->created_at,
            ])->id;
        }
        $this->compter('Programmes (sites)', count($this->map['site'] ?? []));
    }

    protected function importerLotissements(): void
    {
        foreach ($this->source->table('lotissement')->get() as $l) {
            $this->map['loti'][$l->id_loti] = Lotissement::create([
                'programme_id' => $this->map['site'][$l->id_site] ?? null,
                'nom' => trim($l->lib_loti),
                'reference' => $l->reference_loti,
                'type_titre' => $l->titre_loti ? $this->referentiel('type_titre_foncier', $l->titre_loti) : null,
                'numero_titre' => $l->num_titre_loti,
                'prefecture' => $l->prefecture_loti,
                'village' => $l->village_loti,
                'latitude' => $this->coordonnee($l->latitude_loti, 90),
                'longitude' => $this->coordonnee($l->longitude_loti, 180),
                'actif' => (bool) $l->flag_loti,
                'legacy_id' => $l->id_loti,
                'created_at' => $l->created_at,
            ])->id;
        }
        $this->compter('Lotissements', count($this->map['loti'] ?? []));
    }

    protected function importerTiers(): void
    {
        $canaux = $this->source->table('canalcomclient')->get()->groupBy('id_clic');
        $n = 0;

        foreach ($this->source->table('client')->get() as $c) {
            $tiers = Tiers::create([
                'code' => 'T-LEG-' . str_pad((string) $c->id_cli, 5, '0', STR_PAD_LEFT),
                'nom' => trim($c->nom_cli) ?: 'Sans nom',
                'prenoms' => $c->prenom_cli,
                'date_naissance' => $this->date($c->date_nais_cli),
                'lieu_naissance' => $c->lieu_nais_cli,
                'nationalite_id' => $this->map['pays'][$c->id_pays] ?? null,
                'situation_matrimoniale' => $this->map['situation'][$c->id_sit_mat] ?? null,
                'regime_matrimonial' => $c->regime_matri_cli,
                'profession' => $c->profession_cli,
                'employeur' => $c->lib_emp_cli,
                'indicatif' => $c->indicatif_cel_cli ? preg_replace('/\D/', '', $c->indicatif_cel_cli) : null,
                'telephone' => $c->cel_cli ?: $c->tel_domicile_cli,
                'telephone2' => $c->tel_bureau_cli,
                'email' => $c->email_cli,
                'email2' => $c->email2_cli,
                'adresse' => $c->domicile_cli,
                'boite_postale' => $c->bp_cli,
                'conjoint' => array_filter([
                    'nom' => $c->nom_et_prenom_conj_cli, 'date_naissance' => $c->date_nais_conjoint_cli, 'lieu_naissance' => $c->lieu_nais_conjoint_cli,
                    'profession' => $c->profession_conj_cli, 'date_mariage' => $c->date_mariage_cli, 'lieu_mariage' => $c->lieu_mariage_cli,
                ]) ?: null,
                'filiation' => array_filter(['pere' => $c->nom_prenom_pere_cli, 'mere' => $c->nom_prenom_mere_cli]) ?: null,
                'canal_acquisition' => isset($canaux[$c->id_cli]) ? ($this->map['canal'][$canaux[$c->id_cli]->first()->id_canalcom] ?? null) : null,
                'commercial_id' => $this->map['user'][$c->id_user] ?? null,
                'notes' => trim(($c->commentaire_cli ?? '') . "\n" . ($c->commentaire ?? '')) ?: null,
                'legacy_id' => $c->id_cli,
                'created_at' => $c->created_at,
            ]);

            $roles = [];
            if ($c->flag_prospect_cli) {
                $roles[] = 'prospect';
            }
            if ((int) $c->id_type_cli === 1) {
                $roles[] = 'acquereur';
            }
            if ($c->flag_locataire || (int) $c->id_type_cli === 2) {
                $roles[] = 'locataire';
            }
            if ($c->flag_proprietaire || (int) $c->id_type_cli === 3) {
                $roles[] = 'proprietaire';
            }
            $tiers->ajouterRole(...($roles ?: ['prospect']));

            foreach (['' => $c->id_piece_etat, '_mad' => $c->id_piece_etat_mad, '_autre' => $c->id_piece_etat_autre] as $suffixe => $idPiece) {
                $numero = $c->{'numero_piec_cli_etat' . $suffixe} ?? null;
                if (!$numero) {
                    continue;
                }
                $tiers->pieces()->create([
                    'type_piece' => $this->map['piece_identite'][$idPiece] ?? $this->referentiel('type_piece_identite', $c->nature_piece_cli ?: 'CNI'),
                    'numero' => $numero,
                    'date_delivrance' => $this->date($c->{'date_deli_debu_piec_cli_etat' . $suffixe}),
                    'date_expiration' => $this->date($c->{'date_deli_fin_piec_cli_etat' . $suffixe}),
                ]);
            }

            $this->map['client'][$c->id_cli] = $tiers->id;
            $n++;
        }
        $this->compter('Tiers (clients, prospects, locataires, propriétaires)', $n);
    }

    protected function importerImmeubles(): void
    {
        foreach ($this->source->table('immeuble')->get() as $i) {
            $this->map['immeuble'][$i->id_immeuble] = Immeuble::create([
                'nom' => trim($i->nom_immeuble),
                'ilot' => $i->ilot_immeuble,
                'lot' => $i->loti_immeuble,
                'superficie' => $this->nombre($i->superficie_immeuble),
                'nombre_etages' => (int) $i->nombre_etage_immeuble,
                'proprietaire_id' => $this->map['client'][$i->proprietaire_immeuble] ?? null,
                'latitude' => $this->coordonnee($i->map_larg_immeuble, 90),
                'longitude' => $this->coordonnee($i->map_long_immeuble, 180),
                'description' => $i->commentaire,
                'actif' => (bool) $i->actif_immeuble,
                'legacy_id' => $i->id_immeuble,
                'created_at' => $i->created_at,
            ])->id;
        }
        $this->compter('Immeubles', count($this->map['immeuble'] ?? []));
    }

    protected function importerBiens(): void
    {
        $proprietaires = $this->source->table('proprietaire_bien')->pluck('id_client', 'id_bien');

        foreach ($this->source->table('lot')->get() as $l) {
            $residence = (bool) $l->flag_resisdence;
            $location = (bool) $l->flag_location_bien && !$residence;
            $montant = $this->nombre($l->montant_lot);

            $statut = match (true) {
                !$l->flag_lot => StatutBien::Indisponible,
                (bool) $l->flag_en_cours_location || (bool) $l->flag_residence_en_cours => StatutBien::Loue,
                (bool) $l->flag_solder_lot || (bool) $l->flag_payer_lot => $location ? StatutBien::Loue : StatutBien::Vendu,
                (bool) $l->flag_bloquer_lot => StatutBien::Reserve,
                default => StatutBien::Disponible,
            };

            $bien = Bien::create([
                'reference' => 'B-' . str_pad((string) $l->id_lot, 5, '0', STR_PAD_LEFT),
                'libelle' => trim($l->lib_lot) ?: 'Lot ' . $l->id_lot,
                'type_bien' => $this->map['type_bien'][$l->id_tbien] ?? 'TERRAIN',
                'lotissement_id' => $this->map['loti'][$l->id_loti] ?? null,
                'programme_id' => isset($this->map['loti'][$l->id_loti]) ? Lotissement::find($this->map['loti'][$l->id_loti])?->programme_id : null,
                'immeuble_id' => $this->map['immeuble'][$l->id_immeuble] ?? null,
                'proprietaire_id' => $this->map['client'][$proprietaires[$l->id_lot] ?? 0] ?? null,
                'ilot' => $l->ilot_lot,
                'numero_lot' => $l->lot_lot,
                'etage' => $l->etage,
                'porte' => $l->numero_de_porte,
                'superficie' => $this->nombre($l->superfie_lot),
                'unite_superficie' => strtolower((string) $l->type_superficie_lot) === 'ha' ? 'ha' : 'm2',
                'nb_pieces' => $this->entier($l->nombre_de_pieces),
                'nb_chambres' => $this->entier($l->nombre_de_chambres),
                'nb_salons' => $this->entier($l->nombre_de_salon),
                'nb_salles_bain' => $this->entier($l->nombre_de_douches),
                'nb_toilettes' => $this->entier($l->nombre_de_toilette),
                'a_vendre' => !$location && !$residence,
                'a_louer' => $location,
                'meuble' => $residence,
                'prix_vente' => !$location && !$residence ? $montant : null,
                'loyer_mensuel' => $location ? $montant : null,
                'prix_nuitee' => $residence ? $montant : null,
                'statut' => $statut,
                'latitude' => $this->coordonnee($l->map_latitude, 90),
                'longitude' => $this->coordonnee($l->map_longitude, 180),
                'description' => $l->commentaire,
                'publie' => (bool) $l->flag_publier,
                'legacy_id' => $l->id_lot,
                'created_at' => $l->created_at,
            ]);
            $this->map['lot'][$l->id_lot] = $bien->id;
        }

        foreach ($this->source->table('bien_location')->get() as $b) {
            $this->map['bien_location'][$b->id_bien_location] = Bien::create([
                'reference' => 'BL-' . str_pad((string) $b->id_bien_location, 5, '0', STR_PAD_LEFT),
                'libelle' => trim($b->identification_bien_loc) ?: 'Bien en location ' . $b->id_bien_location,
                'type_bien' => $this->map['type_bien'][$b->id_type_lot_loc] ?? 'APPARTEMENT',
                'lotissement_id' => $this->map['loti'][$b->id_loti_loc] ?? null,
                'proprietaire_id' => $this->map['client'][$b->id_proprietaire] ?? null,
                'ilot' => $b->ilot_lot_loc,
                'superficie' => $this->nombre($b->superfie_lot_loc),
                'nb_pieces' => $this->entier($b->nombre_pieces_loc),
                'nb_chambres' => $this->entier($b->nombre_chambre_loc),
                'nb_salons' => $this->entier($b->nombre_salon_loc),
                'nb_salles_bain' => $this->entier($b->nombre_douche_loc),
                'nb_cuisines' => $this->entier($b->nombre_cuisine_loc),
                'a_louer' => true,
                'loyer_mensuel' => $this->nombre($b->cout_loyer_loc),
                'statut' => $b->flag_en_location ? StatutBien::Loue : ($b->flag_valide_loc ? StatutBien::Disponible : StatutBien::Indisponible),
                'description' => $b->Commentaire_loca_loc,
                'legacy_id' => 900000 + $b->id_bien_location,
            ])->id;
        }

        $this->compter('Biens (lots, appartements, locaux)', count($this->map['lot'] ?? []) + count($this->map['bien_location'] ?? []));
    }

    protected function importerReservations(): void
    {
        $n = 0;
        foreach ($this->source->table('reservationcom')->get() as $r) {
            if (!isset($this->map['client'][$r->id_cli], $this->map['lot'][$r->id_bien])) {
                continue;
            }
            Reservation::create([
                'numero' => 'RES-LEG-' . $r->id_reservcom,
                'tiers_id' => $this->map['client'][$r->id_cli],
                'bien_id' => $this->map['lot'][$r->id_bien],
                'user_id' => $this->map['user'][$r->id_userc] ?? null,
                'date_reservation' => $this->date($r->date_reservation) ?? $r->created_at,
                'date_expiration' => $this->date($r->date_liberation) ?? $r->created_at,
                'statut' => $r->flag_vendu ? 'convertie' : ($r->flag_libere ? 'liberee' : ($r->flag_reserv ? 'active' : 'expiree')),
                'legacy_id' => $r->id_reservcom,
            ]);
            $n++;
        }
        $this->compter('Réservations', $n);
    }

    protected function importerVentes(): void
    {
        $dossiers = $this->source->table('dossier')->where(fn ($q) => $q->whereNull('loyer')->orWhere('loyer', ''))->get();
        $lots = $this->source->table('dossier_lot')->get()->groupBy('id_doss');
        $pieces = $this->source->table('dossier_piece')->get()->groupBy('id_doss');
        $echeances = $this->source->table('echancier')->whereNotNull('id_doss')->orderBy('num_ordre')->orderBy('id_echeancier')->get()->groupBy('id_doss');
        $partiels = $this->source->table('echancier_partielle')->get()->groupBy('id_echeancier');
        $montantsLots = $this->source->table('lot')->pluck('montant_lot', 'id_lot');
        $n = 0;

        foreach ($dossiers as $d) {
            if (!isset($this->map['client'][$d->id_cli])) {
                continue;
            }

            // l'ancienne base contient des doublons dossier/lot (aucune contrainte d'unicité)
            $lignes = collect($lots[$d->id_doss] ?? [])->filter(fn ($l) => isset($this->map['lot'][$l->id_lot]))->unique('id_lot');
            $prixTotal = $lignes->sum(fn ($l) => $this->nombre($l->montant) ?? $this->nombre($montantsLots[$l->id_lot] ?? 0) ?? 0);
            $net = $this->nombre($d->montant_total_bien) ?: $prixTotal;
            $statut = match (true) {
                (bool) $d->flag_desister => 'desistee',
                !$d->flag_valide => 'brouillon',
                $d->status_doss === 'Ferme' => 'soldee',
                default => 'en_cours',
            };

            $vente = Vente::create([
                'numero' => $statut === 'brouillon' ? null : ($d->idcontrat_reservation ?: 'CV-LEG-' . $d->id_doss),
                'acquereur_id' => $this->map['client'][$d->id_cli],
                'commercial_id' => $this->map['user'][$d->id_usera] ?? null,
                'statut' => $statut,
                'date_contrat' => $this->date($d->date_dossier) ?? Carbon::parse($d->created_at)->toDateString(),
                'prix_total' => Montant::normaliser($prixTotal),
                'montant_net' => Montant::normaliser($net),
                'mode_financement' => (int) $d->nombre_de_mois > 0 ? 'echelonne' : 'comptant',
                'montant_acompte' => Montant::normaliser($this->nombre($d->accompte) ?? 0),
                'nombre_echeances' => (int) $d->nombre_de_mois,
                'date_premiere_echeance' => $this->date($d->date_debut_mois),
                'date_desistement' => $d->flag_desister ? Carbon::parse($d->updated_at)->toDateString() : null,
                'motif_desistement' => $d->flag_desister ? 'AUTRE' : null,
                'observation_desistement' => $d->motif_desistement,
                'montant_a_rembourser' => Montant::normaliser($this->nombre($d->montant_a_rembourser) ?? 0),
                'rembourse_le' => $d->flag_rembourser ? $d->updated_at : null,
                'observation' => $d->observation,
                'valide_le' => $d->flag_valide ? ($this->date($d->date_dossier) ?? $d->created_at) : null,
                'legacy_id' => $d->id_doss,
                'created_at' => $d->created_at,
            ]);

            foreach ($lignes as $l) {
                $vente->lignes()->create(['bien_id' => $this->map['lot'][$l->id_lot], 'prix' => Montant::normaliser($this->nombre($l->montant) ?? $this->nombre($montantsLots[$l->id_lot] ?? 0) ?? 0)]);
            }

            $acquereur = $vente->acquereur_id;
            $dateDefaut = $vente->date_contrat;

            // frais annexes (pièces du dossier)
            foreach ($pieces[$d->id_doss] ?? [] as $p) {
                $type = $this->map['frais'][$p->id_piece] ?? null;
                $frais = VenteFrais::create([
                    'vente_id' => $vente->id,
                    'frais_type_id' => $type?->id,
                    'libelle' => $type?->libelle ?? 'Frais',
                    'montant' => Montant::normaliser($p->mtt_piece),
                    'statut' => 'a_payer',
                    'document_requis' => (bool) ($type?->document_requis),
                    'statut_document' => $p->flag_notif ? 'notifie' : ($p->flag_dispo ? 'disponible' : null),
                ]);
                if ($p->flag_payer_piece || $p->flag_solde) {
                    $this->paiementHistorique($acquereur, $frais, $p->mtt_piece, $p->date_payer_piece ?? $dateDefaut, 'F' . $p->id_doss_piec, 'vente');
                }
            }

            if ($statut === 'brouillon') {
                $n++;
                continue;
            }

            // acompte
            $acompte = $this->nombre($d->accompte) ?? 0;
            if ($acompte > 0) {
                $echeance = Echeance::create(['vente_id' => $vente->id, 'numero' => 0, 'libelle' => 'Acompte', 'date_echeance' => $dateDefaut, 'montant' => Montant::normaliser($acompte)]);
                if ($d->flag_payer_accompte) {
                    $this->paiementHistorique($acquereur, $echeance, $acompte, $d->date_payer_accompte ?? $dateDefaut, 'A' . $d->id_doss, 'vente');
                }
            }

            // échéances et paiements partiels
            $rang = 0;
            $total = count($echeances[$d->id_doss] ?? []);
            foreach ($echeances[$d->id_doss] ?? [] as $e) {
                $rang++;
                $echeance = Echeance::create([
                    'vente_id' => $vente->id,
                    'numero' => $rang,
                    'libelle' => 'Échéance ' . $rang . '/' . $total,
                    'date_echeance' => $this->date($e->date_paiement) ?? $dateDefaut,
                    'montant' => Montant::normaliser($e->montant_echeance),
                    'statut' => $statut === 'desistee' && !$e->flag_payer ? 'annule' : 'a_payer',
                ]);

                $partielles = collect($partiels[$e->id_echeancier] ?? []);
                if ($partielles->isNotEmpty()) {
                    foreach ($partielles as $pp) {
                        $this->paiementHistorique($acquereur, $echeance, $pp->montant_payer, $pp->date_reel_paiement_partielle ?? $e->date_paiement, 'P' . $pp->id_echancier_partielle, 'vente', $pp->num_mpaie);
                    }
                } elseif ($e->flag_payer) {
                    $this->paiementHistorique($acquereur, $echeance, $e->montant_echeance, $e->date_reel_paiement ?? $e->date_paiement, 'E' . $e->id_echeancier, 'vente', $e->num_mpaie);
                }
            }

            app(VenteService::class)->recalculer($vente);
            $n++;
        }
        $this->compter('Contrats de vente', $n);
    }

    protected function importerBaux(): void
    {
        $dossiers = $this->source->table('dossier')->whereNotNull('loyer')->where('loyer', '!=', '')->get();
        $lots = $this->source->table('dossier_lot')->get()->groupBy('id_doss');
        $paiements = $this->source->table('echancier')->whereNotNull('id_doss')->orderBy('date_paiement')->get()->groupBy('id_doss');
        $pieces = $this->source->table('dossier_piece')->get()->groupBy('id_doss');
        $n = 0;

        foreach ($dossiers as $d) {
            $ligne = collect($lots[$d->id_doss] ?? [])->first();
            $bienId = $ligne ? ($this->map['lot'][$ligne->id_lot] ?? $this->map['bien_location'][$ligne->id_bien_loca] ?? null) : null;
            if (!$bienId || !isset($this->map['client'][$d->id_cli])) {
                continue;
            }

            $loyer = $this->nombre($d->loyer) ?? 0;
            $debut = Carbon::parse($this->date($d->date_dossier) ?? $d->created_at)->startOfDay();
            $statut = match ($d->status_doss) {
                'En Location' => 'actif',
                'Ferme' => 'resilie',
                default => $d->flag_valide ? 'actif' : 'brouillon',
            };
            $bien = Bien::find($bienId);

            $bail = Bail::create([
                'numero' => $statut === 'brouillon' ? null : 'BL-LEG-' . $d->id_doss,
                'bien_id' => $bienId,
                'locataire_id' => $this->map['client'][$d->id_cli],
                'mandat_id' => $bien?->mandat_id,
                'statut' => $statut,
                'date_debut' => $debut,
                'duree_mois' => 12,
                'date_fin' => $debut->copy()->addYear()->subDay(),
                'loyer_mensuel' => Montant::normaliser($loyer),
                'jour_echeance' => min(28, max(1, $debut->day)),
                'mois_caution' => (int) $this->entier($d->mois_caution_loyer),
                'mois_avance' => (int) $this->entier($d->mois_avance_loyer),
                'depot_garantie' => Montant::mul($loyer, (int) $this->entier($d->mois_caution_loyer)),
                'frais_agence' => Montant::normaliser($this->nombre($d->mois_agence_caution) ?? 0),
                'date_resiliation' => $statut === 'resilie' ? Carbon::parse($d->updated_at)->toDateString() : null,
                'motif_resiliation' => $statut === 'resilie' ? 'FIN_BAIL' : null,
                'observation' => $d->observation,
                'legacy_id' => $d->id_doss,
                'created_at' => $d->created_at,
            ]);
            $locataire = $bail->locataire_id;

            // frais annexes du dossier de location (contrat de bail…)
            foreach ($pieces[$d->id_doss] ?? [] as $p) {
                $du = Loyer::create(['bail_id' => $bail->id, 'nature' => NatureLoyer::FraisAgence, 'libelle' => $this->map['frais'][$p->id_piece]?->libelle ?? 'Frais',
                    'date_echeance' => $debut, 'montant' => Montant::normaliser($p->mtt_piece)]);
                if ($p->flag_payer_piece || $p->flag_solde) {
                    $this->paiementHistorique($locataire, $du, $p->mtt_piece, $p->date_payer_piece ?? $debut, 'FL' . $p->id_doss_piec, 'location');
                }
            }

            // garanties et frais d'entrée
            foreach ([
                [NatureLoyer::DepotGarantie, 'Dépôt de garantie', $bail->depot_garantie, $d->flag_mois_caution_loyer, $d->date_payer_caution],
                [NatureLoyer::Avance, 'Avance sur loyer (' . $bail->mois_avance . ' mois)', Montant::mul($loyer, $bail->mois_avance), $d->flag_mois_avance_loyer, $d->date_payer_avance],
                [NatureLoyer::FraisAgence, 'Frais d\'agence', $bail->frais_agence, $d->flag_agence_loyer, $d->date_payer_agence],
            ] as [$nature, $libelle, $montant, $paye, $date]) {
                if (!Montant::estPositif($montant)) {
                    continue;
                }
                $du = Loyer::create(['bail_id' => $bail->id, 'nature' => $nature, 'libelle' => $libelle, 'date_echeance' => $debut, 'montant' => $montant]);
                if ($paye) {
                    $this->paiementHistorique($locataire, $du, $montant, $date ?? $debut, strtoupper(substr($nature->value, 0, 3)) . $d->id_doss, 'location');
                }
            }

            // loyers payés (l'ancienne application enregistrait chaque paiement comme une ligne d'échéancier)
            $dernierePeriode = null;
            foreach ($paiements[$d->id_doss] ?? [] as $p) {
                $periode = Carbon::parse($p->date_paiement ?? $p->created_at)->startOfMonth();
                $du = Loyer::create([
                    'bail_id' => $bail->id, 'nature' => NatureLoyer::Loyer, 'libelle' => 'Loyer ' . $periode->locale('fr')->translatedFormat('F Y'),
                    'periode_debut' => $periode, 'periode_fin' => $periode->copy()->endOfMonth(), 'date_echeance' => $periode->copy()->day(min(28, $bail->jour_echeance)),
                    'montant_loyer' => Montant::normaliser($p->montant_echeance), 'montant' => Montant::normaliser($p->montant_echeance),
                ]);
                if ($p->flag_payer) {
                    $this->paiementHistorique($locataire, $du, $p->montant_echeance, $p->date_reel_paiement ?? $p->date_paiement, 'L' . $p->id_echeancier, 'location', $p->num_mpaie);
                }
                $dernierePeriode = $periode->copy()->endOfMonth();
            }

            // la génération automatique reprendra après la dernière période importée
            $bail->update(['loyers_generes_jusqu_au' => $dernierePeriode ?? $debut->copy()->subDay()]);
            $n++;
        }
        $this->compter('Baux', $n);
    }

    protected function importerSejours(): void
    {
        $lignes = $this->source->table('ligne_reservation_facture')->get()->groupBy('id_reser_fact');
        $n = 0;

        foreach ($this->source->table('reservation_facture')->get() as $f) {
            $arrivee = Carbon::parse($f->date_deb_reser_fact)->startOfDay();
            $depart = Carbon::parse($f->date_fin_reser_fact)->startOfDay();
            $nuits = max(1, (int) $arrivee->diffInDays($depart));

            foreach ($lignes[$f->id_reser_fact] ?? [] as $l) {
                if (!isset($this->map['lot'][$l->id_bien], $this->map['client'][$f->id_client])) {
                    continue;
                }
                $montant = Montant::mul($l->montant_bien, $nuits);
                $sejour = Sejour::create([
                    'numero' => ($f->code_fact ?: 'SEJ-LEG-' . $f->id_reser_fact) . '-' . $l->id_ligne_reser_fact,
                    'bien_id' => $this->map['lot'][$l->id_bien],
                    'tiers_id' => $this->map['client'][$f->id_client],
                    'date_arrivee' => $arrivee,
                    'date_depart' => $depart,
                    'nb_nuits' => $nuits,
                    'prix_nuitee' => Montant::normaliser($l->montant_bien),
                    'montant' => $montant,
                    'statut' => $f->flag_annule_fact ? 'annule' : ($depart->isPast() ? 'termine' : 'confirme'),
                    'legacy_id' => $f->id_reser_fact,
                ]);
                if ($f->flag_solde_fact) {
                    $this->paiementHistorique($sejour->tiers_id, $sejour, $montant, $f->date_val_reser_fact ?? $f->date_deb_reser_fact, 'S' . $l->id_ligne_reser_fact, 'meuble');
                }
                $n++;
            }
        }
        $this->compter('Séjours meublés', $n);
    }

    protected function importerCrm(): void
    {
        $rdv = 0;
        foreach ($this->source->table('rendez_vous')->get() as $r) {
            $date = $this->date($r->date_reel_rdv) ?? $this->date($r->date_report_rdv) ?? $this->date($r->date_prevu_rdv);
            if (!$date) {
                continue;
            }
            $heure = preg_match('/^\d{1,2}:\d{2}/', (string) ($r->heure_reel_rdv ?: $r->heure_prevu_rdv)) ? substr($r->heure_reel_rdv ?: $r->heure_prevu_rdv, 0, 5) : '09:00';
            \App\Models\RendezVous::create([
                'tiers_id' => $this->map['client'][$r->id_cli] ?? null,
                'bien_id' => $this->map['lot'][$r->id_bien_locati] ?? null,
                'programme_id' => $this->map['site'][$r->id_site] ?? null,
                'user_id' => $this->map['user'][$r->id_user] ?? null,
                'debut' => Carbon::parse($date . ' ' . $heure),
                'statut' => $r->date_reel_rdv ? 'realise' : ($r->date_report_rdv ? 'reporte' : ($r->flag_encours ? 'planifie' : 'realise')),
                'commentaire' => $r->commentaire_rdv,
                'compte_rendu' => $r->commentaire_apres_rdv,
                'source' => $r->flag_site_public ? 'vitrine' : 'backoffice',
                'legacy_id' => $r->id_rdv,
            ]);
            $rdv++;
        }
        $this->compter('Rendez-vous', $rdv);

        $pros = 0;
        foreach ($this->source->table('prospection')->get() as $p) {
            if (!isset($this->map['client'][$p->id_cli])) {
                continue;
            }
            \App\Models\Prospect::create([
                'tiers_id' => $this->map['client'][$p->id_cli],
                'commercial_id' => $this->map['user'][$p->id_user] ?? null,
                'programme_id' => $this->map['site'][$p->id_site] ?? null,
                'type_bien' => $this->map['type_bien'][$p->id_tbien] ?? null,
                'superficie_min' => $this->nombre($p->superficie_pros),
                'commentaire' => $p->commentaire_pros,
                'legacy_id' => $p->id_pros,
            ]);
            $pros++;
        }
        $this->compter('Prospects', $pros);

        foreach ($this->source->table('reclamation')->get() as $r) {
            \App\Models\Reclamation::create([
                'tiers_id' => $this->map['client'][$r->id_cli] ?? null,
                'objet' => $r->objet_reclam ?: 'Réclamation',
                'message' => $r->message_reclam ?: '—',
                'type' => $this->referentiel('type_reclamation', $r->type_reclam ?: 'Autre'),
                'statut' => $r->flag_reclam ? 'resolue' : 'ouverte',
                'created_by' => $this->map['user'][$r->id_user] ?? null,
                'legacy_id' => $r->id_reclam,
                'created_at' => $r->created_at,
            ]);
        }
        foreach ($this->source->table('contact')->get() as $c) {
            \App\Models\DemandeContact::create([
                'nom' => $c->nom_prenom_contact ?: 'Contact', 'email' => $c->email_contact, 'telephone' => $c->contact_contact,
                'sujet' => $c->sujet_contact, 'message' => $c->message_contact ?: '—', 'statut' => $c->flag_consulter ? 'traitee' : 'nouvelle',
                'created_at' => $c->created_at,
            ]);
        }
        $this->compter('Réclamations / contacts', $this->source->table('reclamation')->count() + $this->source->table('contact')->count());
    }

    protected function importerVitrine(): void
    {
        $n = 0;
        foreach ($this->source->table('actualite')->get() as $a) {
            $this->publication('actualite', $a->titre_actualite, $a->description_actualite, null, $a->flag_actualite, $a->date_pub_actu ?? $a->created_at);
            $n++;
        }
        foreach ($this->source->table('article')->get() as $a) {
            $this->publication('article', $a->titre_article, $a->description_detaille ?: $a->description_article, $a->sous_titre, $a->flag_article, $a->created_at, $a->lien_video);
            $n++;
        }
        foreach ($this->source->table('slide')->get() as $s) {
            \App\Models\Slide::create(['titre' => $s->titre_slide, 'sous_titre' => Str::limit(strip_tags((string) $s->description_slide), 250), 'bouton_libelle' => $s->libelle_bouton_slide,
                'bouton_lien' => $s->lien_bouton_slide, 'actif' => (bool) $s->flag_slide]);
            $n++;
        }
        foreach ($this->source->table('temoignange')->get() as $t) {
            $colonnes = (array) $t;
            \App\Models\Temoignage::create([
                'auteur' => $colonnes['nom_prenom_temoignange'] ?? $colonnes['nom_temoignange'] ?? 'Client',
                'contenu' => strip_tags((string) ($colonnes['description_temoignange'] ?? $colonnes['message_temoignange'] ?? '')) ?: '—',
                'actif' => (bool) ($colonnes['flag_temoignange'] ?? true),
            ]);
            $n++;
        }
        $this->compter('Contenus du site vitrine', $n);
    }

    // ------------------------------------------------------------------ outils

    /**
     * Recrée un paiement historique : encaissement daté « LEG-… » affecté à la dette.
     */
    protected function paiementHistorique(?int $tiersId, $dette, $montant, $date, string $reference, string $activite, $mode = null): void
    {
        $montant = Montant::min(Montant::normaliser($montant), $dette->resteAPayer());
        if (!Montant::estPositif($montant)) {
            return;
        }

        $encaissement = Encaissement::create([
            'numero' => 'LEG-' . $reference,
            'tiers_id' => $tiersId,
            'date_encaissement' => Carbon::parse($date)->toDateString(),
            'montant' => $montant,
            'mode_paiement' => $this->map['mode'][$mode] ?? 'ESPECES',
            'activite' => $activite,
            'observation' => 'Reprise de l\'ancienne application',
            'statut' => 'valide',
        ]);
        $encaissement->affectations()->create(['affectable_type' => $dette->getMorphClass(), 'affectable_id' => $dette->getKey(), 'montant' => $montant]);
        $dette->imputer($montant);

        if ($dette instanceof Loyer && $dette->estSolde()) {
            $dette->update(['quittance_numero' => 'QT-LEG-' . $reference]);
        }
    }

    protected function publication(string $type, ?string $titre, ?string $contenu, ?string $resume, $publie, $date, ?string $video = null): void
    {
        $titre = trim((string) $titre) ?: 'Publication';
        $slug = Str::slug($titre) ?: 'publication';
        $i = 1;
        while (\App\Models\Publication::where('slug', $slug)->exists()) {
            $slug = Str::slug($titre) . '-' . ++$i;
        }

        \App\Models\Publication::create([
            'type' => $type, 'titre' => $titre, 'slug' => $slug, 'resume' => $resume ? Str::limit(strip_tags($resume), 490) : null,
            'contenu' => strip_tags((string) $contenu), 'publie' => (bool) $publie, 'publie_le' => $date, 'video_url' => $video && filter_var($video, FILTER_VALIDATE_URL) ? $video : null,
        ]);
    }

    /**
     * Code d'une valeur de liste, créée si elle n'existe pas encore (recherche par libellé puis par code).
     */
    protected function referentiel(string $categorie, string $libelle): string
    {
        $libelle = trim($libelle);
        $code = strtoupper(Str::slug($libelle, '_'));

        $existant = Referentiel::where('categorie', $categorie)
            ->where(fn ($q) => $q->whereRaw('lower(libelle) = ?', [mb_strtolower($libelle)])->orWhere('code', $code))->first();
        if ($existant) {
            return $existant->code;
        }

        Referentiel::create(['categorie' => $categorie, 'code' => $code, 'libelle' => Str::ucfirst(Str::lower($libelle)), 'ordre' => 99, 'actif' => true]);

        return $code;
    }

    protected function parametreSource(string $type): ?string
    {
        try {
            return $this->source->table('logo')->where('valeur', $type)->where('flag_logo', true)->value('mot_cle');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        $propre = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $valeur);

        return is_numeric($propre) ? (float) $propre : null;
    }

    protected function entier($valeur): ?int
    {
        $n = $this->nombre($valeur);

        return $n === null ? null : (int) $n;
    }

    protected function coordonnee($valeur, int $limite): ?float
    {
        $n = $this->nombre($valeur);

        return $n !== null && abs($n) <= $limite ? $n : null;
    }

    protected function date($valeur): ?string
    {
        if (!$valeur || str_starts_with((string) $valeur, '0000')) {
            return null;
        }
        try {
            return Carbon::parse($valeur)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function compter(string $libelle, int $nombre): void
    {
        $this->rapport[$libelle] = $nombre;
        $this->line(sprintf('  %-55s %6d', $libelle, $nombre));
    }

    /**
     * Supprime les données métier de l'agence (ordre compatible avec les clés étrangères).
     */
    protected function purger(): void
    {
        $id = $this->agence->id;
        $parAgence = fn (string $table) => DB::table($table)->where('agence_id', $id)->delete();
        $parParent = fn (string $table, string $colonne, string $parent) => DB::table($table)->whereIn($colonne, DB::table($parent)->where('agence_id', $id)->select('id'))->delete();

        $parParent('encaissement_affectations', 'encaissement_id', 'encaissements');
        $parAgence('encaissements');
        $parParent('campagne_destinataires', 'campagne_id', 'campagnes');
        $parAgence('campagnes');
        $parAgence('appel_lignes');
        $parAgence('appels_fonds');
        $parParent('resolutions', 'assemblee_id', 'assemblees');
        $parAgence('assemblees');
        DB::table('budget_lignes')->whereIn('budget_id', DB::table('budgets')->whereIn('copropriete_id', DB::table('coproprietes')->where('agence_id', $id)->select('id'))->select('id'))->delete();
        $parParent('budgets', 'copropriete_id', 'coproprietes');
        DB::table('cle_lots')->whereIn('cle_repartition_id', DB::table('cles_repartition')->whereIn('copropriete_id', DB::table('coproprietes')->where('agence_id', $id)->select('id'))->select('id'))->delete();
        $parParent('cles_repartition', 'copropriete_id', 'coproprietes');
        $parParent('copro_lots', 'copropriete_id', 'coproprietes');
        $parAgence('interventions');
        DB::table('ventes')->where('agence_id', $id)->update(['depense_remboursement_id' => null]);
        $parAgence('depenses');
        $parAgence('coproprietes');
        $parAgence('echeances');
        $parAgence('vente_frais');
        $parAgence('reservations');
        $parParent('vente_biens', 'vente_id', 'ventes');
        DB::table('ventes')->where('agence_id', $id)->update(['vente_origine_id' => null]);
        $parAgence('ventes');
        $parParent('reversement_lignes', 'reversement_id', 'reversements');
        $parAgence('loyers');
        $parAgence('etats_lieux');
        $parAgence('reversements');
        $parAgence('baux');
        $parAgence('sejours');
        $parAgence('rendez_vous');
        $parAgence('prospects');
        $parAgence('reclamations');
        $parAgence('demandes_contact');
        $parAgence('medias');
        DB::table('biens')->where('agence_id', $id)->update(['mandat_id' => null]);
        $parAgence('biens');
        $parAgence('mandats');
        $parAgence('immeubles');
        $parAgence('lotissements');
        $parAgence('programmes');
        $parAgence('tiers_pieces');
        $parParent('tiers_roles', 'tiers_id', 'tiers');
        $parAgence('tiers');
        foreach (['publications', 'slides', 'temoignages', 'objectifs', 'sequences'] as $table) {
            $parAgence($table);
        }

        $emails = $this->source->table('users')->pluck('email');
        $users = User::where('agence_id', $id)->whereIn('email', $emails)->pluck('id');
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $users)->delete();
        DB::table('historique_mot_de_passe')->whereIn('id_utilisateur', $users)->delete();
        User::whereIn('id', $users)->delete();

        Referentiel::oublier();
        $this->line('  Données de l\'agence supprimées.');
    }
}
