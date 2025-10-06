<?php
// database/migrations/2025_10_06_201500_fix_historias_clinicas_column_lengths.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Configurar MySQL para permitir cambios
        DB::statement('SET SESSION sql_mode = ""');
        DB::statement('SET SESSION innodb_strict_mode = 0');
        
        Schema::table('historias_clinicas', function (Blueprint $table) {
            // ✅ CORREGIR COLUMNAS CON LONGITUD INSUFICIENTE
            
            // Cambiar de string(10) a TEXT para permitir valores largos
            $table->text('finalidad')->nullable()->change();
            
            // Cambiar teléfono de string(10) a string(20) por si acaso
            $table->string('acu_telefono', 20)->nullable()->change();
            
            // Asegurar que estas columnas sean TEXT (algunas podrían tener restricciones)
            $table->text('causa_externa')->nullable()->change();
            $table->text('clasificacion')->nullable()->change();
            $table->text('clasificacion_hta')->nullable()->change();
            $table->text('clasificacion_dm')->nullable()->change();
            $table->text('clasificacion_erc_estado')->nullable()->change();
            $table->text('clasificacion_erc_categoria_ambulatoria_persistente')->nullable()->change();
            $table->text('clasificacion_rcv')->nullable()->change();
            $table->text('clasificacion_estado_metabolico')->nullable()->change();
            
            // Cambiar campos que podrían tener valores largos de ENUM a TEXT
            $table->text('discapacidad_fisica')->nullable()->change();
            $table->text('discapacidad_visual')->nullable()->change();
            $table->text('discapacidad_mental')->nullable()->change();
            $table->text('discapacidad_auditiva')->nullable()->change();
            $table->text('discapacidad_intelectual')->nullable()->change();
            $table->text('drogo_dependiente')->nullable()->change();
            
            // Cambiar campos de examen físico que podrían tener valores largos
            $table->text('ef_cabeza')->nullable()->change();
            $table->text('agudeza_visual')->nullable()->change();
            $table->text('fundoscopia')->nullable()->change();
            $table->text('cuello')->nullable()->change();
            $table->text('torax')->nullable()->change();
            $table->text('mamas')->nullable()->change();
            $table->text('abdomen')->nullable()->change();
            $table->text('genito_urinario')->nullable()->change();
            $table->text('extremidades')->nullable()->change();
            $table->text('piel_anexos_pulsos')->nullable()->change();
            $table->text('sistema_nervioso')->nullable()->change();
            $table->text('capacidad_cognitiva')->nullable()->change();
            $table->text('orientacion')->nullable()->change();
            $table->text('reflejo_aquiliar')->nullable()->change();
            $table->text('reflejo_patelar')->nullable()->change();
            
            // Cambiar campos de factores de riesgo
            $table->text('tabaquismo')->nullable()->change();
            $table->text('lesion_organo_blanco')->nullable()->change();
            
            // Cambiar campos de examen físico adicional
            $table->text('oidos')->nullable()->change();
            $table->text('nariz_senos_paranasales')->nullable()->change();
            $table->text('cavidad_oral')->nullable()->change();
            $table->text('cardio_respiratorio')->nullable()->change();
            $table->text('musculo_esqueletico')->nullable()->change();
            $table->text('inspeccion_sensibilidad_pies')->nullable()->change();
            $table->text('capacidad_cognitiva_orientacion')->nullable()->change();
        });
        
        // Asegurar formato de fila dinámico
        DB::statement('ALTER TABLE historias_clinicas ROW_FORMAT=DYNAMIC');
    }

    public function down()
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            // ❌ REVERTIR CAMBIOS (CUIDADO: Puede truncar datos)
            $table->string('finalidad', 10)->nullable()->change();
            $table->string('acu_telefono', 10)->nullable()->change();
            
            // Nota: No revertimos los campos TEXT porque podrían contener datos largos
        });
    }
};
