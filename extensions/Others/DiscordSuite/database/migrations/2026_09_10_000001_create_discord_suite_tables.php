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
        // 1. Linked Discord Accounts
        if (!Schema::hasTable('linked_discord_accounts')) {
            Schema::create('linked_discord_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('discord_user_id', 32)->unique()->index();
                $table->string('discord_username', 100);
                $table->string('discord_discriminator', 10)->default('0');
                $table->string('discord_global_name', 100)->nullable();
                $table->string('discord_avatar', 255)->nullable();
                $table->text('access_token'); // Encrypted
                $table->text('refresh_token')->nullable(); // Encrypted
                $table->timestamp('token_expires_at')->nullable();
                $table->json('scopes')->nullable();
                $table->json('guild_membership_status')->nullable();
                $table->timestamp('linked_roles_synced_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'discord_user_id']);
            });
        }

        // 2. Discord Guilds
        if (!Schema::hasTable('discord_guilds')) {
            Schema::create('discord_guilds', function (Blueprint $table) {
                $table->id();
                $table->string('guild_id', 32)->unique()->index();
                $table->string('name', 150);
                $table->string('icon', 255)->nullable();
                $table->string('invite_url', 255)->nullable();
                $table->boolean('auto_join_enabled')->default(true);
                $table->string('welcome_channel_id', 32)->nullable();
                $table->text('welcome_message')->nullable();
                $table->boolean('bot_present')->default(false);
                $table->timestamps();
            });
        }

        // 3. Discord Role Mappings
        if (!Schema::hasTable('discord_role_mappings')) {
            Schema::create('discord_role_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('guild_id', 32)->index();
                $table->enum('rule_type', ['product', 'category', 'customer_tier'])->default('product');
                $table->unsignedBigInteger('target_id')->nullable()->index();
                $table->string('tier_type', 50)->nullable(); // verified, active, premium, vps, dedicated, minecraft
                $table->string('discord_role_id', 32)->index();
                $table->string('discord_role_name', 100)->nullable();
                $table->integer('priority')->default(0);
                $table->decimal('min_spend', 10, 2)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index(['rule_type', 'target_id']);
            });
        }

        // 4. Discord Notifications
        if (!Schema::hasTable('discord_notifications')) {
            Schema::create('discord_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('discord_user_id', 32)->nullable()->index();
                $table->string('channel_id', 32)->nullable();
                $table->string('message_id', 32)->nullable();
                $table->string('type', 50)->index(); // invoice_new, service_activated, reminder_7d, etc.
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->enum('status', ['pending', 'sent', 'failed_dm', 'failed_rate_limit', 'failed_other'])->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['reference_type', 'reference_id', 'type']);
            });
        }

        // 5. Discord Sync Logs
        if (!Schema::hasTable('discord_sync_logs')) {
            Schema::create('discord_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('discord_user_id', 32)->index();
                $table->string('guild_id', 32)->nullable();
                $table->string('action', 50); // roles_added, roles_removed, no_change, error, guild_joined
                $table->json('roles_added')->nullable();
                $table->json('roles_removed')->nullable();
                $table->text('details')->nullable();
                $table->enum('status', ['success', 'failed', 'retrying'])->default('success');
                $table->timestamps();

                $table->index(['discord_user_id', 'status']);
            });
        }

        // 6. Discord Linked Roles
        if (!Schema::hasTable('discord_linked_roles')) {
            Schema::create('discord_linked_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('discord_user_id', 32)->index();
                $table->integer('active_services')->default(0);
                $table->decimal('total_spent', 10, 2)->default(0.00);
                $table->integer('account_age_days')->default(0);
                $table->integer('invoices_paid')->default(0);
                $table->boolean('is_verified')->default(false);
                $table->integer('support_level')->default(1);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_linked_roles');
        Schema::dropIfExists('discord_sync_logs');
        Schema::dropIfExists('discord_notifications');
        Schema::dropIfExists('discord_role_mappings');
        Schema::dropIfExists('discord_guilds');
        Schema::dropIfExists('linked_discord_accounts');
    }
};
