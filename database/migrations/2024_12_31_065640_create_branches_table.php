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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('address', 255);
            $table->string('phones', 255);
            $table->string('email', 50)->nullable();
            $table->tinyInteger('active')->default(1);
            $table->Integer('added_by');
            $table->Integer('updated_by')->nullable();
            $table->Integer('com_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
