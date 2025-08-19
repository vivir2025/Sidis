<?php
// database/migrations/2024_01_01_000001_create_sedes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre', 100);
            $table->enum('codigo', ['CAJIBIO', 'MORALES', 'PIENDAMO']);
            $table->string('direccion')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('ciudad', 50)->default('Cauca');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sedes');
    }
};
