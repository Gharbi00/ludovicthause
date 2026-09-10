<?php

namespace App\Console\Commands;

use App\Models\Commune;
use Illuminate\Console\Command;

class ImportCommunes extends Command
{
    protected $signature = 'communes:import {--fichier= : Chemin du CSV (défaut : database/data/communes.csv)}';

    protected $description = 'Importe les communes françaises (INSEE, CP, GPS) depuis le CSV embarqué, par upsert.';

    public function handle(): int
    {
        $fichier = $this->option('fichier') ?: database_path('data/communes.csv');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : $fichier");

            return self::FAILURE;
        }

        $handle = fopen($fichier, 'r');
        fgetcsv($handle); // en-tête

        $lot = [];
        $total = 0;
        $taille = 1000;

        while (($ligne = fgetcsv($handle)) !== false) {
            [$insee, $nom, $cp, $lat, $lng] = array_pad($ligne, 5, null);
            if (! $insee || ! $nom) {
                continue;
            }

            $lot[] = [
                'code_insee' => $insee,
                'nom' => $nom,
                'nom_normalise' => Commune::normaliser($nom),
                'code_postal' => $cp ?: null,
                'latitude' => $lat !== '' ? $lat : null,
                'longitude' => $lng !== '' ? $lng : null,
            ];

            if (count($lot) >= $taille) {
                $this->flush($lot);
                $total += count($lot);
                $lot = [];
            }
        }

        if ($lot) {
            $this->flush($lot);
            $total += count($lot);
        }

        fclose($handle);

        $this->info("Import terminé : $total communes (total en base : ".Commune::count().').');

        return self::SUCCESS;
    }

    /** Upsert d'un lot (mise à jour si le code INSEE existe, insertion sinon). */
    protected function flush(array $lot): void
    {
        Commune::upsert($lot, ['code_insee'], ['nom', 'nom_normalise', 'code_postal', 'latitude', 'longitude']);
    }
}
