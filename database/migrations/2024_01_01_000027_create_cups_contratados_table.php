<?php
// database/migrations/2024_01_01_000027_create_cups_contratados_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cups_contratados', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('categoria_cups_id');
            $table->unsignedBigInteger('cups_id');
            $table->string('tarifa', 30);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('contrato_id')->references('id')->on('contratos');
            $table->foreign('categoria_cups_id')->references('id')->on('categorias_cups');
            $table->foreign('cups_id')->references('id')->on('cups');
            
            $table->index(['contrato_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cups_contratados');
    }
};
    