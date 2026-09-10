<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Planning extends Model
{
    use HasFactory;

    protected $table = 'planning';

    protected $fillable = [
        'vehicule_id',
        'date_debut',
        'date_fin',
        'statut',
        'motif',
        'devis_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    /**
     * Filtre les occupations qui chevauchent une période donnée.
     * Sert à vérifier la disponibilité d'un véhicule avant affectation.
     */
    public function scopeChevauche(Builder $query, $debut, $fin): Builder
    {
        return $query->where('date_debut', '<', $fin)
            ->where('date_fin', '>', $debut);
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }
}
