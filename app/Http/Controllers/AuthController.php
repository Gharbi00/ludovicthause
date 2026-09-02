<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('admin.demandes');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Limitation anti-force brute : 5 essais / minute / (email+IP)
        $cle = 'login:' . strtolower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans une minute.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($cle, 60);
            \App\Models\Journal::enregistrer('Échec de connexion', null, strtolower($credentials['email']));
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();
        \App\Models\Journal::enregistrer('Connexion');

        return redirect()->intended(route('admin.demandes'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
