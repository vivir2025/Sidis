<?php
// database/migrations/2024_01_01_000021_create_pacientes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('regimen_id');
            $table->unsignedBigInteger('tipo_afiliacion_id');
            $table->unsignedBigInteger('zona_residencia_id');
            $table->unsignedBigInteger('depto_nacimiento_id');
            $table->unsignedBigInteger('depto_residencia_id');
            $table->unsignedBigInteger('municipio_nacimiento_id');
            $table->unsignedBigInteger('municipio_residencia_id');
            $table->unsignedBigInteger('raza_id');
            $table->unsignedBigInteger('escolaridad_id');
            $table->unsignedBigInteger('parentesco_id');
            $table->unsignedBigInteger('tipo_documento_id');
            $table->string('registro', 100);
            $table->string('primer_nombre', 50);
            $table->string('segundo_nombre', 50)->nullable();
            $table->string('primer_apellido', 50);
            $table->string('segundo_apellido', 50)->nullable();
            $table->string('documento', 20);
            $table->date('fecha_nacimiento');
            $table->enum('sexo', ['M', 'F']);
            $table->string('direccion', 50);
            $table->string('telefono', 50);
            $table->string('correo', 50)->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado_civil', 50);
            $table->unsignedBigInteger('ocupacion_id');
            $table->string('nombre_acudiente', 50)->nullable();
            $table->string('parentesco_acudiente', 50)->nullable();
            $table->string('telefono_acudiente', 10)->nullable();
            $table->string('direccion_acudiente', 50)->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->string('acompanante_nombre', 100)->nullable();
            $table->string('acompanante_telefono', 30)->nullable();
            $table->date('fecha_registro')->default(now());
            $table->unsignedBigInteger('novedad_id')->nullable();
            $table->unsignedBigInteger('auxiliar_id')->nullable();
            $table->unsignedBigInteger('brigada_id')->nullable();
            $table->date('fecha_actualizacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('regimen_id')->references('id')->on('regimenes');
            $table->foreign('tipo_afiliacion_id')->references('id')->on('tipos_afiliacion');
            $table->foreign('zona_residencia_id')->references('id')->on('zonas_residenciales');
            $table->foreign('depto_nacimiento_id')->references('id')->on('departamentos');
            $table->foreign('depto_residencia_id')->references('id')->on('departamentos');
            $table->foreign('municipio_nacimiento_id')->references('id')->on('municipios');
            $table->foreign('municipio_residencia_id')->references('id')->on('municipios');
            $table->foreign('raza_id')->references('id')->on('razas');
            $table->foreign('escolaridad_id')->references('id')->on('escolaridades');
            $table->foreign('parentesco_id')->references('id')->on('tipos_parentesco');
            $table->foreign('tipo_documento_id')->references('id')->on('tipos_documento');
            $table->foreign('ocupacion_id')->references('id')->on('ocupaciones');
            $table->foreign('novedad_id')->references('id')->on('novedades');
            $table->foreign('auxiliar_id')->references('id')->on('auxiliares');
            $table->foreign('brigada_id')->references('id')->on('brigadas');
            
            // Indexes
            $table->index(['sede_id', 'documento']);
            $table->index(['sede_id', 'estado']);
            $table->unique(['sede_id', 'documento']); // Documento único por sede
        });
    }

    public function down()
    {
        Schema::dropIfExists('pacientes');
    }
};
