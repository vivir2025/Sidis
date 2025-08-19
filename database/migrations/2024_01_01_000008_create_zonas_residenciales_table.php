<?php
// database/migrations/2024_01_01_000008_create_zonas_residenciales_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zonas_residenciales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->char('abreviacion', 1);
            $table->string('nombre', 50);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('zonas_residenciales');
    }
};
