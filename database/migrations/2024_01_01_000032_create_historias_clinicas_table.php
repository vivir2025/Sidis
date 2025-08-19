<?php
// database/migrations/2024_01_01_000032_create_historias_clinicas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Configurar MySQL para permitir filas más grandes
        DB::statement('SET SESSION sql_mode = ""');
        DB::statement('SET SESSION innodb_strict_mode = 0');
        
        Schema::create('historias_clinicas', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('cita_id');
            
            // Información básica - campos críticos como VARCHAR cortos, resto TEXT
            $table->string('finalidad', 10)->nullable();
            $table->text('acompanante')->nullable();
            $table->string('acu_telefono', 10)->nullable();
            $table->text('acu_parentesco')->nullable();
            $table->text('causa_externa')->nullable();
            $table->text('motivo_consulta')->nullable();
            $table->text('enfermedad_actual')->nullable();
            
            // Discapacidades - TEXT
            $table->text('discapacidad_fisica')->nullable();
            $table->text('discapacidad_visual')->nullable();
            $table->text('discapacidad_mental')->nullable();
            $table->text('discapacidad_auditiva')->nullable();
            $table->text('discapacidad_intelectual')->nullable();
            
            // Drogodependencia - TEXT
            $table->text('drogo_dependiente')->nullable();
            $table->text('drogo_dependiente_cual')->nullable();
            
            // Medidas antropométricas - usar decimales para números
            $table->decimal('peso', 5, 2)->nullable();
            $table->decimal('talla', 5, 2)->nullable();
            $table->decimal('imc', 5, 2)->nullable();
            $table->text('clasificacion')->nullable();
            $table->decimal('tasa_filtracion_glomerular_ckd_epi', 5, 2)->nullable();
            $table->decimal('tasa_filtracion_glomerular_gockcroft_gault', 5, 2)->nullable();
            
            // Antecedentes familiares - ENUM para SI/NO, TEXT para parentesco
            $table->enum('hipertension_arterial', ['SI', 'NO'])->nullable();
            $table->text('parentesco_hipertension')->nullable();
            $table->enum('diabetes_mellitus', ['SI', 'NO'])->nullable();
            $table->text('parentesco_mellitus')->nullable();
            $table->enum('artritis', ['SI', 'NO'])->nullable();
            $table->text('parentesco_artritis')->nullable();
            $table->enum('enfermedad_cardiovascular', ['SI', 'NO'])->nullable();
            $table->text('parentesco_cardiovascular')->nullable();
            $table->enum('antecedente_metabolico', ['SI', 'NO'])->nullable();
            $table->text('parentesco_metabolico')->nullable();
            $table->enum('cancer_mama_estomago_prostata_colon', ['SI', 'NO'])->nullable();
            $table->text('parentesco_cancer')->nullable();
            $table->enum('leucemia', ['SI', 'NO'])->nullable();
            $table->text('parentesco_leucemia')->nullable();
            $table->enum('vih', ['SI', 'NO'])->nullable();
            $table->text('parentesco_vih')->nullable();
            $table->enum('otro', ['SI', 'NO'])->nullable();
            $table->text('parentesco_otro')->nullable();
            
            // Antecedentes personales - ENUM para SI/NO, TEXT para observaciones
            $table->enum('hipertension_arterial_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_hipertension_arterial')->nullable();
            $table->enum('diabetes_mellitus_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_mellitus')->nullable();
            $table->enum('enfermedad_cardiovascular_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_enfermedad_cardiovascular')->nullable();
            $table->enum('arterial_periferica_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_arterial_periferica')->nullable();
            $table->enum('carotidea_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_carotidea')->nullable();
            $table->enum('aneurisma_aorta_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_aneurisma_aorta')->nullable();
            $table->enum('sindrome_coronario_agudo_angina_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_sindrome_coronario')->nullable();
            $table->enum('artritis_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_artritis')->nullable();
            $table->enum('iam_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_iam')->nullable();
            $table->enum('revascul_coronaria_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_revascul_coronaria')->nullable();
            $table->enum('insuficiencia_cardiaca_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_insuficiencia_cardiaca')->nullable();
            $table->enum('amputacion_pie_diabetico_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_amputacion_pie_diabetico')->nullable();
            $table->enum('enfermedad_pulmonar_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_enfermedad_pulmonar')->nullable();
            $table->enum('victima_maltrato_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_maltrato_personal')->nullable();
            $table->enum('antecedentes_quirurgicos', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_antecedentes_quirurgicos')->nullable();
            $table->enum('acontosis_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_acontosis')->nullable();
            $table->enum('otro_personal', ['SI', 'NO'])->nullable();
            $table->text('obs_personal_otro')->nullable();
            $table->text('insulina_requiriente')->nullable();
            
            // Adherencia al tratamiento
            $table->enum('olvida_tomar_medicamentos', ['SI', 'NO'])->nullable();
            $table->enum('toma_medicamentos_hora_indicada', ['SI', 'NO'])->nullable();
            $table->enum('cuando_esta_bien_deja_tomar_medicamentos', ['SI', 'NO'])->nullable();
            $table->enum('siente_mal_deja_tomarlos', ['SI', 'NO'])->nullable();
            $table->enum('valoracion_psicologia', ['SI', 'NO'])->nullable();
            
            // Revisión por sistemas - TEXT
            $table->text('cabeza')->nullable();
            $table->text('orl')->nullable();
            $table->text('cardiovascular')->nullable();
            $table->text('gastrointestinal')->nullable();
            $table->text('osteoatromuscular')->nullable();
            $table->text('snc')->nullable();
            $table->text('revision_sistemas')->nullable();
            
            // Signos vitales - usar decimales para números
            $table->decimal('presion_arterial_sistolica_sentado_pie', 5, 2)->nullable();
            $table->decimal('presion_arterial_distolica_sentado_pie', 5, 2)->nullable();
            $table->decimal('presion_arterial_sistolica_acostado', 5, 2)->nullable();
            $table->decimal('presion_arterial_distolica_acostado', 5, 2)->nullable();
            $table->decimal('frecuencia_cardiaca', 5, 2)->nullable();
            $table->decimal('frecuencia_respiratoria', 5, 2)->nullable();
            
            // Examen físico - TEXT
            $table->text('ef_cabeza')->nullable();
            $table->text('obs_cabeza')->nullable();
            $table->text('agudeza_visual')->nullable();
            $table->text('obs_agudeza_visual')->nullable();
            $table->text('fundoscopia')->nullable();
            $table->text('obs_fundoscopia')->nullable();
            $table->text('cuello')->nullable();
            $table->text('obs_cuello')->nullable();
            $table->text('torax')->nullable();
            $table->text('obs_torax')->nullable();
            $table->text('mamas')->nullable();
            $table->text('obs_mamas')->nullable();
            $table->text('abdomen')->nullable();
            $table->text('obs_abdomen')->nullable();
            $table->text('genito_urinario')->nullable();
            $table->text('obs_genito_urinario')->nullable();
            $table->text('extremidades')->nullable();
            $table->text('obs_extremidades')->nullable();
            $table->text('piel_anexos_pulsos')->nullable();
            $table->text('obs_piel_anexos_pulsos')->nullable();
            $table->text('sistema_nervioso')->nullable();
            $table->text('obs_sistema_nervioso')->nullable();
            $table->text('capacidad_cognitiva')->nullable();
            $table->text('obs_capacidad_cognitiva')->nullable();
            $table->text('orientacion')->nullable();
            $table->text('obs_orientacion')->nullable();
            $table->text('reflejo_aquiliar')->nullable();
            $table->text('obs_reflejo_aquiliar')->nullable();
            $table->text('reflejo_patelar')->nullable();
            $table->text('obs_reflejo_patelar')->nullable();
            $table->text('hallazgo_positivo_examen_fisico')->nullable();
            
            // Factores de riesgo - TEXT
            $table->text('tabaquismo')->nullable();
            $table->text('obs_tabaquismo')->nullable();
            $table->enum('dislipidemia', ['SI', 'NO'])->nullable();
            $table->text('obs_dislipidemia')->nullable();
            $table->enum('menor_cierta_edad', ['SI', 'NO'])->nullable();
            $table->text('obs_menor_cierta_edad')->nullable();
            $table->text('perimetro_abdominal')->nullable();
            $table->text('obs_perimetro_abdominal')->nullable();
            $table->enum('condicion_clinica_asociada', ['SI', 'NO'])->nullable();
            $table->text('obs_condicion_clinica_asociada')->nullable();
            $table->text('lesion_organo_blanco')->nullable();
            $table->text('descripcion_lesion_organo_blanco')->nullable();
            $table->text('obs_lesion_organo_blanco')->nullable();
            
            // Clasificaciones - TEXT
            $table->text('clasificacion_hta')->nullable();
            $table->text('clasificacion_dm')->nullable();
            $table->text('clasificacion_erc_estado')->nullable();
            $table->text('clasificacion_erc_categoria_ambulatoria_persistente')->nullable();
            $table->text('clasificacion_rcv')->nullable();
            
            // Educación - ENUM para SI/NO
            $table->enum('alimentacion', ['SI', 'NO'])->nullable();
            $table->enum('disminucion_consumo_sal_azucar', ['SI', 'NO'])->nullable();
            $table->enum('fomento_actividad_fisica', ['SI', 'NO'])->nullable();
            $table->enum('importancia_adherencia_tratamiento', ['SI', 'NO'])->nullable();
            $table->enum('consumo_frutas_verduras', ['SI', 'NO'])->nullable();
            $table->enum('manejo_estres', ['SI', 'NO'])->nullable();
            $table->enum('disminucion_consumo_cigarrillo', ['SI', 'NO'])->nullable();
            $table->enum('disminucion_peso', ['SI', 'NO'])->nullable();
            
            $table->text('observaciones_generales')->nullable();
            
            // Examen físico adicional - TEXT
            $table->text('oidos')->nullable();
            $table->text('nariz_senos_paranasales')->nullable();
            $table->text('cavidad_oral')->nullable();
            $table->text('cardio_respiratorio')->nullable();
            $table->text('musculo_esqueletico')->nullable();
            $table->text('inspeccion_sensibilidad_pies')->nullable();
            $table->text('capacidad_cognitiva_orientacion')->nullable();
            
            // Medicina tradicional - ENUM para SI/NO
            $table->enum('recibe_tratamiento_alternativo', ['SI', 'NO'])->nullable();
            $table->enum('recibe_tratamiento_con_plantas_medicinales', ['SI', 'NO'])->nullable();
            $table->enum('recibe_ritual_medicina_tradicional', ['SI', 'NO'])->nullable();
            
            // Alimentación
            $table->tinyInteger('numero_frutas_diarias')->nullable();
            $table->enum('elevado_consumo_grasa_saturada', ['SI', 'NO'])->nullable();
            $table->enum('adiciona_sal_despues_preparar_comida', ['SI', 'NO'])->nullable();
            
            $table->text('general')->nullable();
            $table->text('respiratorio')->nullable();
            $table->text('adherente')->nullable();
            
            // Exámenes complementarios - TEXT
            $table->text('ecografia_renal')->nullable();
            $table->text('razon_reformulacion')->nullable();
            $table->text('motivo_reformulacion')->nullable();
            $table->text('reformulacion_quien_reclama')->nullable();
            $table->text('reformulacion_nombre_reclama')->nullable();
            $table->text('electrocardiograma')->nullable();
            $table->text('ecocardiograma')->nullable();
            $table->text('adicional')->nullable();
            
            $table->text('clasificacion_estado_metabolico')->nullable();
            $table->text('fex_es')->nullable();
            $table->text('fex_es1')->nullable();
            $table->text('fex_es2')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('cita_id')->references('id')->on('citas');
            
            $table->index(['sede_id', 'cita_id']);
        });
        
        // Cambiar a formato de fila dinámico después de crear la tabla
        DB::statement('ALTER TABLE historias_clinicas ROW_FORMAT=DYNAMIC');
    }

    public function down()
    {
        Schema::dropIfExists('historias_clinicas');
    }
};
