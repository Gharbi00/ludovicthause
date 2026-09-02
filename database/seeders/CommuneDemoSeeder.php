<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;

class CommuneDemoSeeder extends Seeder
{
    /**
     * Villes ÉTRANGÈRES (voyages transfrontaliers) — absentes de la BAN française.
     * Les communes françaises proviennent de `communes:import` (BAN complète) :
     * on ne les remet PAS ici pour éviter les collisions sur code_insee.
     */
    public function run(): void
    {
        $villes = [
            ['nom' => 'Genève (Suisse)',        'code_postal' => '1200',  'latitude' => 46.2044, 'longitude' => 6.1432],
            ['nom' => 'Barcelone (Espagne)',    'code_postal' => '08001', 'latitude' => 41.3851, 'longitude' => 2.1734],
            ['nom' => 'Milan (Italie)',         'code_postal' => '20100', 'latitude' => 45.4642, 'longitude' => 9.1900],
            ['nom' => 'Bruxelles (Belgique)',   'code_postal' => '1000',  'latitude' => 50.8503, 'longitude' => 4.3517],
            ['nom' => 'Francfort (Allemagne)',  'code_postal' => '60311', 'latitude' => 50.1109, 'longitude' => 8.6821],
        ];

        foreach ($villes as $v) {
            Commune::updateOrCreate(
                ['nom' => $v['nom'], 'code_postal' => $v['code_postal']],
                $v + ['code_insee' => null],
            );
        }
    }
}
