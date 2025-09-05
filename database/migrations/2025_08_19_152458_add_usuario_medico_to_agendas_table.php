<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_medico_id')->nullable()->after('brigada_id');
            $table->foreign('usuario_medico_id')->references('id')->on('usuarios')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('agendas', function (Blueprint $table) {
            $table->dropForeign(['usuario_medico_id']);
            $table->dropColumn('usuario_medico_id');
        });
    }
};
