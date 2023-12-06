<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('giro_details', function (Blueprint $table) {
            $table->id();
            $table->string('giro_id');
            $table->string('remarks')->nullable();
            $table->double('amount')->nullable();
            $table->string('tx_flag')->nullable();
            $table->boolean('is_cashin')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giro_details');
    }
};
