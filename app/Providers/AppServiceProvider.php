<?php

namespace App\Providers;

use App\Models\Parametre;
use App\Services\Itineraire\EstimationItineraireProvider;
use App\Services\Itineraire\HereItineraireProvider;
use App\Services\Itineraire\ItineraireProvider;
use App\Services\Itineraire\OpenRouteServiceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fournisseur d'itinéraire, par ordre de préférence :
        //   HERE (routage + péages) > OpenRouteService (routage réel, péage estimé) > estimation.
        $this->app->bind(ItineraireProvider::class, function ($app) {
            try {
                $cleHere = Parametre::get('here_api_key');
                $cleOrs  = Parametre::get('ors_api_key');
            } catch (\Throwable $e) {
                $cleHere = $cleOrs = null; // base non disponible (ex. pendant les migrations)
            }

            if ($cleHere) {
                return new HereItineraireProvider($cleHere);
            }
            if ($cleOrs) {
                return new OpenRouteServiceProvider($cleOrs);
            }

            return $app->make(EstimationItineraireProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
