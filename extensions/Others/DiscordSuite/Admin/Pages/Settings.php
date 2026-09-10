<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use App\Models\Extension;
use App\Models\Setting;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
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
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
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
                                    Section::make('1. Create Discord Application')
                                        ->description('Register your bot application in the Discord Developer Portal')
                                        ->schema([
                                            Placeholder::make('step1')
                                                ->hiddenLabel()
                                                ->content(new HtmlString('<div style="font-size: 0.875rem; color: #d1d5db; line-height: 1.6;">Head to the <a href="https://discord.com/developers/applications" target="_blank" style="color: #60a5fa; text-decoration: underline; font-weight: 600;">Discord Developer Portal</a>, click <strong>"New Application"</strong> in the top right, and name it your company brand (e.g. <em>Azion Cloud Bot</em>).</div>')),
                                        ]),

                                    Section::make('2. Enable Privileged Gateway Intents & Copy Bot Token')
                                        ->description('Required for checking server membership, status, and managing roles')
                                        ->schema([
                                            Placeholder::make('step2')
                                                ->hiddenLabel()
                                                ->content(new HtmlString('<div style="font-size: 0.875rem; color: #d1d5db; line-height: 1.6;"><p>In your Discord application, navigate to the <strong>Bot</strong> tab on the left menu:</p><ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem; color: #9ca3af;"><li>Click <strong>"Reset Token"</strong> to generate your secret <strong>Bot Token</strong>, then paste it into the <em>Bot Credentials</em> tab above.</li><li>Scroll down to <strong>Privileged Gateway Intents</strong> and turn ON <strong style="color: #fbbf24;">SERVER MEMBERS INTENT</strong>.</li></ul></div>')),
                                        ]),

                                    Section::make('3. Configure OAuth2 Redirect URLs')
                                        ->description('Copy and paste these exact Redirect URLs into OAuth2 -> General in Discord Developer Portal')
                                        ->schema([
                                            Grid::make(1)->schema([
                                                TextInput::make('oauth_redirect_url')
                                                    ->label('Main Account Linking Redirect URL')
                                                    ->default(url('/discord-suite/oauth/callback'))
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->helperText('Used for customer account linking and server auto-join'),
                                                TextInput::make('linked_roles_redirect_url')
                                                    ->label('Discord Linked Roles Redirect URL')
                                                    ->default(url('/discord-suite/linked-roles/callback'))
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->helperText('Used for official Discord Connected Roles verification'),
                                            ]),
                                        ]),

                                    Section::make('4. Configure Interactions Endpoint URL (Slash Commands)')
                                        ->description('Enable real-time HTTP-based slash command responses')
                                        ->schema([
                                            TextInput::make('interactions_url')
                                                ->label('Interactions Endpoint URL')
                                                ->default(url('/api/discord-suite/interactions'))
                                                ->disabled()
                                                ->dehydrated(false)
                                                ->helperText('In General Information, paste into Interactions Endpoint URL. Important: Save your Public Key in Bot Credentials first so Discord\'s Ed25519 validation ping succeeds!'),
                                        ]),

                                    Section::make('5. Invite Bot & Configure Role Hierarchy')
                                        ->description('Permissions and role hierarchy configuration')
                                        ->schema([
                                            Placeholder::make('step5')
                                                ->hiddenLabel()
                                                ->content(new HtmlString('<div style="font-size: 0.875rem; color: #d1d5db; line-height: 1.6;"><p>Under <strong>OAuth2 -> URL Generator</strong>, select scopes <code>bot</code> and <code>applications.commands</code> with permissions:</p><div style="display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0;"><span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #e5e7eb;">Manage Roles</span><span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #e5e7eb;">Create Instant Invite</span><span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #e5e7eb;">Send Messages</span><span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #e5e7eb;">Embed Links</span><span style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #e5e7eb;">Use Slash Commands</span></div><div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 12px; color: #fbbf24; font-size: 13px; line-height: 1.5; margin-top: 10px;"><strong>⚠️ CRITICAL ROLE HIERARCHY RULE:</strong> In Discord <em>Server Settings -> Roles</em>, drag the Bot\'s managed role <strong>ABOVE</strong> all customer roles it will assign. Discord strictly blocks bots from assigning any role higher than or equal to their own rank.</div></div>')),
                                        ]),
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
