<?php
// database/migrations/2024_01_01_000038_create_historia_diagnosticos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historia_diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('historia_clinica_id');
            $table->unsignedBigInteger('diagnostico_id');
            $table->string('tipo', 50);
            $table->string('tipo_diagnostico', 50);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->foreign('diagnostico_id')->references('id')->on('diagnosticos');
            $table->index(['historia_clinica_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('historia_diagnosticos');
    }
};
