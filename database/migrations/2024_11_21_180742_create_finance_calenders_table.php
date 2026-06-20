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
        Schema::create('finance_calenders', function (Blueprint $table) {
            $table->id();
            $table->integer('finance_yr');
            $table->string('finance_yr_desc', 225);
            $table-> date('start_date');
            $table-> date('end_date');
            $table-> tinyInteger('is_open')->default(0);
            $table-> integer('com_code');
            $table-> integer('added_by');
            $table-> integer('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_calenders');
    }
};
