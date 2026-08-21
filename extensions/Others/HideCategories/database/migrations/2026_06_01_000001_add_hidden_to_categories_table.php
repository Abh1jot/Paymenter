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
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'hidden')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('hidden')->default(false)->after('sort');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'hidden')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('hidden');
            });
        }
    }
};
