<?php
// database/migrations/xxxx_xx_xx_add_descripcion_activo_to_roles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('nombre');
            $table->boolean('activo')->default(true)->after('descripcion');
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'activo']);
        });
    }
};
