<?php
// database/migrations/2024_01_01_000042_create_factura_cups_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('factura_cups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('factura_id');
            $table->unsignedBigInteger('cups_id');
            $table->string('tarifa', 30);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('factura_id')->references('id')->on('facturas');
            $table->foreign('cups_id')->references('id')->on('cups');
            $table->index(['factura_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('factura_cups');
    }
};
