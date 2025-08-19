<?php
// database/migrations/2024_01_01_000041_create_facturas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('cita_id')->nullable();
            $table->unsignedBigInteger('paciente_id')->nullable();
            $table->unsignedBigInteger('contrato_id');
            $table->date('fecha');
            $table->string('copago', 50);
            $table->string('comision', 50);
            $table->string('descuento', 50);
            $table->string('valor_consulta', 50);
            $table->string('sub_total', 50);
            $table->string('autorizacion', 50);
            $table->string('cantidad', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('cita_id')->references('id')->on('citas');
            $table->foreign('paciente_id')->references('id')->on('pacientes');
            $table->foreign('contrato_id')->references('id')->on('contratos');
            
            $table->index(['sede_id', 'fecha']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('facturas');
    }
};
