<div>
    <h1 class="text-2xl font-semibold text-slate-900 mb-4">Tableau de bord API</h1>

    <x-reglages-tabs />

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Appels ce mois</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($total, 0, ',', ' ') }}</div>
            <div class="text-xs text-slate-500">Plafond : {{ number_format($plafond, 0, ',', ' ') }}</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Depuis le cache</div>
            <div class="mt-1 text-2xl font-bold text-green-700">{{ number_format($cached, 0, ',', ' ') }}</div>
            <div class="text-xs text-slate-500">Taux de cache : {{ $taux_cache }} %</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Fournisseurs</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $par_fournisseur->count() }}</div>
            <div class="text-xs text-slate-500">Actifs ce mois</div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">Par fournisseur</h2>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-3 py-2">Fournisseur</th><th class="px-3 py-2">Appels</th><th class="px-3 py-2">Cache</th><th class="px-3 py-2">Taux</th></tr>
            </thead>
            <tbody>
                @foreach ($par_fournisseur as $f)
                    @php $taux = $f->total > 0 ? round($f->cached / $f->total * 100, 1) : 0; @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-medium">{{ strtoupper($f->provider) }}</td>
                        <td class="px-3 py-2">{{ number_format($f->total, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2">{{ number_format($f->cached, 0, ',', ' ') }}</td>
                        <td class="px-3 py-2">{{ $taux }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">50 derniers appels</h2>
        <table class="min-w-full text-xs">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-2 py-2">Date</th><th class="px-2 py-2">Fournisseur</th><th class="px-2 py-2">Cache</th><th class="px-2 py-2">Durée (ms)</th><th class="px-2 py-2">HTTP</th></tr>
            </thead>
            <tbody>
                @foreach ($recent as $u)
                    <tr class="border-t border-slate-100">
                        <td class="px-2 py-2">{{ $u->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-2 py-2">{{ strtoupper($u->provider) }}</td>
                        <td class="px-2 py-2">{{ $u->cached ? 'Oui' : 'Non' }}</td>
                        <td class="px-2 py-2">{{ $u->response_time_ms ?? '—' }}</td>
                        <td class="px-2 py-2">{{ $u->http_status ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
