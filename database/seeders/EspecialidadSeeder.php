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
            ['id' => 2, 'codigo' => 'CA002', 'nombre' => 'CARDIOLOGÍA'],
            ['id' => 3, 'codigo' => 'PE003', 'nombre' => 'PEDIATRÍA'],
            ['id' => 4, 'codigo' => 'GI004', 'nombre' => 'GINECOLOGÍA'],
            ['id' => 5, 'codigo' => 'NE005', 'nombre' => 'NEUROLOGÍA'],
            ['id' => 6, 'codigo' => 'DE006', 'nombre' => 'DERMATOLOGÍA'],
            ['id' => 7, 'codigo' => 'OR007', 'nombre' => 'ORTOPEDIA'],
            ['id' => 8, 'codigo' => 'PS008', 'nombre' => 'PSIQUIATRÍA']
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
