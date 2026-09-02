<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Espace secrétaire' }} — LTT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ltt.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: {
            brand: { DEFAULT: '#15663a', dark: '#0c3d20', light: '#7cb342' }
        } } } }
    </script>
    @livewireStyles
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-full">
        <nav class="bg-brand-dark text-white">
            <div class="mx-auto max-w-6xl px-4">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-6">
                        <a href="{{ route('admin.demandes') }}" title="Accueil — Ludovic Thause Tourisme"
                           class="inline-flex items-center shrink-0">
                            <span class="inline-flex rounded-md bg-white px-2 py-1">
                                <img src="{{ asset('images/logo-ltt.png') }}" alt="Ludovic Thause Tourisme" class="block h-7 w-auto">
                            </span>
                        </a>
                        <a href="{{ route('admin.demandes') }}"
                           class="text-sm {{ request()->routeIs('admin.demande*') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                            Demandes
                        </a>
                        @if (auth()->user()?->isAdmin())
                            <a href="{{ route('admin.reglages') }}"
                               class="text-sm {{ request()->routeIs('admin.reglages*') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                                Réglages
                            </a>
                        @endif
                        <a href="{{ route('admin.aide') }}" target="_blank" rel="noopener"
                           class="text-sm text-white/70 hover:text-white">
                            Mode d'emploi ↗
                        </a>
                        <a href="{{ route('admin.motdepasse') }}"
                           class="text-sm {{ request()->routeIs('admin.motdepasse') ? 'text-white font-medium' : 'text-white/70 hover:text-white' }}">
                            Mon mot de passe
                        </a>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-white/70 hidden sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md bg-white/15 px-3 py-1.5 hover:bg-white/25">Déconnexion</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 ring-1 ring-green-200">
                    {{ session('status') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    <x-demo-notice area="backend" />

    <script>
        // Bascule afficher/masquer pour les champs mot de passe (bouton œil).
        function basculerMdp(btn) {
            const champ = btn.parentElement.querySelector('input');
            if (! champ) return;
            const oeil = btn.querySelector('[data-oeil]');
            const oeilBarre = btn.querySelector('[data-oeil-off]');
            const masque = champ.type === 'password';
            champ.type = masque ? 'text' : 'password';
            oeil.classList.toggle('hidden', masque);
            oeilBarre.classList.toggle('hidden', !masque);
            btn.setAttribute('aria-label', masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        }
    </script>

    @livewireScripts
</body>
</html>
