<?php

namespace App\Livewire\Admin;

use App\Models\ApiUsage;
use App\Models\Parametre;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class ApiDashboard extends Component
{
    public ?string $provider = null;

    public function render()
    {
        $debut = now()->startOfMonth();

        $query = ApiUsage::query()->where('created_at', '>=', $debut);
        if ($this->provider) {
            $query->where('provider', $this->provider);
        }

        $total = $query->count();
        $cached = $query->where('cached', true)->count();
        $tauxCache = $total > 0 ? round($cached / $total * 100, 1) : 0.0;

        $parFournisseur = ApiUsage::query()
            ->select('provider', DB::raw('COUNT(*) as total'), DB::raw('SUM(cached) as cached'))
            ->where('created_at', '>=', $debut)
            ->groupBy('provider')
            ->get();

        $recent = $query->orderByDesc('created_at')->limit(50)->get();

        $plafond = (int) Parametre::get('api_plafond_mensuel', 10000);

        return view('livewire.admin.api-dashboard', [
            'total' => $total,
            'cached' => $cached,
            'taux_cache' => $tauxCache,
            'par_fournisseur' => $parFournisseur,
            'recent' => $recent,
            'plafond' => $plafond,
        ]);
    }
}
