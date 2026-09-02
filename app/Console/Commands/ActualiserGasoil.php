<?php

namespace App\Console\Commands;

use App\Services\PrixGasoilProvider;
use Illuminate\Console\Command;

class ActualiserGasoil extends Command
{
    protected $signature = 'gasoil:actualiser';

    protected $description = 'Met à jour le prix du gasoil (moyenne nationale, open data prix-carburants.gouv.fr).';

    public function handle(PrixGasoilProvider $provider): int
    {
        $prix = $provider->actualiser();

        if ($prix === null) {
            $this->error('Prix du gasoil indisponible (API injoignable).');
            return self::FAILURE;
        }

        $this->info("Prix du gasoil actualisé : {$prix} €/L.");
        return self::SUCCESS;
    }
}
