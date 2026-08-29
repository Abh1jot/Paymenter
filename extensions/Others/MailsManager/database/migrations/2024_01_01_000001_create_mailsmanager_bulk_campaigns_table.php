<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailsmanager_bulk_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->enum('recipient_type', ['all', 'active'])->default('all');
            $table->enum('status', ['pending', 'sending', 'done', 'failed'])->default('pending');
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->unsignedBigInteger('total_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailsmanager_bulk_campaigns');
    }
};
