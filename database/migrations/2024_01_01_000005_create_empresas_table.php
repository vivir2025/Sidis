<?php
// database/migrations/2024_01_01_000005_create_empresas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre', 50);
            $table->string('nit', 50);
            $table->string('codigo_eapb', 50)->comment('CODIGO ENTIDAD ADMINISTRADORA');
            $table->string('codigo', 50)->comment('CODIGO HABILITACION');
            $table->string('direccion', 50);
            $table->string('telefono', 10);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresas');
    }
};
