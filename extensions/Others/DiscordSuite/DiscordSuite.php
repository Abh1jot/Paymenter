<?php

namespace Paymenter\Extensions\Others\DiscordSuite;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Events\Invoice\Finalized as InvoiceFinalized;
use App\Events\Invoice\Paid as InvoicePaid;
use App\Events\Service\Created as ServiceCreated;
use App\Events\Service\Updated as ServiceUpdated;
use App\Events\TicketMessage\Created as TicketMessageCreated;
use App\Helpers\ExtensionHelper;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Pages\BotSettings;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Pages\DiscordOverview;
use Paymenter\Extensions\Others\DiscordSuite\Commands\DiscordBotGatewayCommand;
use Paymenter\Extensions\Others\DiscordSuite\Commands\DiscordRegisterCommandsCommand;
use Paymenter\Extensions\Others\DiscordSuite\Commands\DiscordSendRemindersCommand;
use Paymenter\Extensions\Others\DiscordSuite\Commands\DiscordSyncRolesCommand;
use Paymenter\Extensions\Others\DiscordSuite\Commands\DiscordValidateGuildsCommand;
use Paymenter\Extensions\Others\DiscordSuite\Listeners\HandleInvoiceFinalized;
use Paymenter\Extensions\Others\DiscordSuite\Listeners\HandleInvoicePaid;
use Paymenter\Extensions\Others\DiscordSuite\Listeners\HandleServiceCreated;
use Paymenter\Extensions\Others\DiscordSuite\Listeners\HandleServiceUpdated;
use Paymenter\Extensions\Others\DiscordSuite\Listeners\HandleTicketMessageCreated;
use Paymenter\Extensions\Others\DiscordSuite\Livewire\Account\DiscordSettings;
use Paymenter\Extensions\Others\DiscordSuite\Livewire\Dashboard\DiscordWidget;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordLinkedRole;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

#[ExtensionMeta(
    name: 'Discord Suite',
    description: 'Enterprise Discord integration: automated OAuth linking, bidirectional role sync, Discord Linked Roles API, slash commands via HTTP interactions, DM notifications, and renewal reminders.',
    version: '1.0.0',
    author: 'Abhijot',
    icon: 'ri-discord-line'
)]
class DiscordSuite extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Configure Discord credentials, test bot connection, and view step-by-step guides by visiting the <a class="text-primary-600 font-bold underline" href="' . url('/admin/discord-suite/bot-settings') . '">Discord Suite Admin Suite</a>.'),
            ],
            [
                'name' => 'client_id',
                'type' => 'text',
                'label' => 'Discord Application (Client) ID',
                'description' => 'Your Discord Application ID from Discord Developer Portal',
                'required' => false,
            ],
            [
                'name' => 'public_key',
                'type' => 'text',
                'label' => 'Discord Public Key (Ed25519)',
                'description' => 'Required for cryptographically verifying Discord Slash Command webhooks',
                'required' => false,
            ],
            [
                'name' => 'client_secret',
                'type' => 'password',
                'label' => 'Discord Client Secret',
                'description' => 'OAuth2 Client Secret',
                'required' => false,
            ],
            [
                'name' => 'bot_token',
                'type' => 'password',
                'label' => 'Discord Bot Token',
                'description' => 'Bot Token with Server Members privileged intent',
                'required' => false,
            ],
            [
                'name' => 'embed_color',
                'type' => 'text',
                'label' => 'Embed Brand Color',
                'description' => 'Hex color code (e.g. #5865F2)',
                'default' => '#5865F2',
                'required' => false,
            ],
            [
                'name' => 'staff_log_channel_id',
                'type' => 'text',
                'label' => 'Staff Audit Log Channel ID',
                'description' => 'Discord Channel ID to receive staff alerts and failed notification notices',
                'required' => false,
            ],
        ];
    }

    public function installed(): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DiscordSuite/database/migrations');
    }

    public function uninstalled(): void
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/DiscordSuite/database/migrations');
    }

    public function upgraded($oldVersion = null): void
    {
        ExtensionHelper::runMigrations('extensions/Others/DiscordSuite/database/migrations');
    }

    public function boot(): void
    {
        // 1. Dynamic Eloquent Relationships on Paymenter User model
        User::resolveRelationUsing('discordAccount', function (User $user) {
            return $user->hasOne(LinkedDiscordAccount::class, 'user_id');
        });

        User::resolveRelationUsing('discordLinkedRole', function (User $user) {
            return $user->hasOne(DiscordLinkedRole::class, 'user_id');
        });

        // 2. Load Web and API routes
        require __DIR__ . '/routes/web.php';
        require __DIR__ . '/routes/api.php';

        // 3. Register Views and Language Namespaces
        View::addNamespace('discord_suite', __DIR__ . '/resources/views');
        Lang::addNamespace('discord_suite', __DIR__ . '/resources/lang');

        // 4. Register Livewire Components
        Livewire::component('discord-suite.dashboard-widget', DiscordWidget::class);
        Livewire::component('discord-suite.account-settings', DiscordSettings::class);

        // 5. Register Paymenter Event Listeners
        Event::listen(InvoicePaid::class, HandleInvoicePaid::class);
        Event::listen(InvoiceFinalized::class, HandleInvoiceFinalized::class);
        Event::listen(ServiceCreated::class, HandleServiceCreated::class);
        Event::listen(ServiceUpdated::class, HandleServiceUpdated::class);
        Event::listen(TicketMessageCreated::class, HandleTicketMessageCreated::class);

        // 6. Navigation Hooks
        // Hook into Customer Account navigation (/account)
        Event::listen('navigation.account', function () {
            return [
                'name' => 'Discord Account',
                'url' => route('discord-suite.account.settings'),
                'icon' => 'ri-discord-line',
                'priority' => 35,
            ];
        });

        // Hook into Account Dropdown menu
        Event::listen('navigation.account-dropdown', function () {
            return [
                'name' => 'Discord Integration',
                'url' => route('discord-suite.account.settings'),
                'icon' => 'ri-discord-line',
                'priority' => 25,
            ];
        });

        // Hook into Customer Dashboard page (/dashboard)
        Event::listen('pages.dashboard', function () {
            return [
                'view' => '<div class="mt-8"><livewire:discord-suite.dashboard-widget /></div>',
                'priority' => 20,
            ];
        });

        // 7. Register Console Commands and Scheduled Tasks
        if (app()->runningInConsole()) {
            $this->commands([
                DiscordRegisterCommandsCommand::class,
                DiscordSyncRolesCommand::class,
                DiscordSendRemindersCommand::class,
                DiscordValidateGuildsCommand::class,
                DiscordBotGatewayCommand::class,
            ]);

            app()->booted(function () {
                $schedule = app(Schedule::class);

                // Hourly role synchronization
                $schedule->command('discord:sync-roles')
                    ->hourly()
                    ->withoutOverlapping()
                    ->runInBackground();

                // Daily automated renewal reminders at 09:00
                $schedule->command('discord:send-reminders')
                    ->dailyAt('09:00')
                    ->withoutOverlapping()
                    ->runInBackground();

                // Twice daily guild validation check
                $schedule->command('discord:validate-guilds')
                    ->twiceDaily(2, 14)
                    ->withoutOverlapping();
            });
        }
    }

    /**
     * Helper to register console commands from extension context.
     */
    protected function commands(array $commands): void
    {
        foreach ($commands as $command) {
            \Illuminate\Console\Application::starting(function ($artisan) use ($command) {
                $artisan->resolve($command);
            });
        }
    }
}
