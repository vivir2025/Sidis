<?php
// database/migrations/2024_01_01_000034_create_hcs_paraclinicos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hcs_paraclinicos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->string('fecha', 30);
            $table->string('identificacion', 30);
            
            // Perfil lipídico
            $table->string('colesterol_total', 50)->nullable();
            $table->string('colesterol_hdl', 50)->nullable();
            $table->string('trigliceridos', 50)->nullable();
            $table->string('colesterol_ldl', 50)->nullable();
            
            // Hematología
            $table->string('hemoglobina', 50)->nullable();
            $table->string('hematocrito', 50)->nullable();
            $table->string('plaquetas', 50)->nullable();
            
            // Glucemia
            $table->string('hemoglobina_glicosilada', 50)->nullable();
            $table->string('glicemia_basal', 50)->nullable();
            $table->string('glicemia_post', 50)->nullable();
            
            // Función renal
            $table->string('creatinina', 50)->nullable();
            $table->string('creatinuria', 50)->nullable();
            $table->string('microalbuminuria', 50)->nullable();
            $table->string('albumina', 50)->nullable();
            $table->string('relacion_albuminuria_creatinuria', 50)->nullable();
            $table->string('parcial_orina', 50)->nullable();
            $table->string('depuracion_creatinina', 50)->nullable();
            $table->string('creatinina_orina_24', 50)->nullable();
            $table->string('proteina_orina_24', 50)->nullable();
            
            // Hormonas
            $table->string('hormona_estimulante_tiroides', 50)->nullable();
            $table->string('hormona_paratiroidea', 50)->nullable();
            
            // Química sanguínea
            $table->string('albumina_suero', 25)->nullable();
            $table->string('fosforo_suero', 25)->nullable();
            $table->string('nitrogeno_ureico', 25)->nullable();
            $table->string('acido_urico_suero', 25)->nullable();
            $table->string('calcio', 25)->nullable();
            $table->string('sodio_suero', 25)->nullable();
            $table->string('potasio_suero', 25)->nullable();
            
            // Hierro
            $table->string('hierro_total', 25)->nullable();
            $table->string('ferritina', 25)->nullable();
            $table->string('transferrina', 25)->nullable();
            
            // Enzimas
            $table->string('fosfatasa_alcalina', 20)->nullable();
            
            // Vitaminas
            $table->string('acido_folico_suero', 25)->nullable();
            $table->string('vitamina_b12', 25)->nullable();
            
            $table->string('nitrogeno_ureico_orina_24', 25)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->index(['sede_id', 'identificacion']);
            $table->index(['fecha']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hcs_paraclinicos');
    }
};
