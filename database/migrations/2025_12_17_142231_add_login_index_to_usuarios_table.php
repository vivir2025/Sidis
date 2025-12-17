<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Agregar índice en login para optimizar búsquedas
            // La columna login ya tiene unique, pero esto mejora el rendimiento
            $table->index('estado_id', 'idx_usuarios_estado');
            $table->index('rol_id', 'idx_usuarios_rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('idx_usuarios_estado');
            $table->dropIndex('idx_usuarios_rol');
        });
    }
};
