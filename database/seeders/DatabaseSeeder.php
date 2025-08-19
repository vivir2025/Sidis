<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ORDEN CORRECTO: Primero las tablas padre, luego las hijas
        $this->call([
            EstadoSeeder::class,        // ← Crear estados primero
            RolSeeder::class,           // ← Crear roles primero
            SedeSeeder::class,          // ← Crear sedes primero
            EspecialidadSeeder::class,  // ← Crear especialidades primero
            UsuarioSeeder::class,       // ← Crear usuarios al final
        ]);
        
        $this->command->info('🎉 Todos los seeders ejecutados exitosamente');
    }
}
