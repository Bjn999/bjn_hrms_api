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
        Schema::create('shifts_types', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger("type")->comment("نوع الشفت واحد صباحي - اثنين ومسائي");
            $table->time("from_time");
            $table->time("to_time");
            $table->decimal("total_hour", 10, 2);
            $table->integer("com_code");
            $table->tinyInteger("active")->default(1);
            $table->integer("added_by");
            $table->integer("updated_by")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts_types');
    }
};
