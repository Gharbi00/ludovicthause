<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lieu extends Model
{
    protected $table = 'lieux';

    protected $fillable = [
        'libelle', 'adresse_normalisee', 'ville', 'code_postal',
        'latitude', 'longitude', 'source', 'provider_id',
        'acces', 'contact', 'commentaire', 'utilisations',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'utilisations' => 'integer',
        ];
    }
}
