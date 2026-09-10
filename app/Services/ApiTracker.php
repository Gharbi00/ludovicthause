<?php

namespace App\Services;

use App\Models\ApiUsage;
use App\Models\Parametre;
use Illuminate\Support\Facades\Mail;

class ApiTracker
{
    public function log(string $provider, ?string $endpoint, ?string $cacheKey, bool $cached, ?int $responseTimeMs, ?int $httpStatus, ?int $devisId = null): void
    {
        try {
            ApiUsage::create([
                'provider' => $provider,
                'endpoint' => $endpoint,
                'cache_key' => $cacheKey,
                'cached' => $cached,
                'response_time_ms' => $responseTimeMs,
                'http_status' => $httpStatus,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'devis_id' => $devisId,
            ]);
        } catch (\Throwable $e) {
            // Ne jamais interrompre le flux pour un problème de journalisation.
        }

        $plafond = (int) Parametre::get('api_plafond_mensuel', 10000);
        if ($plafond > 0 && ! $cached) {
            $compte = $this->compteMois($provider);
            if ($compte >= $plafond) {
                $this->envoyerAlerte($provider, $compte, $plafond);
            }
        }
    }

    public function compteMois(?string $provider = null): int
    {
        $debut = now()->startOfMonth();
        $query = ApiUsage::query()->where('created_at', '>=', $debut);
        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->count();
    }

    public function tauxCacheMois(?string $provider = null): float
    {
        $debut = now()->startOfMonth();
        $query = ApiUsage::query()->where('created_at', '>=', $debut);
        if ($provider) {
            $query->where('provider', $provider);
        }
        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        return round($query->where('cached', true)->count() / $total * 100, 1);
    }

    protected function envoyerAlerte(string $provider, int $compte, int $plafond): void
    {
        $destinataire = (string) Parametre::get('api_alerte_email', '');
        if ($destinataire === '' || ! filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::raw(
                "Alerte : plafond API mensuel atteint pour $provider ($compte / $plafond).",
                fn ($m) => $m->to($destinataire)->subject('Alerte plafond API — '.$provider)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
