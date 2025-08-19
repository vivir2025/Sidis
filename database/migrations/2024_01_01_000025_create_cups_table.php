<?php
// database/migrations/2024_01_01_000025_create_cups_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('origen', 10);
            $table->string('nombre', 200);
            $table->string('codigo', 10);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['codigo', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cups');
    }
};
