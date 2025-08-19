<?php
// database/migrations/2024_01_01_000035_create_hcs_visitas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hcs_visitas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->string('fecha', 11);
            $table->string('identificacion', 30);
            $table->string('edad', 12);
            $table->string('hta', 12);
            $table->string('dm', 12);
            $table->string('telefono', 20);
            $table->string('zona', 50);
            
            // Medidas antropométricas
            $table->string('peso', 20)->nullable();
            $table->string('talla', 20)->nullable();
            $table->string('imc', 20)->nullable();
            $table->integer('perimetro_abdominal')->nullable();
            
            // Signos vitales
            $table->string('frecuencia_cardiaca', 20)->nullable();
            $table->string('frecuencia_respiratoria', 20)->nullable();
            $table->string('tension_arterial', 20)->nullable();
            $table->string('glucometria', 30)->nullable();
            $table->string('temperatura', 20)->nullable();
            
            // Información social
            $table->string('familiar', 50)->nullable();
            $table->string('abandono_social', 11)->nullable();
            
            // Evaluación
            $table->text('motivo')->nullable();
            $table->text('medicamentos')->nullable();
            $table->string('riesgos', 500)->nullable();
            $table->string('conductas', 1000)->nullable();
            $table->text('novedades')->nullable();
            $table->text('encargado')->nullable();
            $table->string('fecha_control', 11)->nullable();
            
            // Documentación
            $table->text('foto')->nullable();
            $table->text('firma')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->index(['sede_id', 'identificacion']);
            $table->index(['fecha']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hcs_visitas');
    }
};
