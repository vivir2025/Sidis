<?php
// database/migrations/2024_01_01_000040_create_historia_remisiones_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historia_remisiones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('remision_id');
            $table->unsignedBigInteger('historia_clinica_id');
            $table->string('observacion', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('remision_id')->references('id')->on('remisiones');
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->index(['historia_clinica_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('historia_remisiones');
    }
};
