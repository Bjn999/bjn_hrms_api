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
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->tinyInteger('active')->default(1);
            $table->foreignId('governorate_id')->references('id')->on('governorates')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('country_id')->references('id')->on('countries')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('added_by')->references('id')->on('users')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->references('id')->on('users')->onUpdate('cascade');

            $table->timestamps();
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'name'], 'comp_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
