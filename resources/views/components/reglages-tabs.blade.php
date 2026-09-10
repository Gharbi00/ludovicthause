@php
    $onglets = [
        'admin.reglages'           => 'Paramètres',
        'admin.reglages.societe'   => 'Société',
        'admin.reglages.pdf'       => 'Devis PDF',
        'admin.reglages.vehicules' => 'Véhicules & charges',
        'admin.reglages.emails'    => 'E-mails',
        'admin.reglages.utilisateurs' => 'Utilisateurs & journal',
        'admin.reglages.api'       => 'API & cache',
    ];
@endphp
<div class="mb-6 border-b border-slate-200">
    <nav class="flex flex-wrap gap-1 -mb-px">
        @foreach ($onglets as $route => $libelle)
            <a href="{{ route($route) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 {{ request()->routeIs($route) ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                {{ $libelle }}
            </a>
        @endforeach
    </nav>
</div>
