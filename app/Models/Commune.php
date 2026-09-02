<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Commune extends Model
{
    use HasFactory;

    protected $table = 'communes';

    /** Table de référence importée : pas d'horodatage. */
    public $timestamps = false;

    protected $fillable = [
        'code_insee',
        'nom',
        'nom_normalise',
        'code_postal',
        'latitude',
        'longitude',
    ];

    protected static function booted(): void
    {
        // Tient à jour la version normalisée pour la recherche (create/update via modèle).
        static::saving(function (Commune $commune) {
            $commune->nom_normalise = static::normaliser($commune->nom);
        });
    }

    /** Normalise un libellé : minuscules, sans accents, sans tirets/espaces/apostrophes. */
    public static function normaliser(?string $valeur): string
    {
        $valeur = Str::ascii(mb_strtolower((string) $valeur, 'UTF-8'));

        return preg_replace('/[^a-z0-9]/', '', $valeur) ?? '';
    }

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(Etape::class);
    }
}
