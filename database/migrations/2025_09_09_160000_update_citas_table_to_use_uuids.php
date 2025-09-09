<?php
// database/migrations/2025_09_09_160000_update_citas_table_to_use_uuids.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('citas', function (Blueprint $table) {
            // ✅ AGREGAR COLUMNAS UUID
            $table->string('paciente_uuid', 36)->nullable()->after('patologia');
            $table->string('agenda_uuid', 36)->nullable()->after('paciente_uuid');
            $table->string('cups_contratado_uuid', 36)->nullable()->after('agenda_uuid');
            
            // ✅ ÍNDICES PARA BÚSQUEDAS RÁPIDAS
            $table->index('paciente_uuid');
            $table->index('agenda_uuid');
            $table->index('cups_contratado_uuid');
        });

        // ✅ MIGRAR DATOS EXISTENTES (si los hay)
        $this->migrateExistingData();

        Schema::table('citas', function (Blueprint $table) {
            // ✅ HACER NULLABLE LAS COLUMNAS ANTIGUAS PRIMERO
            $table->unsignedBigInteger('paciente_id')->nullable()->change();
            $table->unsignedBigInteger('agenda_id')->nullable()->change();
            $table->unsignedBigInteger('cups_contratado_id')->nullable()->change();
        });
    }

    private function migrateExistingData()
    {
        // Migrar pacientes
        DB::statement("
            UPDATE citas 
            SET paciente_uuid = (
                SELECT uuid FROM pacientes WHERE pacientes.id = citas.paciente_id
            )
            WHERE paciente_id IS NOT NULL
        ");

        // Migrar agendas
        DB::statement("
            UPDATE citas 
            SET agenda_uuid = (
                SELECT uuid FROM agendas WHERE agendas.id = citas.agenda_id
            )
            WHERE agenda_id IS NOT NULL
        ");

        // Migrar cups_contratados
        DB::statement("
            UPDATE citas 
            SET cups_contratado_uuid = (
                SELECT uuid FROM cups_contratados WHERE cups_contratados.id = citas.cups_contratado_id
            )
            WHERE cups_contratado_id IS NOT NULL
        ");
    }

    public function down()
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn(['paciente_uuid', 'agenda_uuid', 'cups_contratado_uuid']);
            $table->unsignedBigInteger('paciente_id')->nullable(false)->change();
            $table->unsignedBigInteger('agenda_id')->nullable(false)->change();
            $table->unsignedBigInteger('cups_contratado_id')->nullable(false)->change();
        });
    }
};
