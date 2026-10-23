<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('notification_templates')) {
            return;
        }

        $template = DB::table('notification_templates')->where('key', 'password_reset')->first();

        $defaultBody = <<<'HTML'
# Password reset
You are receiving this email because we received a password reset request for your account.

**Reset password**
<div class="action">
	<a class="button button-blue" href="{{ $url }}">
		Reset password
	</a>
</div>

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.
HTML;

        if (!$template) {
            DB::table('notification_templates')->insert([
                'key' => 'password_reset',
                'subject' => 'Password reset',
                'body' => $defaultBody,
                'enabled' => true,
                'mail_enabled' => 'force',
                'in_app_enabled' => 'never',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Ensure template is enabled and mail_enabled is not 'never'
            $updates = [];
            if (!$template->enabled) {
                $updates['enabled'] = true;
            }
            if ($template->mail_enabled === 'never') {
                $updates['mail_enabled'] = 'force';
            }
            if (!empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('notification_templates')
                    ->where('key', 'password_reset')
                    ->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive: preserve template
    }
};
