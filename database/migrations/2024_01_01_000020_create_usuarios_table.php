<?php
// database/migrations/2024_01_01_000020_create_usuarios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sede_id');
            $table->string('documento', 15);
            $table->string('nombre', 50);
            $table->string('apellido', 50);
            $table->string('telefono', 10);
            $table->string('correo', 60);
            $table->string('registro_profesional', 50)->nullable();
            $table->longText('firma')->nullable();
            $table->string('login', 50)->unique();
            $table->string('password');
            $table->unsignedBigInteger('estado_id');
            $table->unsignedBigInteger('rol_id');
            $table->unsignedBigInteger('especialidad_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->foreign('estado_id')->references('id')->on('estados');
            $table->foreign('rol_id')->references('id')->on('roles');
            $table->foreign('especialidad_id')->references('id')->on('especialidades');
            
            $table->index(['sede_id', 'login']);
            $table->index(['sede_id', 'estado_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
};
