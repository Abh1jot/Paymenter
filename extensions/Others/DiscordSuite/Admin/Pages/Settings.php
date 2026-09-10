<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use App\Models\Extension;
use App\Models\Setting;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordApiService;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordInteractionService;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordLinkedRolesService;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-settings-3-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-settings-3-fill';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 5;

    protected string $view = 'discord_suite::admin.pages.settings';

    public ?array $data = [];

    public function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Connection')
                ->icon('ri-wifi-line')
                ->color('gray')
                ->action(function (DiscordApiService $apiService) {
                    try {
                        $bot = $apiService->getBotUser();
                        $guilds = $apiService->getGuilds();
                        $username = $bot['username'] . ($bot['discriminator'] !== '0' ? "#{$bot['discriminator']}" : '');
                        $guildCount = count($guilds);

                        Notification::make()
                            ->title("Connected: {$username}")
                            ->body("Successfully verified Bot API connection! Bot is active in {$guildCount} Discord server(s).")
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Bot Connection Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('registerCommands')
                ->label('Register Commands')
                ->icon('ri-terminal-box-line')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Register Slash Commands Globally?')
                ->modalDescription('This will publish all 20 slash commands to Discord globally.')
                ->action(function (DiscordApiService $apiService, DiscordInteractionService $interactionService) {
                    try {
                        $commands = $interactionService->getCommandDefinitions();
                        $apiService->registerGlobalCommands($commands);

                        Notification::make()
                            ->title('Slash Commands Registered')
                            ->body("Successfully published " . count($commands) . " slash commands to Discord globally!")
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Command Registration Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('syncLinkedRoles')
                ->label('Sync Linked Roles')
                ->icon('ri-shield-check-line')
                ->color('warning')
                ->action(function (DiscordLinkedRolesService $linkedRolesService) {
                    try {
                        $linkedRolesService->registerMetadataSchema();

                        Notification::make()
                            ->title('Linked Roles Schema Registered')
                            ->body('Successfully registered metadata fields with Discord Linked Roles!')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Linked Roles Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function mount(): void
    {
        $extension = Extension::where('extension', 'DiscordSuite')->first();
        if ($extension) {
            $settings = $extension->settings->pluck('value', 'key')->toArray();

            $defaults = [
                'embed_color' => '#5865F2',
                'notify_invoice_created' => true,
                'notify_invoice_paid' => true,
                'notify_invoice_overdue' => true,
                'notify_service_activated' => true,
                'notify_service_suspended' => true,
                'notify_service_unsuspended' => true,
                'notify_service_expiring' => true,
                'notify_service_terminated' => true,
                'notify_ticket_reply' => true,
                'notify_product_upgraded' => true,
                'notify_credit_added' => true,
                'reminder_14d' => true,
                'reminder_7d' => true,
                'reminder_3d' => true,
                'reminder_1d' => true,
                'reminder_0d' => true,
            ];

            $this->form->fill(array_merge($defaults, $settings));
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make('Settings')
                        ->tabs([
                            Tab::make('Bot Credentials')
                                ->icon('ri-robot-2-line')
                                ->schema([
                                    Section::make('Discord Bot & Application API')
                                        ->description('Configure your Discord Developer Portal credentials for OAuth and API interactions')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TextInput::make('client_id')
                                                    ->label('Application (Client) ID')
                                                    ->placeholder('e.g. 123456789012345678')
                                                    ->helperText('Found under General Information in Discord Developer Portal'),
                                                TextInput::make('public_key')
                                                    ->label('Public Key (Ed25519)')
                                                    ->placeholder('e.g. e5b2... (64 hex characters)')
                                                    ->helperText('Cryptographically verifies Slash Command webhook requests'),
                                                TextInput::make('client_secret')
                                                    ->label('Client Secret')
                                                    ->password()
                                                    ->revealable()
                                                    ->helperText('OAuth2 Client Secret under OAuth2 -> General'),
                                                TextInput::make('bot_token')
                                                    ->label('Bot Token')
                                                    ->password()
                                                    ->revealable()
                                                    ->helperText('Found under Bot -> Reset Token in Developer Portal'),
                                            ]),
                                        ]),
                                ]),

                            Tab::make('Direct Messages')
                                ->icon('ri-notification-3-line')
                                ->schema([
                                    Section::make('Branding & Staff Logging')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                ColorPicker::make('embed_color')
                                                    ->label('Discord Embed Color')
                                                    ->default('#5865F2')
                                                    ->helperText('Accent color displayed on all Discord DM and alert embeds'),
                                                TextInput::make('staff_log_channel_id')
                                                    ->label('Staff Audit Channel ID')
                                                    ->placeholder('e.g. 109283746501928374')
                                                    ->helperText('Optional Discord channel ID for staff notifications and failed DM alerts'),
                                            ]),
                                        ]),

                                    Section::make('Customer Direct Message (DM) Notifications')
                                        ->description('Select which events automatically send private embeds to customers on Discord')
                                        ->schema([
                                            Grid::make(3)->schema([
                                                Toggle::make('notify_invoice_created')->label('New Invoice Created')->default(true),
                                                Toggle::make('notify_invoice_paid')->label('Invoice Paid Confirmation')->default(true),
                                                Toggle::make('notify_invoice_overdue')->label('Invoice Overdue Alert')->default(true),
                                                Toggle::make('notify_service_activated')->label('Service Activated')->default(true),
                                                Toggle::make('notify_service_suspended')->label('Service Suspended')->default(true),
                                                Toggle::make('notify_service_unsuspended')->label('Service Reactivated')->default(true),
                                                Toggle::make('notify_service_expiring')->label('Service Expiring Soon')->default(true),
                                                Toggle::make('notify_service_terminated')->label('Service Terminated')->default(true),
                                                Toggle::make('notify_ticket_reply')->label('Staff Ticket Reply')->default(true),
                                                Toggle::make('notify_product_upgraded')->label('Product Upgraded')->default(true),
                                                Toggle::make('notify_credit_added')->label('Credit Balance Added')->default(true),
                                            ]),
                                        ]),
                                ]),

                            Tab::make('Renewal Reminders')
                                ->icon('ri-time-line')
                                ->schema([
                                    Section::make('Automated Renewal Reminders')
                                        ->description('Daily automated milestones checked at 09:00 to alert customers before their services expire')
                                        ->schema([
                                            Grid::make(3)->schema([
                                                Toggle::make('reminder_14d')->label('14 Days Before Expiry')->default(true),
                                                Toggle::make('reminder_7d')->label('7 Days Before Expiry')->default(true),
                                                Toggle::make('reminder_3d')->label('3 Days Before Expiry')->default(true),
                                                Toggle::make('reminder_1d')->label('1 Day Before Expiry')->default(true),
                                                Toggle::make('reminder_0d')->label('Day of Expiry (Expired)')->default(true),
                                            ]),
                                        ]),
                                ]),

                            Tab::make('Setup Guide')
                                ->icon('ri-book-open-line')
                                ->schema([
                                    View::make('discord_suite::admin.partials.setup-guide'),
                                ]),
                        ])
                        ->persistTabInQueryString(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save Settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $extension = Extension::where('extension', 'DiscordSuite')->first();

        if ($extension) {
            // Build enabled notifications array for fast lookup
            $enabledList = [];
            foreach ($data as $key => $value) {
                if (str_starts_with($key, 'notify_') && $value) {
                    $enabledList[] = str_replace('notify_', '', $key);
                }
            }
            $data['enabled_notifications'] = $enabledList;

            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    [
                        'settingable_type' => Extension::class,
                        'settingable_id' => $extension->id,
                        'key' => $key,
                    ],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'encrypted' => in_array($key, ['bot_token', 'client_secret']),
                    ]
                );
            }

            Notification::make()
                ->title('Settings Saved Successfully')
                ->success()
                ->send();
        }
    }
}
