<?php

namespace Database\Seeders;

use App\Models\Pays;
use Illuminate\Database\Seeder;

/**
 * Pays les plus courants (la reprise de l'ancienne base importe la liste complète).
 */
class PaysSeeder extends Seeder
{
    public function run(): void
    {
        $pays = [
            ['CI', "Côte d'Ivoire", 'Ivoirienne', '225'], ['BF', 'Burkina Faso', 'Burkinabè', '226'], ['ML', 'Mali', 'Malienne', '223'],
            ['SN', 'Sénégal', 'Sénégalaise', '221'], ['GN', 'Guinée', 'Guinéenne', '224'], ['GH', 'Ghana', 'Ghanéenne', '233'],
            ['TG', 'Togo', 'Togolaise', '228'], ['BJ', 'Bénin', 'Béninoise', '229'], ['NE', 'Niger', 'Nigérienne', '227'],
            ['NG', 'Nigeria', 'Nigériane', '234'], ['LR', 'Liberia', 'Libérienne', '231'], ['CM', 'Cameroun', 'Camerounaise', '237'],
            ['GA', 'Gabon', 'Gabonaise', '241'], ['CG', 'Congo', 'Congolaise', '242'], ['CD', 'RD Congo', 'Congolaise (RDC)', '243'],
            ['MA', 'Maroc', 'Marocaine', '212'], ['TN', 'Tunisie', 'Tunisienne', '216'], ['DZ', 'Algérie', 'Algérienne', '213'],
            ['LB', 'Liban', 'Libanaise', '961'], ['FR', 'France', 'Française', '33'], ['BE', 'Belgique', 'Belge', '32'],
            ['CH', 'Suisse', 'Suisse', '41'], ['CA', 'Canada', 'Canadienne', '1'], ['US', 'États-Unis', 'Américaine', '1'],
            ['GB', 'Royaume-Uni', 'Britannique', '44'], ['DE', 'Allemagne', 'Allemande', '49'], ['IT', 'Italie', 'Italienne', '39'],
            ['ES', 'Espagne', 'Espagnole', '34'], ['CN', 'Chine', 'Chinoise', '86'], ['IN', 'Inde', 'Indienne', '91'],
        ];

        foreach ($pays as [$code, $libelle, $nationalite, $indicatif]) {
            Pays::updateOrCreate(['code' => $code], compact('libelle', 'nationalite', 'indicatif'));
        }
    }
}
