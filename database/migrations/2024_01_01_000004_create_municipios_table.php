<?php
// database/migrations/2024_01_01_000004_create_municipios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre', 50);
            $table->unsignedBigInteger('departamento_id');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('departamento_id')->references('id')->on('departamentos');
            $table->index(['departamento_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('municipios');
    }
};
