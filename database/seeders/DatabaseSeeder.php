<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ParametreSeeder::class);
        $this->call(CategorieSeeder::class);
        $this->call(CommuneDemoSeeder::class);
        $this->call(VehiculeReelSeeder::class);

        // Comptes de démonstration (mot de passe : « password » — À CHANGER en prod)
        User::factory()->create([
            'name' => 'Secrétaire LTT',
            'email' => 'secretaire@ludovicthause.fr',
            'role' => 'secretaire',
        ]);
        User::factory()->create([
            'name' => 'Admin LTT',
            'email' => 'admin@ludovicthause.fr',
            'role' => 'admin',
        ]);
    }
}
