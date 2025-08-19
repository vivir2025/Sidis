<?php
// database/migrations/2024_01_01_000028_create_citas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->date('fecha');
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_final');
            $table->date('fecha_deseada');
            $table->string('motivo', 200)->nullable();
            $table->string('nota', 200);
            $table->string('estado', 50);
            $table->string('patologia', 50);
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('agenda_id');
            $table->unsignedBigInteger('cups_contratado_id');
            $table->unsignedBigInteger('usuario_creo_cita_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('paciente_id')->references('id')->on('pacientes');
            $table->foreign('agenda_id')->references('id')->on('agendas');
            $table->foreign('cups_contratado_id')->references('id')->on('cups_contratados');
            $table->foreign('usuario_creo_cita_id')->references('id')->on('usuarios');
            
            $table->index(['sede_id', 'fecha']);
            $table->index(['sede_id', 'estado']);
            $table->index(['paciente_id', 'fecha']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('citas');
    }
};