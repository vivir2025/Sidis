<?php
// database/migrations/2025_12_05_094800_add_clasificacion_erc_estadodos_to_historias_clinicas.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->text('clasificacion_erc_estadodos')->nullable();
        });
    }

    public function down()
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->dropColumn('clasificacion_erc_estadodos');
        });
    }
};
