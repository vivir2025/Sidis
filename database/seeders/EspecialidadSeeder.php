<?php
// database/seeders/EspecialidadSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialidad;
use Illuminate\Support\Str;

class EspecialidadSeeder extends Seeder
{
    public function run(): void
    {
        $especialidades = [
            ['id' => 1, 'codigo' => 'MG001', 'nombre' => 'MEDICINA GENERAL'],
            ['id' => 2, 'codigo' => 'CA002', 'nombre' => 'REFORMULACION'],
            ['id' => 3, 'codigo' => 'PE003', 'nombre' => 'NUTRICIONISTA'],
            ['id' => 4, 'codigo' => 'GI004', 'nombre' => 'PSICOLOGIA'],
            ['id' => 5, 'codigo' => 'NE005', 'nombre' => 'NEFROLOGIA'],
            ['id' => 6, 'codigo' => 'DE006', 'nombre' => 'INTERNISTA'],
            ['id' => 7, 'codigo' => 'OR007', 'nombre' => 'FISIOTERAPIA'],
            ['id' => 8, 'codigo' => 'PS008', 'nombre' => 'TRABAJO SOCIAL']
        ];

        foreach ($especialidades as $especialidadData) {
            Especialidad::updateOrCreate(
                ['id' => $especialidadData['id']],
                [
                    'uuid' => Str::uuid(),
                    'codigo' => $especialidadData['codigo'],
                    'nombre' => $especialidadData['nombre'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Especialidades creadas/actualizadas exitosamente');
    }
}
