<?php
// database/migrations/2024_01_01_000033_create_historias_clinicas_complementarias_table.php
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
        
        Schema::create('historias_clinicas_complementarias', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('historia_clinica_id')->nullable();
            
            // Antecedentes patológicos - TEXT
            $table->text('sistema_nervioso_nefro_inter')->nullable();
            $table->text('sistema_hemolinfatico')->nullable();
            $table->text('aparato_digestivo')->nullable();
            $table->text('organo_sentido')->nullable();
            $table->text('endocrino_metabolico')->nullable();
            $table->text('inmunologico')->nullable();
            $table->text('cancer_tumores_radioterapia_quimio')->nullable();
            $table->text('glandula_mamaria')->nullable();
            $table->text('hipertension_diabetes_erc')->nullable();
            $table->text('reacciones_alergica')->nullable();
            $table->text('cardio_vasculares')->nullable();
            $table->text('respiratorios')->nullable();
            $table->text('urinarias')->nullable();
            $table->text('osteoarticulares')->nullable();
            $table->text('infecciosos')->nullable();
            $table->text('cirugia_trauma')->nullable();
            $table->text('tratamiento_medicacion')->nullable();
            $table->text('antecedente_quirurgico')->nullable();
            $table->text('antecedentes_familiares')->nullable();
            $table->text('consumo_tabaco')->nullable();
            $table->text('antecedentes_alcohol')->nullable();
            $table->text('sedentarismo')->nullable();
            $table->text('ginecologico')->nullable();
            $table->text('citologia_vaginal')->nullable();
            $table->text('menarquia')->nullable();
            $table->text('gestaciones')->nullable();
            $table->text('parto')->nullable();
            $table->text('aborto')->nullable();
            $table->text('cesaria')->nullable();
            $table->text('metodo_conceptivo')->nullable();
            $table->text('metodo_conceptivo_cual')->nullable();
            $table->text('antecedente_personal')->nullable();
            
            // Descripciones detalladas - TEXT
            $table->text('descripcion_sistema_nervioso')->nullable();
            $table->text('descripcion_sistema_hemolinfatico')->nullable();
            $table->text('descripcion_aparato_digestivo')->nullable();
            $table->text('descripcion_organos_sentidos')->nullable();
            $table->text('descripcion_endocrino_metabolico')->nullable();
            $table->text('descripcion_inmunologico')->nullable();
            $table->text('descripcion_cancer_tumores_radio_quimioterapia')->nullable();
            $table->text('descripcion_glandulas_mamarias')->nullable();
            $table->text('descripcion_hipertension_diabetes_erc')->nullable();
            $table->text('descripcion_reacion_alergica')->nullable();
            $table->text('descripcion_cardio_vasculares')->nullable();
            $table->text('descripcion_respiratorios')->nullable();
            $table->text('descripcion_urinarias')->nullable();
            $table->text('descripcion_osteoarticulares')->nullable();
            $table->text('descripcion_infecciosos')->nullable();
            $table->text('descripcion_cirugias_traumas')->nullable();
            $table->text('descripcion_tratamiento_medicacion')->nullable();
            $table->text('descripcion_antecedentes_quirurgicos')->nullable();
            $table->text('descripcion_antecedentes_familiares')->nullable();
            $table->text('descripcion_consumo_tabaco')->nullable();
            $table->text('descripcion_antecedentes_alcohol')->nullable();
            $table->text('descripcion_sedentarismo')->nullable();
            $table->text('descripcion_ginecologicos')->nullable();
            $table->text('descripcion_citologia_vaginal')->nullable();
            
            // Neurológico y estado mental - TEXT
            $table->text('neurologico_estado_mental')->nullable();
            $table->text('obs_neurologico_estado_mental')->nullable();
            
            // Estructura familiar - TEXT
            $table->text('estructura_familiar')->nullable();
            $table->text('cantidad_habitantes')->nullable();
            $table->text('cantidad_conforman_familia')->nullable();
            $table->text('composicion_familiar')->nullable();
            
            // Vivienda - TEXT
            $table->text('tipo_vivienda')->nullable();
            $table->text('tenencia_vivienda')->nullable();
            $table->text('material_paredes')->nullable();
            $table->text('material_pisos')->nullable();
            $table->text('espacios_sala')->nullable();
            $table->text('comedor')->nullable();
            $table->text('banio')->nullable();
            $table->text('cocina')->nullable();
            $table->text('patio')->nullable();
            $table->text('cantidad_dormitorios')->nullable();
            $table->text('cantidad_personas_ocupan_cuarto')->nullable();
            
            // Servicios públicos - TEXT
            $table->text('energia_electrica')->nullable();
            $table->text('alcantarillado')->nullable();
            $table->text('gas_natural')->nullable();
            $table->text('centro_atencion')->nullable();
            $table->text('acueducto')->nullable();
            $table->text('centro_culturales')->nullable();
            $table->text('ventilacion')->nullable();
            $table->text('organizacion')->nullable();
            $table->text('centro_educacion')->nullable();
            $table->text('centro_recreacion_esparcimiento')->nullable();
            
            // Evaluación psicosocial - TEXT
            $table->text('dinamica_familiar')->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('acciones_seguir')->nullable();
            $table->text('motivo_consulta')->nullable();
            $table->text('psicologia_descripcion_problema')->nullable();
            $table->text('psicologia_red_apoyo')->nullable();
            $table->text('psicologia_plan_intervencion_recomendacion')->nullable();
            $table->text('psicologia_tratamiento_actual_adherencia')->nullable();
            $table->text('analisis_conclusiones')->nullable();
            $table->text('psicologia_comportamiento_consulta')->nullable();
            
            // Seguimiento - TEXT
            $table->text('objetivo_visita')->nullable();
            $table->text('situacion_encontrada')->nullable();
            $table->text('compromiso')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->text('siguiente_seguimiento')->nullable();
            $table->text('enfermedad_diagnostica')->nullable();
            
            // Antecedentes adicionales - TEXT
            $table->text('habito_intestinal')->nullable();
            $table->text('quirurgicos')->nullable();
            $table->text('quirurgicos_observaciones')->nullable();
            $table->text('alergicos')->nullable();
            $table->text('alergicos_observaciones')->nullable();
            $table->text('familiares')->nullable();
            $table->text('familiares_observaciones')->nullable();
            $table->text('psa')->nullable();
            $table->text('psa_observaciones')->nullable();
            $table->text('farmacologicos')->nullable();
            $table->text('farmacologicos_observaciones')->nullable();
            $table->text('sueno')->nullable();
            $table->text('sueno_observaciones')->nullable();
            $table->text('tabaquismo_observaciones')->nullable();
            $table->text('ejercicio')->nullable();
            $table->text('ejercicio_observaciones')->nullable();
            
            // Gineco-obstétricos - TEXT
            $table->text('embarazo_actual')->nullable();
            $table->text('semanas_gestacion')->nullable();
            $table->text('climatero')->nullable();
            
            // Evaluación nutricional - TEXT
            $table->text('tolerancia_via_oral')->nullable();
            $table->text('percepcion_apetito')->nullable();
            $table->text('percepcion_apetito_observacion')->nullable();
            $table->text('alimentos_preferidos')->nullable();
            $table->text('alimentos_rechazados')->nullable();
            $table->text('suplemento_nutricionales')->nullable();
            $table->text('dieta_especial')->nullable();
            $table->text('dieta_especial_cual')->nullable();
            
            // Horarios de comida - TEXT
            $table->text('desayuno_hora')->nullable();
            $table->text('desayuno_hora_observacion')->nullable();
            $table->text('media_manana_hora')->nullable();
            $table->text('media_manana_hora_observacion')->nullable();
            $table->text('almuerzo_hora')->nullable();
            $table->text('almuerzo_hora_observacion')->nullable();
            $table->text('media_tarde_hora')->nullable();
            $table->text('media_tarde_hora_observacion')->nullable();
            $table->text('cena_hora')->nullable();
            $table->text('cena_hora_observacion')->nullable();
            $table->text('refrigerio_nocturno_hora')->nullable();
            $table->text('refrigerio_nocturno_hora_observacion')->nullable();
            
            // Evaluación nutricional - TEXT
            $table->text('peso_ideal')->nullable();
            $table->text('interpretacion')->nullable();
            $table->text('meta_meses')->nullable();
            $table->text('analisis_nutricional')->nullable();
            $table->text('plan_seguir')->nullable();
            $table->text('avance_paciente')->nullable();
            
            // Frecuencia de consumo - TEXT
            $table->text('comida_desayuno')->nullable();
            $table->text('comida_almuerzo')->nullable();
            $table->text('comida_medio_almuerzo')->nullable();
            $table->text('comida_cena')->nullable();
            $table->text('comida_medio_desayuno')->nullable();
            
            // Grupos de alimentos - TEXT
            $table->text('lacteo')->nullable();
            $table->text('lacteo_observacion')->nullable();
            $table->text('huevo')->nullable();
            $table->text('huevo_observacion')->nullable();
            $table->text('embutido')->nullable();
            $table->text('embutido_observacion')->nullable();
            $table->text('carne_roja')->nullable();
            $table->text('carne_blanca')->nullable();
            $table->text('carne_vicera')->nullable();
            $table->text('carne_observacion')->nullable();
            $table->text('leguminosas')->nullable();
            $table->text('leguminosas_observacion')->nullable();
            $table->text('frutas_jugo')->nullable();
            $table->text('frutas_porcion')->nullable();
            $table->text('frutas_observacion')->nullable();
            $table->text('verduras_hortalizas')->nullable();
            $table->text('vh_observacion')->nullable();
            $table->text('cereales')->nullable();
            $table->text('cereales_observacion')->nullable();
            $table->text('rtp')->nullable();
            $table->text('rtp_observacion')->nullable();
            $table->text('azucar_dulce')->nullable();
            $table->text('ad_observacion')->nullable();
            
            // Diagnóstico nutricional - TEXT
            $table->text('diagnostico_nutri')->nullable();
            $table->text('plan_seguir_nutri')->nullable();
            
            // Evaluación física y terapéutica - TEXT
            $table->text('actitud')->nullable();
            $table->text('evaluacion_d')->nullable();
            $table->text('evaluacion_p')->nullable();
            $table->text('estado')->nullable();
            $table->text('evaluacion_dolor')->nullable();
            $table->text('evaluacion_os')->nullable();
            $table->text('evaluacion_neu')->nullable();
            $table->text('comitante')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('historia_clinica_id')->references('id')->on('historias_clinicas');
            $table->index(['historia_clinica_id']);
        });
        
        // Cambiar a formato de fila dinámico después de crear la tabla
        DB::statement('ALTER TABLE historias_clinicas_complementarias ROW_FORMAT=DYNAMIC');
    }

    public function down()
    {
        Schema::dropIfExists('historias_clinicas_complementarias');
    }
};
