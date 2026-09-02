<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\MaintenanceController;
use App\Livewire\Admin\DemandesList;
use App\Livewire\Admin\DemandeShow;
use App\Livewire\Admin\Reglages;
use App\Livewire\Admin\VehiculesReglages;
use App\Livewire\DemandeForm;
use Illuminate\Support\Facades\Route;

// --- Front public : formulaire de demande de devis (Phase 2) ---
Route::get('/', DemandeForm::class)->name('demande.create');

// --- Authentification (Phase 3) ---
Route::get('/connexion', [AuthController::class, 'show'])->name('login');
Route::post('/connexion', [AuthController::class, 'login']);
Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');

// --- Maintenance à distance (jeton obligatoire ; 404 sinon) ---
Route::get('/_maintenance/{action}', [MaintenanceController::class, 'run'])
    ->where('action', 'migrate|seed-demo|import-communes|gasoil|here-test|ors-test|test-email');

// --- Back-office secrétaire (authentifié) ---
Route::middleware(['auth', 'force.password'])->prefix('secretariat')->group(function () {
    Route::get('/', DemandesList::class)->name('admin.demandes');
    Route::get('/demandes/{demande}', DemandeShow::class)->name('admin.demande');
    Route::get('/devis/{devis}/pdf', [DevisController::class, 'pdf'])->name('admin.devis.pdf');
    Route::get('/devis/{devis}/carte', [DevisController::class, 'carte'])->name('admin.devis.carte');
    Route::get('/mot-de-passe', \App\Livewire\Admin\MotDePasse::class)->name('admin.motdepasse');
    Route::view('/aide', 'aide')->name('admin.aide');

    // Réglages + gestion des comptes : réservés aux administrateurs.
    Route::middleware('admin')->group(function () {
        Route::get('/reglages', Reglages::class)->name('admin.reglages');
        Route::get('/reglages/vehicules', VehiculesReglages::class)->name('admin.reglages.vehicules');
        Route::get('/reglages/societe', Reglages::class)->name('admin.reglages.societe');
        Route::get('/reglages/pdf', Reglages::class)->name('admin.reglages.pdf');
        Route::get('/reglages/emails', \App\Livewire\Admin\EmailsReglages::class)->name('admin.reglages.emails');
        Route::get('/reglages/utilisateurs', \App\Livewire\Admin\Utilisateurs::class)->name('admin.reglages.utilisateurs');
    });
});
