<?php
// database/migrations/2024_01_01_000002_create_sync_queue_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sede_id');
            $table->string('table_name');
            $table->string('record_uuid'); // UUID único global
            $table->unsignedBigInteger('record_id')->nullable(); // ID local
            $table->enum('operation', ['CREATE', 'UPDATE', 'DELETE']);
            $table->json('data')->nullable();
            $table->enum('status', ['PENDING', 'SYNCED', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at_offline')->nullable();
            $table->timestamps();
            
            $table->foreign('sede_id')->references('id')->on('sedes');
            $table->index(['sede_id', 'status']);
            $table->index(['record_uuid']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_queue');
    }
};
