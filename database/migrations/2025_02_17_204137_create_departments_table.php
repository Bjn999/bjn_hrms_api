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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string("name", 225);
            $table->string("phones", 100);
            $table->string("notes", 300)->nullable();
            $table->integer("added_by");
            $table->integer("updated_by")->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->tinyInteger("active")->default(1);

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
        Schema::dropIfExists('departments');
    }
};
