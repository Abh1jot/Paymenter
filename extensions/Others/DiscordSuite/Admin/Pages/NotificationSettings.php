<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Admin\Pages;

use App\Models\Extension;
use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\DiscordSuite\Admin\Clusters\DiscordSuiteCluster;

class NotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = DiscordSuiteCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-notification-3-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-notification-3-fill';

    protected static ?string $navigationLabel = 'Notification Settings';

    protected static ?int $navigationSort = 4;

    protected string $view = 'discord_suite::admin.notification-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $extension = Extension::where('extension', 'DiscordSuite')->first();
        if ($extension) {
            $settings = $extension->settings->pluck('value', 'key')->toArray();

            // Default all notifications to true if not explicitly disabled
            $defaultEvents = [
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
                'embed_color' => '#5865F2',
            ];

            $this->form->fill(array_merge($defaultEvents, $settings));
        }
    }

    public function form(Form $form): Form
    {
        return $form
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

                Section::make('Renewal Reminder Intervals')
                    ->description('Automated milestones checked daily by Laravel scheduler')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('reminder_14d')->label('14 Days Before Expiry')->default(true),
                            Toggle::make('reminder_7d')->label('7 Days Before Expiry')->default(true),
                            Toggle::make('reminder_3d')->label('3 Days Before Expiry')->default(true),
                            Toggle::make('reminder_1d')->label('1 Day Before Expiry')->default(true),
                            Toggle::make('reminder_0d')->label('Day of Expiry (Expired)')->default(true),
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
            // Build enabled notifications array for fast lookup in DiscordNotificationService
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
                    ]
                );
            }

            Notification::make()
                ->title('Notification Settings Saved')
                ->success()
                ->send();
        }
    }
}
