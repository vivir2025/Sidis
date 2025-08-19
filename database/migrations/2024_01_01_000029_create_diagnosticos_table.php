<?php
// database/migrations/2024_01_01_000029_create_diagnosticos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('codigo', 20);
            $table->string('nombre', 300);
            $table->string('cod_categoria', 200);
            $table->string('categoria', 200);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['codigo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('diagnosticos');
    }
};
