<?php
// database/migrations/2024_01_01_000023_create_agendas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->string('modalidad', 50)->comment('Telemedicina, Ambulatoria');
            $table->date('fecha');
            $table->string('consultorio', 50);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('intervalo', 10);
            $table->string('etiqueta', 50);
            $table->enum('estado', ['ACTIVO', 'ANULADA', 'LLENA'])->default('ACTIVO');
            $table->unsignedBigInteger('proceso_id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('brigada_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('proceso_id')->references('id')->on('procesos');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('brigada_id')->references('id')->on('brigadas');
            
            $table->index(['sede_id', 'fecha']);
            $table->index(['sede_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('agendas');
    }
};
