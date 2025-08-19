<?php
// database/migrations/2024_01_01_000036_create_hc_pdfs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hc_pdfs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('historia_clinica_id');
            $table->string('archivo', 160);
            $table->string('observacion', 160)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->index(['sede_id', 'historia_clinica_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hc_pdfs');
    }
};
