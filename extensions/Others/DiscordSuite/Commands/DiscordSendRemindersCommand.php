<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Commands;

use Illuminate\Console\Command;
use Paymenter\Extensions\Others\DiscordSuite\Services\RenewalReminderService;

class DiscordSendRemindersCommand extends Command
{
    protected $signature = 'discord:send-reminders';

    protected $description = 'Scan services nearing expiration and send automated Discord DM renewal reminders';

    public function handle(RenewalReminderService $reminderService): int
    {
        $this->info('Scanning services for renewal reminders...');

        $results = $reminderService->processReminders();

        $this->table(['Milestone', 'Reminders Sent'], collect($results)->map(fn ($count, $key) => [
            'milestone' => str_replace('_', ' ', ucfirst($key)),
            'count' => $count,
        ]));

        $this->info('✅ Renewal reminders processed.');

        return Command::SUCCESS;
    }
}
