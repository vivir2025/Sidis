<?php
// database/seeders/RolSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use Illuminate\Support\Str;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'nombre' => 'ADMINISTRADOR',
                'descripcion' => 'Administrador del sistema con acceso completo',
                'activo' => true
            ],
            [
                'id' => 2,
                'nombre' => 'MÉDICO',
                'descripcion' => 'Médico con acceso a funciones clínicas',
                'activo' => true
            ],
            [
                'id' => 3,
                'nombre' => 'ENFERMERO',
                'descripcion' => 'Enfermero con acceso a funciones de enfermería',
                'activo' => true
            ],
            [
                'id' => 4,
                'nombre' => 'AUXILIAR',
                'descripcion' => 'Auxiliar de enfermería',
                'activo' => true
            ],
            [
                'id' => 5,
                'nombre' => 'RECEPCIONISTA',
                'descripcion' => 'Recepcionista con acceso a funciones administrativas básicas',
                'activo' => true
            ]
        ];

        foreach ($roles as $rolData) {
            Rol::updateOrCreate(
                ['id' => $rolData['id']],
                array_merge($rolData, ['uuid' => Str::uuid()])
            );
        }

        echo "✅ Roles creados exitosamente\n";
    }
}
