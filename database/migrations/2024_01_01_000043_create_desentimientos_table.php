<?php
// database/migrations/2024_01_01_000043_create_desentimientos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('desentimientos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->datetime('fecha');
            $table->string('nombre', 60);
            $table->string('tipo_cc', 2);
            $table->string('identificacion', 13);
            $table->string('fecha_nacimiento', 20);
            $table->string('edad', 3);
            $table->string('direccion', 20);
            $table->string('telefono', 12);
            $table->string('yo', 30);
            $table->string('identificacion_po', 12);
            $table->string('manifiesto', 13);
            $table->string('realiza', 15);
            $table->string('cargo', 10);
            $table->string('visita', 13);
            $table->string('realiza_por', 60);
            $table->string('con_cargo', 25);
            $table->string('firma_usuario', 150);
            $table->string('nombre_usuario', 40);
            $table->string('identificacion_usuario', 13);
            $table->string('firma_testigo', 150);
            $table->string('nombre_testigo', 70);
            $table->string('identificacion_testigo', 13);
            $table->string('firma_fnpv', 50);
            $table->string('nombre_fnpv', 150);
            $table->string('identificacion_fnpv', 60);
            $table->string('cargo_fnpv', 50);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->index(['sede_id', 'identificacion']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('desentimientos');
    }
};
