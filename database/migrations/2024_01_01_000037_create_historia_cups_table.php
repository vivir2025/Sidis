<?php
// database/migrations/2024_01_01_000037_create_historia_cups_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historia_cups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('historia_clinica_id');
            $table->unsignedBigInteger('cups_id');
            $table->string('observacion', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->foreign('cups_id')->references('id')->on('cups');
            $table->index(['historia_clinica_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('historia_cups');
    }
};
