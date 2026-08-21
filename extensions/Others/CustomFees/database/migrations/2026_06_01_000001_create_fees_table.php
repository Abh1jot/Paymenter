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
        if (!Schema::hasTable('fees')) {
            Schema::create('fees', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('rate', 8, 4);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('feeables')) {
            Schema::create('feeables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_id')->constrained()->cascadeOnDelete();
                $table->morphs('feeable');
                $table->timestamps();

                $table->unique(['fee_id', 'feeable_id', 'feeable_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeables');
        Schema::dropIfExists('fees');
    }
};
