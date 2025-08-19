<?php
// database/migrations/2024_01_01_000024_create_contratos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('empresa_id');
            $table->string('numero', 50);
            $table->string('descripcion', 50);
            $table->enum('plan_beneficio', ['POS', 'NO POS']);
            $table->string('poliza', 50);
            $table->string('por_descuento', 50);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('valor', 50);
            $table->date('fecha_registro');
            $table->enum('tipo', ['PGP', 'EVENTO']);
            $table->enum('copago', ['SI', 'NO']);
            $table->enum('estado', ['ACTIVO', 'INACTIVO']);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contratos');
    }
};
