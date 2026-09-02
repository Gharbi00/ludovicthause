<?php

namespace App\Services;

use App\Models\Parametre;
use Illuminate\Support\Facades\Http;

/**
 * Récupère le prix moyen national du gazole depuis l'open data prix-carburants.gouv.fr
 * (data.economie.gouv.fr) et met à jour le paramètre prix_gasoil_litre.
 */
class PrixGasoilProvider
{
    private const URL = 'https://data.economie.gouv.fr/api/explore/v2.1/catalog/datasets/prix-des-carburants-en-france-flux-instantane-v2/records';

    /** Moyenne nationale du prix du gazole (€/L), ou null si indisponible. */
    public function prixMoyenNational(): ?float
    {
        try {
            $resp = Http::timeout(20)->get(self::URL, [
                'select' => 'avg(gazole_prix) as prix_moyen',
                'limit'  => 1,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $resp->ok()) {
            return null;
        }

        $prix = data_get($resp->json(), 'results.0.prix_moyen');

        return $prix ? round((float) $prix, 3) : null;
    }

    /** Actualise le paramètre prix_gasoil_litre. Retourne le nouveau prix ou null. */
    public function actualiser(): ?float
    {
        $prix = $this->prixMoyenNational();

        if ($prix !== null) {
            Parametre::set('prix_gasoil_litre', $prix);
        }

        return $prix;
    }
}
