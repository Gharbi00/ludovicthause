<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\CategorieSeeder;
use Database\Seeders\CommuneDemoSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\VehiculeReelSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Maintenance à distance sur l'hébergement mutualisé (pas de PHP en ligne de commande).
 * Toutes les actions sont protégées par un jeton secret (MAINT_TOKEN dans .env) et
 * idempotentes (aucune donnée existante n'est écrasée).
 */
class MaintenanceController extends Controller
{
    public function run(Request $request, string $action)
    {
        $attendu = config('app.maint_token');

        // Jeton absent côté serveur ou incorrect => on masque totalement l'endpoint.
        abort_if(empty($attendu), 404);
        abort_unless(is_string($request->query('token')) && hash_equals($attendu, $request->query('token')), 404);

        return match ($action) {
            'migrate'         => $this->migrate(),
            'seed-demo'       => $this->seedDemo(),
            'import-communes' => $this->importCommunes(),
            'gasoil'          => $this->gasoil(),
            'here-test'       => $this->itineraireTest('here'),
            'ors-test'        => $this->itineraireTest('ors'),
            'test-email'      => $this->testEmail($request),
            default           => abort(404),
        };
    }

    protected function migrate()
    {
        Artisan::call('migrate', ['--force' => true]);

        return response('<pre>' . e(Artisan::output()) . '</pre>');
    }

    protected function gasoil()
    {
        Artisan::call('gasoil:actualiser');

        return response('<pre>' . e(Artisan::output()) . '</pre>');
    }

    /** Diagnostic e-mail : affiche le mailer actif et tente un envoi réel (?to=adresse). */
    protected function testEmail(Request $request)
    {
        $to = (string) $request->query('to');
        $out = 'MAIL_MAILER (config) = ' . config('mail.default') . "\n";
        $out .= 'MAIL_SENDMAIL_PATH = ' . config('mail.mailers.sendmail.path', '(défaut)') . "\n";
        $configuree = (string) \App\Models\Parametre::get('email_from_address', 'contact@doliexpert.fr');
        $from = \App\Services\TransportMail::adresseExpediteur();
        $out .= 'Expéditeur (configuré) = ' . $configuree . "\n";
        $out .= 'Expéditeur (réel, aligné SMTP) = ' . $from . "\n\n";

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response('<pre>' . e($out . "Ajoutez ?to=votre@email.fr pour tester un envoi réel.") . '</pre>');
        }

        // Transport : ?via=... force un mode, sinon celui configuré (paramètres).
        $transport = $request->query('via') ?: \App\Services\TransportMail::resoudre();
        try {
            \Illuminate\Support\Facades\Mail::mailer($transport)->raw(
                "Test d'envoi depuis l'application LTT (" . now()->format('d/m/Y H:i') . ").",
                function ($m) use ($to, $from) {
                    $m->to($to)->from($from, 'LTT test')->subject('Test envoi — LTT');
                }
            );
            $out .= "Envoi via '$transport' : OK (aucune exception). Vérifiez la réception ET les spams de $to.";
        } catch (\Throwable $e) {
            $out .= "ERREUR (via '$transport') : " . $e->getMessage();
        }

        return response('<pre>' . e($out) . '</pre>');
    }

    /** Diagnostic routage : teste un trajet réel (Varennes-Vauzelles → Paris → retour). */
    protected function itineraireTest(string $fournisseur)
    {
        $cleParam = $fournisseur === 'here' ? 'here_api_key' : 'ors_api_key';
        $cle = \App\Models\Parametre::get($cleParam);
        if (empty($cle)) {
            return response('<pre>Aucune clé ' . strtoupper($fournisseur) . ' renseignée (Réglages → Clés d\'API).</pre>');
        }

        $depot = \App\Models\Commune::where('code_insee', '58303')->first();
        $paris = \App\Models\Commune::where('nom', 'Paris')->orderBy('code_postal')->first();
        $veh   = \App\Models\Vehicule::where('actif', true)->orderByDesc('nb_places')->first();

        if (! $depot || ! $paris || ! $veh) {
            return response('<pre>Données manquantes (dépôt / Paris / véhicule).</pre>');
        }

        $points = [
            ['lat' => (float) $depot->latitude, 'lng' => (float) $depot->longitude, 'nom' => $depot->nom],
            ['lat' => (float) $paris->latitude, 'lng' => (float) $paris->longitude, 'nom' => $paris->nom],
            ['lat' => (float) $depot->latitude, 'lng' => (float) $depot->longitude, 'nom' => $depot->nom],
        ];

        $provider = $fournisseur === 'here'
            ? new \App\Services\Itineraire\HereItineraireProvider($cle)
            : new \App\Services\Itineraire\OpenRouteServiceProvider($cle);

        try {
            $r = $provider->calculer($points, 1, 2, $veh);
            $kmPeage = $r->payload['km_a_peage'] ?? null;
            $tarif = $r->payload['tarif_peage_km'] ?? null;
            $out = strtoupper($fournisseur) . " OK ✓\n"
                . "Véhicule : {$veh->immatriculation} ({$veh->nb_essieux} essieux, classe " . $veh->classe_peage . ")\n"
                . "Distance : {$r->distanceKm} km (chargé {$r->distanceKmCharge} / vide {$r->distanceKmVide})\n"
                . "Durée : {$r->dureeConduiteMinutes} min (" . intdiv($r->dureeConduiteMinutes, 60) . 'h' . str_pad($r->dureeConduiteMinutes % 60, 2, '0', STR_PAD_LEFT) . ")\n"
                . ($kmPeage !== null ? "Km à péage : {$kmPeage} km × {$tarif} €/km\n" : '')
                . "Péage : {$r->coutPeage} € · Vignettes : {$r->coutVignettes} € · source={$r->source}";
        } catch (\Throwable $e) {
            $out = strtoupper($fournisseur) . " ERREUR : " . $e->getMessage();
        }

        return response('<pre>' . e($out) . '</pre>');
    }

    protected function importCommunes()
    {
        @set_time_limit(300);
        Artisan::call('communes:import');

        return response('<pre>' . e(Artisan::output()) . '</pre>');
    }

    protected function seedDemo()
    {
        // Référentiels de démo (updateOrCreate : rien n'est écrasé de force).
        app(ParametreSeeder::class)->run();
        app(CategorieSeeder::class)->run();
        app(CommuneDemoSeeder::class)->run();
        app(VehiculeReelSeeder::class)->run();

        // Comptes (créés seulement s'ils n'existent pas ; mot de passe par défaut « password »).
        foreach ([
            ['secretaire@ludovicthause.fr', 'Secrétaire LTT', 'secretaire'],
            ['admin@ludovicthause.fr', 'Admin LTT', 'admin'],
        ] as [$email, $nom, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $nom, 'role' => $role, 'password' => Hash::make('password')],
            );
        }

        return response('<pre>Seed démo OK. '
            . 'Catégories, communes, véhicules et comptes en place (sans écraser les demandes existantes).</pre>');
    }
}
