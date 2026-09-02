<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — Espace secrétaire LTT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ltt.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: {
            brand: { DEFAULT: '#15663a', dark: '#0c3d20', light: '#7cb342' }
        } } } }
    </script>
</head>
<body class="h-full flex items-center justify-center p-4" style="background-color:#dde6d9">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <img src="{{ asset('images/logo-ltt.png') }}" alt="Ludovic Thause Tourisme" class="mx-auto h-16 w-auto">
            <h1 class="mt-4 text-lg font-semibold text-brand-dark">Espace secrétaire</h1>
        </div>

        <form method="POST" action="{{ route('login') }}" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 p-6 space-y-4">
            @csrf
            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Mot de passe</label>
                <div class="relative mt-1">
                    <input id="login-password" type="password" name="password" required
                           class="w-full rounded-lg border-slate-300 pr-10 shadow-sm focus:border-brand focus:ring-brand">
                    <button type="button" onclick="basculerMdp(this)" aria-label="Afficher le mot de passe"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5" data-oeil>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 hidden" data-oeil-off>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand focus:ring-brand">
                Se souvenir de moi
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-brand px-4 py-2.5 text-white font-semibold hover:bg-brand-dark">
                Se connecter
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-500">
            <a href="{{ url('/') }}" class="hover:text-brand">← Retour au formulaire public</a>
        </p>
    </div>

    <script>
        function basculerMdp(btn) {
            const champ = document.getElementById('login-password');
            const oeil = btn.querySelector('[data-oeil]');
            const oeilBarre = btn.querySelector('[data-oeil-off]');
            const masque = champ.type === 'password';
            champ.type = masque ? 'text' : 'password';
            oeil.classList.toggle('hidden', masque);
            oeilBarre.classList.toggle('hidden', !masque);
            btn.setAttribute('aria-label', masque ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        }
    </script>
</body>
</html>
