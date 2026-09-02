<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Ludovic Thause Tourisme — Demande de devis' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ltt.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                brand: { DEFAULT: '#15663a', dark: '#0c3d20', light: '#7cb342' }
            } } }
        }
    </script>
    <style>
        body { background-color: #dde6d9; }               /* fond de page teinté (contraste) */
        main section.rounded-2xl { background-color: #eef4ec !important; } /* cartes vert très clair */
        /* champs BLANCS qui ressortent sur les cartes teintées */
        main input:not([type=checkbox]):not([type=radio]),
        main select, main textarea { background-color: #ffffff; border-color: #a9bba4; }
    </style>
    @livewireStyles
</head>
<body class="h-full text-slate-800 antialiased">
    <header class="bg-white border-b-4 border-brand shadow-sm">
        <div class="mx-auto max-w-3xl px-4 py-3 flex items-center gap-4">
            <img src="{{ asset('images/logo-ltt.png') }}" alt="Ludovic Thause Tourisme" class="h-12 w-auto">
            <div class="border-l border-slate-200 pl-4">
                <p class="text-sm font-semibold text-brand-dark">Demande de devis</p>
                <p class="text-xs text-slate-500">Transport de voyageurs en autocar</p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-3xl px-4 py-6 text-center text-xs text-slate-500">
        {{ \App\Models\Parametre::get('societe_nom', 'Ludovic Thause Tourisme') }} — {{ \App\Models\Parametre::get('societe_cp_ville', 'Varennes-Vauzelles') }}
    </footer>

    <x-demo-notice area="public" />

    @livewireScripts
</body>
</html>
