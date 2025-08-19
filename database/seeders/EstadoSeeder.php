<?php
// database/seeders/EstadoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estado;
use Illuminate\Support\Str;

class EstadoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['id' => 1, 'nombre' => 'ACTIVO'],
            ['id' => 2, 'nombre' => 'INACTIVO'],
            ['id' => 3, 'nombre' => 'SUSPENDIDO']
        ];

        foreach ($estados as $estadoData) {
            Estado::create([
                'id' => $estadoData['id'],
                'uuid' => Str::uuid(),
                'nombre' => $estadoData['nombre']
            ]);
        }

        echo "✅ Estados creados exitosamente\n";
    }
}
