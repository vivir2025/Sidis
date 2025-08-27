<?php
// database/seeders/UsuarioSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Usuario;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        
        Usuario::updateOrCreate(
            ['documento' => '12345678'],
            [
                'uuid' => Str::uuid(),
                'sede_id' => 1,
                'documento' => '12345678',
                'nombre' => 'Administrador',
                'apellido' => 'Sistema',
                'telefono' => '3001234567',
                'correo' => 'admin@sistema.com',
                'registro_profesional' => null,
                'firma' => null,
                'login' => 'admin',
                'password' => Hash::make('admin123'),
                'estado_id' => 1,
                'rol_id' => 1,
                'especialidad_id' => null,
                'email_verified_at' => null,
            ]
        );

        // Usuario Médico
        Usuario::updateOrCreate(
            ['documento' => '87654321'],
            [
                'uuid' => Str::uuid(),
                'sede_id' => 1,
                'documento' => '87654321',
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'telefono' => '3009876543',
                'correo' => 'medico@sistema.com',
                'registro_profesional' => 'MP12345',
                'firma' => null,
                'login' => 'medico1',
                'password' => Hash::make('medico123'),
                'estado_id' => 1,
                'rol_id' => 2,
                'especialidad_id' => 1,
                'email_verified_at' => null,
            ]
        );

        // Usuario Enfermero
        Usuario::updateOrCreate(
            ['documento' => '11223344'],
            [
                'uuid' => Str::uuid(),
                'sede_id' => 1,
                'documento' => '11223344',
                'nombre' => 'María',
                'apellido' => 'González',
                'telefono' => '3007654321',
                'correo' => 'enfermero@sistema.com',
                'registro_profesional' => 'ENF5678',
                'firma' => null,
                'login' => 'enfermero1',
                'password' => Hash::make('enfermero123'),
                'estado_id' => 1,
                'rol_id' => 3,
                'especialidad_id' => null,
                'email_verified_at' => null,
            ]
        );

        echo "✅ Usuarios creados/actualizados exitosamente\n";
    }
}
