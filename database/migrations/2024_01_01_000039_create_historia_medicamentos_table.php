<?php
// database/migrations/2024_01_01_000039_create_historia_medicamentos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historia_medicamentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('medicamento_id');
            $table->unsignedBigInteger('historia_clinica_id');
            $table->string('cantidad', 10);
            $table->string('dosis', 500);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('medicamento_id')->references('id')->on('medicamentos');
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->index(['historia_clinica_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('historia_medicamentos');
    }
};
