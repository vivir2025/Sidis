<?php
// database/seeders/SedeSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sede;
use Illuminate\Support\Str;

class SedeSeeder extends Seeder
{
    public function run(): void
    {
        $sedes = [
            [
                'id' => 1,
                'nombre' => 'Sede Principal',
                'direccion' => 'Calle 123 # 45-67',
                'telefono' => '3001234567',
                'ciudad' => 'Bogotá',
                'activo' => true
            ],
            [
                'id' => 2,
                'nombre' => 'Sede Norte',
                'direccion' => 'Carrera 78 # 90-12',
                'telefono' => '3007654321',
                'ciudad' => 'Bogotá',
                'activo' => true
            ]
        ];

        foreach ($sedes as $sedeData) {
            Sede::updateOrCreate(
                ['id' => $sedeData['id']],
                [
                    'uuid' => Str::uuid(),
                    'nombre' => $sedeData['nombre'],
                    'direccion' => $sedeData['direccion'],
                    'telefono' => $sedeData['telefono'],
                    'ciudad' => $sedeData['ciudad'] ?? null,
                    'activo' => $sedeData['activo'] ?? true
                ]
            );
        }

        echo "✅ Sedes creadas/actualizadas exitosamente\n";
    }
}
