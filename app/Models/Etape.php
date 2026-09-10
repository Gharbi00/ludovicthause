<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Etape extends Model
{
    use HasFactory;

    protected $table = 'etapes';

    protected $fillable = [
        'demande_id',
        'ordre',
        'commune_id',
        'ville',
        'adresse',
        'lieu_libelle',
        'adresse_normalisee',
        'geocodage_source',
        'geocodage_provider_id',
        'lieu_acces',
        'lieu_contact',
        'lieu_commentaire',
        'latitude',
        'longitude',
        'date',
        'heure_arrivee',
        'heure_depart',
        'arrivee_imperative',
        'depart_imperatif',
        'temps_attente_minutes',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'date' => 'date',
            'arrivee_imperative' => 'boolean',
            'depart_imperatif' => 'boolean',
            'temps_attente_minutes' => 'integer',
        ];
    }

    /** Libellé d'affichage : adresse précise si dispo (elle contient déjà la ville), sinon ville. */
    public function libelle(): string
    {
        return $this->adresse ?: ($this->ville ?: ($this->commune?->nom ?? '—'));
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
