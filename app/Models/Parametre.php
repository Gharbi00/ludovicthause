<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    use HasFactory;

    protected $table = 'parametres';

    protected $fillable = [
        'cle',
        'valeur',
        'type',
        'groupe',
        'libelle',
    ];

    /**
     * Récupère un paramètre en le convertissant selon son type.
     * Ex : Parametre::get('taux_tva') => 10.0 (float)
     */
    public static function get(string $cle, mixed $defaut = null): mixed
    {
        $parametre = static::query()->where('cle', $cle)->first();

        if (! $parametre || $parametre->valeur === null) {
            return $defaut;
        }

        return match ($parametre->type) {
            'integer' => (int) $parametre->valeur,
            'decimal' => (float) $parametre->valeur,
            'boolean' => filter_var($parametre->valeur, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($parametre->valeur, true),
            default   => $parametre->valeur,
        };
    }

    /**
     * Enregistre la valeur d'un paramètre existant (les tableaux sont encodés en JSON).
     */
    public static function set(string $cle, mixed $valeur): bool
    {
        $parametre = static::query()->where('cle', $cle)->first();

        if (! $parametre) {
            return false;
        }

        $parametre->valeur = is_array($valeur) ? json_encode($valeur) : (string) $valeur;

        return $parametre->save();
    }
}
