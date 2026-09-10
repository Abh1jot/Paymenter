<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use App\Models\Extension;
use App\Models\Setting;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordApiService;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordInteractionService;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordLinkedRolesService;

class BotSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-robot-2-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-robot-2-fill';

    protected static ?string $navigationLabel = 'Bot & Setup Guide';

    protected static ?int $navigationSort = 3;

    protected string $view = 'discord_suite::admin.bot-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $extension = Extension::where('extension', 'DiscordSuite')->first();
        if ($extension) {
            $settings = $extension->settings->pluck('value', 'key')->toArray();
            $this->form->fill($settings);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Discord Bot & Application Credentials')
                    ->description('Enter your credentials from the Discord Developer Portal')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('client_id')
                                ->label('Application (Client) ID')
                                ->required()
                                ->placeholder('e.g. 123456789012345678')
                                ->helperText('Found under General Information in Discord Developer Portal'),
                            TextInput::make('public_key')
                                ->label('Public Key (Ed25519)')
                                ->required()
                                ->placeholder('e.g. e5b2... (64 hex characters)')
                                ->helperText('Used by Discord to cryptographically verify Slash Command webhooks'),
                            TextInput::make('client_secret')
                                ->label('Client Secret')
                                ->password()
                                ->revealable()
                                ->required()
                                ->helperText('OAuth2 Client Secret under OAuth2 -> General'),
                            TextInput::make('bot_token')
                                ->label('Bot Token')
                                ->password()
                                ->revealable()
                                ->required()
                                ->helperText('Found under Bot -> Reset Token in Developer Portal'),
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
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    [
                        'settingable_type' => Extension::class,
                        'settingable_id' => $extension->id,
                        'key' => $key,
                    ],
                    [
                        'value' => $value,
                        'encrypted' => in_array($key, ['bot_token', 'client_secret']),
                    ]
                );
            }

            Notification::make()
                ->title('Bot Settings Saved Successfully')
                ->success()
                ->send();
        }
    }

    public function testConnection(DiscordApiService $apiService): void
    {
        try {
            $bot = $apiService->getBotUser();
            $guilds = $apiService->getGuilds();

            $username = $bot['username'] . ($bot['discriminator'] !== '0' ? "#{$bot['discriminator']}" : '');
            $guildCount = count($guilds);

            Notification::make()
                ->title("Connected: {$username}")
                ->body("Successfully verified Bot API connection! Bot is currently in {$guildCount} Discord server(s).")
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function registerCommands(DiscordApiService $apiService, DiscordInteractionService $interactionService): void
    {
        try {
            $commands = $interactionService->getCommandDefinitions();
            $apiService->registerGlobalCommands($commands);

            Notification::make()
                ->title('Slash Commands Registered')
                ->body("Successfully published " . count($commands) . " slash commands to Discord globally! (Takes up to a few minutes to appear in all servers).")
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Command Registration Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncLinkedRolesSchema(DiscordLinkedRolesService $linkedRolesService): void
    {
        try {
            $linkedRolesService->registerMetadataSchema();

            Notification::make()
                ->title('Linked Roles Schema Registered')
                ->body('Successfully registered Active Services, Total Spent, Invoices Paid, and Account Age metadata fields with Discord Linked Roles!')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Linked Roles Sync Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
