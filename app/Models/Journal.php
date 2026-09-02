<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Journal d'activité (connexions, actions sur les devis, gestion des comptes).
 */
class Journal extends Model
{
    protected $table = 'journaux';

    public const UPDATED_AT = null; // pas de colonne updated_at

    protected $fillable = ['utilisateur', 'action', 'details', 'ip'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * Enregistre une entrée. $utilisateur force le libellé (ex. e-mail sur échec de connexion).
     * La journalisation ne doit JAMAIS interrompre le flux principal (connexion, devis) :
     * toute erreur (table absente avant migration, etc.) est silencieusement ignorée.
     */
    public static function enregistrer(string $action, ?string $details = null, ?string $utilisateur = null): void
    {
        try {
            static::create([
                'utilisateur' => $utilisateur ?? Auth::user()?->name ?? '—',
                'action'      => $action,
                'details'     => $details,
                'ip'          => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
