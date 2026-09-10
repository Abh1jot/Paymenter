<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\MassRoleSyncJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordInteractionService
{
    public function __construct(
        protected DiscordApiService $apiService,
        protected DiscordOAuthService $oauthService,
        protected RoleSyncEngine $roleSyncEngine
    ) {}

    /**
     * Handle incoming interaction from Discord Webhook.
     */
    public function handleInteraction(array $interaction): array
    {
        $type = $interaction['type'] ?? 0;

        // Type 1: PING (Discord endpoint validation)
        if ($type === 1) {
            return ['type' => 1]; // PONG
        }

        // Type 2: APPLICATION_COMMAND (Slash command)
        if ($type === 2) {
            $commandName = $interaction['data']['name'] ?? '';

            return $this->dispatchCommand($commandName, $interaction);
        }

        return [
            'type' => 4,
            'data' => [
                'content' => 'Unsupported interaction type.',
                'flags' => 64,
            ],
        ];
    }

    /**
     * Dispatch slash command to its specific handler.
     */
    public function dispatchCommand(string $name, array $interaction): array
    {
        $discordUserId = $interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? null;
        $linkedAccount = $discordUserId ? LinkedDiscordAccount::where('discord_user_id', $discordUserId)->with('user')->first() : null;
        $user = $linkedAccount?->user;

        // Customer Commands
        return match ($name) {
            'profile' => $this->handleProfile($user, $linkedAccount),
            'services' => $this->handleServices($user),
            'service' => $this->handleServiceDetail($user, $this->getOption($interaction, 'id')),
            'invoices' => $this->handleInvoices($user),
            'tickets' => $this->handleTickets($user),
            'renew' => $this->handleRenew($user, $this->getOption($interaction, 'id')),
            'balance', 'credits' => $this->handleBalance($user),
            'support' => $this->handleSupport(),
            'status' => $this->handleStatus(),
            'link' => $this->handleLink($discordUserId),
            'unlink' => $this->handleUnlink($linkedAccount),

            // Admin Commands
            'lookup' => $this->handleAdminLookup($interaction, $this->getOption($interaction, 'query')),
            'user' => $this->handleAdminUser($interaction, $this->getOption($interaction, 'user_id')),
            'services-user' => $this->handleAdminServicesUser($interaction, $this->getOption($interaction, 'user_id')),
            'invoices-user' => $this->handleAdminInvoicesUser($interaction, $this->getOption($interaction, 'user_id')),
            'tickets-user' => $this->handleAdminTicketsUser($interaction, $this->getOption($interaction, 'user_id')),
            'syncroles' => $this->handleAdminSyncRoles($interaction, $this->getOption($interaction, 'user_id')),
            'forcerolesync' => $this->handleAdminForceRoleSync($interaction),
            'broadcast' => $this->handleAdminBroadcast($interaction, $this->getOption($interaction, 'channel_id'), $this->getOption($interaction, 'message')),
            'stats' => $this->handleAdminStats($interaction),

            default => [
                'type' => 4,
                'data' => [
                    'content' => "Unknown command: /{$name}",
                    'flags' => 64,
                ],
            ],
        };
    }

    // Customer Command Implementations

    protected function handleProfile(?User $user, ?LinkedDiscordAccount $account): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $activeServicesCount = $user->services()->where('status', Service::STATUS_ACTIVE)->count();
        $unpaidInvoicesCount = $user->invoices()->where('status', Invoice::STATUS_PENDING)->count();
        $creditsSum = $user->credits()->sum('amount');

        $embed = [
            'title' => "👤 Customer Profile: {$user->name}",
            'color' => 0x5865F2,
            'thumbnail' => ['url' => $account->avatar_url],
            'fields' => [
                ['name' => 'Email', 'value' => $user->email, 'inline' => true],
                ['name' => 'Member Since', 'value' => $user->created_at->format('M d, Y'), 'inline' => true],
                ['name' => 'Discord Account', 'value' => "{$account->formatted_tag} (`{$account->discord_user_id}`)", 'inline' => false],
                ['name' => 'Active Services', 'value' => (string) $activeServicesCount, 'inline' => true],
                ['name' => 'Unpaid Invoices', 'value' => (string) $unpaidInvoicesCount, 'inline' => true],
                ['name' => 'Credit Balance', 'value' => '$' . number_format((float) $creditsSum, 2), 'inline' => true],
            ],
            'footer' => ['text' => config('app.name', 'Paymenter Billing')],
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleServices(?User $user): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $services = $user->services()->with('product')->take(10)->get();
        if ($services->isEmpty()) {
            return $this->respondText("📦 You currently have no services registered on your account.", ephemeral: true);
        }

        $fields = [];
        foreach ($services as $service) {
            $statusEmoji = match ($service->status) {
                Service::STATUS_ACTIVE => '🟢',
                Service::STATUS_SUSPENDED => '🔴',
                Service::STATUS_PENDING => '🟡',
                default => '⚪',
            };
            $expiry = $service->expires_at ? $service->expires_at->format('M d, Y') : 'Never';
            $fields[] = [
                'name' => "{$statusEmoji} #{$service->id} - {$service->product->name}",
                'value' => "Status: **" . ucfirst($service->status) . "** | Renews: `{$expiry}` | Price: `{$service->formatted_price}`",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "📦 Your Services (" . $services->count() . ")",
            'color' => 0x2ECC71,
            'description' => "Use `/service <id>` for detailed information or `/renew <id>` to extend your subscription.",
            'fields' => $fields,
            'footer' => ['text' => config('app.name')],
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleServiceDetail(?User $user, mixed $serviceId): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $service = $user->services()->where('id', $serviceId)->with(['product.category', 'plan'])->first();
        if (!$service) {
            return $this->respondText("❌ Service #{$serviceId} was not found on your account.", ephemeral: true);
        }

        $embed = [
            'title' => "🔧 Service Details: {$service->product->name} (#{$service->id})",
            'color' => 0x3498DB,
            'fields' => [
                ['name' => 'Category', 'value' => $service->product->category?->name ?? 'General', 'inline' => true],
                ['name' => 'Status', 'value' => ucfirst($service->status), 'inline' => true],
                ['name' => 'Price', 'value' => (string) $service->formatted_price, 'inline' => true],
                ['name' => 'Expires At', 'value' => $service->expires_at?->format('M d, Y') ?? 'Never', 'inline' => true],
                ['name' => 'Billing Unit', 'value' => ucfirst($service->plan?->billing_unit ?? 'One-time'), 'inline' => true],
            ],
            'footer' => ['text' => "Manage in dashboard: " . route('services.show', $service->id)],
        ];

        $button = [
            'type' => 1,
            'components' => [
                [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Open in Dashboard',
                    'url' => route('services.show', $service->id),
                ],
            ],
        ];

        return $this->respondEmbed($embed, ephemeral: true, components: [$button]);
    }

    protected function handleInvoices(?User $user): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $invoices = $user->invoices()->where('status', Invoice::STATUS_PENDING)->take(5)->get();
        if ($invoices->isEmpty()) {
            return $this->respondText("🎉 You have no unpaid invoices! Your account is in good standing.", ephemeral: true);
        }

        $fields = [];
        foreach ($invoices as $inv) {
            $due = $inv->due_at ? $inv->due_at->format('M d, Y') : 'Immediate';
            $fields[] = [
                'name' => "📄 Invoice #{$inv->id}",
                'value' => "Total: **{$inv->formatted_total}** | Due: `{$due}`\n[Pay Now](" . route('invoices.show', $inv->id) . ")",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "⚠️ Unpaid Invoices (" . $invoices->count() . ")",
            'color' => 0xE67E22,
            'fields' => $fields,
            'footer' => ['text' => 'Pay promptly to prevent service interruptions.'],
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleTickets(?User $user): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $tickets = $user->tickets()->where('status', '!=', 'closed')->take(5)->get();
        if ($tickets->isEmpty()) {
            return $this->respondText("📨 You have no open support tickets. Need help? Use `/support` to open one.", ephemeral: true);
        }

        $fields = [];
        foreach ($tickets as $t) {
            $fields[] = [
                'name' => "#{$t->id} - {$t->subject}",
                'value' => "Status: `{$t->status}` | Dept: `{$t->department}`\n[View Ticket](" . route('tickets.show', $t->id) . ")",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "🎫 Your Open Tickets (" . $tickets->count() . ")",
            'color' => 0x9B59B6,
            'fields' => $fields,
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleRenew(?User $user, mixed $serviceId): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $service = $user->services()->where('id', $serviceId)->first();
        if (!$service) {
            return $this->respondText("❌ Service #{$serviceId} was not found on your account.", ephemeral: true);
        }

        $url = route('services.show', $service->id);

        $embed = [
            'title' => "🔄 Renew Service #{$service->id}",
            'description' => "Click the button below to renew **{$service->product->name}** directly through the billing portal.",
            'color' => 0x2ECC71,
        ];

        $button = [
            'type' => 1,
            'components' => [
                [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Renew Now',
                    'url' => $url,
                ],
            ],
        ];

        return $this->respondEmbed($embed, ephemeral: true, components: [$button]);
    }

    protected function handleBalance(?User $user): array
    {
        if (!$user) {
            return $this->respondNotLinked();
        }

        $credits = $user->credits;
        $total = $credits->sum('amount');

        $embed = [
            'title' => "💰 Account Credit Balance",
            'description' => "Your current available balance is **$" . number_format((float) $total, 2) . "**.",
            'color' => 0xF1C40F,
            'footer' => ['text' => 'Credits are automatically deducted on future renewals and checkout.'],
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleSupport(): array
    {
        $url = route('tickets.create');

        $embed = [
            'title' => "🆘 Need Help or Support?",
            'description' => "Our team is available 24/7. Click below to open a ticket in our billing system or visit our community support channels.",
            'color' => 0x3498DB,
        ];

        $button = [
            'type' => 1,
            'components' => [
                [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Create Support Ticket',
                    'url' => $url,
                ],
            ],
        ];

        return $this->respondEmbed($embed, ephemeral: true, components: [$button]);
    }

    protected function handleStatus(): array
    {
        $embed = [
            'title' => "📊 System Status: All Systems Operational",
            'description' => "All hosting nodes, API endpoints, and billing gateways are operating normally.",
            'color' => 0x2ECC71,
            'fields' => [
                ['name' => 'Billing Platform', 'value' => '🟢 Operational', 'inline' => true],
                ['name' => 'Hosting Nodes', 'value' => '🟢 Operational', 'inline' => true],
                ['name' => 'Discord Gateway', 'value' => '🟢 Connected', 'inline' => true],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->respondEmbed($embed, ephemeral: false);
    }

    protected function handleLink(?string $discordUserId): array
    {
        $linkUrl = route('discord-suite.oauth.redirect');

        $embed = [
            'title' => "🔗 Link Your Discord Account",
            'description' => "Connect your Discord account to your billing account to unlock automated customer roles, instant renewal alerts, and bot slash commands.",
            'color' => 0x5865F2,
        ];

        $button = [
            'type' => 1,
            'components' => [
                [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Link Discord Account',
                    'url' => $linkUrl,
                ],
            ],
        ];

        return $this->respondEmbed($embed, ephemeral: true, components: [$button]);
    }

    protected function handleUnlink(?LinkedDiscordAccount $account): array
    {
        if (!$account) {
            return $this->respondNotLinked();
        }

        $account->delete();

        return $this->respondText("🔓 Your Discord account has been disconnected from your billing profile. Your customer roles will be removed upon the next sync cycle.", ephemeral: true);
    }

    // Admin Command Implementations

    protected function handleAdminLookup(array $interaction, ?string $query): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        if (!$query) {
            return $this->respondText("❌ Please provide a search query (email, name, or user ID).", ephemeral: true);
        }

        $users = User::where('email', 'LIKE', "%{$query}%")
            ->orWhere('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->orWhere('id', $query)
            ->take(5)
            ->get();

        if ($users->isEmpty()) {
            return $this->respondText("🔍 No customers found matching `{$query}`.", ephemeral: true);
        }

        $fields = [];
        foreach ($users as $u) {
            $linked = $u->discordAccount ? "✅ Linked ({$u->discordAccount->formatted_tag})" : "❌ Not Linked";
            $services = $u->services()->where('status', Service::STATUS_ACTIVE)->count();
            $fields[] = [
                'name' => "User #{$u->id}: {$u->name}",
                'value' => "Email: `{$u->email}` | Discord: {$linked} | Active Services: `{$services}`",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "🔎 Customer Lookup Results for: {$query}",
            'color' => 0xF39C12,
            'fields' => $fields,
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleAdminUser(array $interaction, mixed $userId): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $user = User::with(['services', 'invoices', 'discordAccount'])->find($userId);
        if (!$user) {
            return $this->respondText("❌ User #{$userId} not found.", ephemeral: true);
        }

        $embed = [
            'title' => "🛠️ Admin Inspection: User #{$user->id} ({$user->name})",
            'color' => 0x9B59B6,
            'fields' => [
                ['name' => 'Email', 'value' => $user->email, 'inline' => true],
                ['name' => 'Role', 'value' => $user->role?->name ?? 'Customer', 'inline' => true],
                ['name' => 'Discord User', 'value' => $user->discordAccount ? "{$user->discordAccount->formatted_tag} (`{$user->discordAccount->discord_user_id}`)" : 'None', 'inline' => false],
                ['name' => 'Total Services', 'value' => (string) $user->services->count(), 'inline' => true],
                ['name' => 'Paid Invoices', 'value' => (string) $user->invoices->where('status', 'paid')->count(), 'inline' => true],
                ['name' => 'Total Spent', 'value' => '$' . number_format((float) $user->invoices->where('status', 'paid')->sum('total'), 2), 'inline' => true],
            ],
            'footer' => ['text' => 'Paymenter Admin Suite'],
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleAdminServicesUser(array $interaction, mixed $userId): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->respondText("❌ User #{$userId} not found.", ephemeral: true);
        }

        $services = $user->services()->with('product')->get();
        if ($services->isEmpty()) {
            return $this->respondText("User #{$userId} has no services.", ephemeral: true);
        }

        $fields = [];
        foreach ($services as $s) {
            $fields[] = [
                'name' => "#{$s->id} - {$s->product->name} (" . ucfirst($s->status) . ")",
                'value' => "Renews: " . ($s->expires_at?->format('Y-m-d') ?? 'Never') . " | Price: {$s->formatted_price}",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "Services for {$user->name} (#{$user->id})",
            'color' => 0x34495E,
            'fields' => $fields,
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleAdminInvoicesUser(array $interaction, mixed $userId): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->respondText("❌ User #{$userId} not found.", ephemeral: true);
        }

        $invoices = $user->invoices()->take(10)->get();
        $fields = [];
        foreach ($invoices as $inv) {
            $fields[] = [
                'name' => "Invoice #{$inv->id} - " . ucfirst($inv->status),
                'value' => "Total: {$inv->formatted_total} | Due: " . ($inv->due_at?->format('Y-m-d') ?? 'N/A'),
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "Invoices for {$user->name} (#{$user->id})",
            'color' => 0x34495E,
            'fields' => $fields,
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleAdminTicketsUser(array $interaction, mixed $userId): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->respondText("❌ User #{$userId} not found.", ephemeral: true);
        }

        $tickets = $user->tickets()->take(10)->get();
        $fields = [];
        foreach ($tickets as $t) {
            $fields[] = [
                'name' => "#{$t->id} - {$t->subject}",
                'value' => "Status: {$t->status} | Department: {$t->department}",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "Tickets for {$user->name} (#{$user->id})",
            'color' => 0x34495E,
            'fields' => $fields,
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    protected function handleAdminSyncRoles(array $interaction, mixed $userId): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $account = $userId
            ? LinkedDiscordAccount::where('user_id', $userId)->first()
            : null;

        if (!$account) {
            return $this->respondText("❌ No linked Discord account found for user #{$userId}.", ephemeral: true);
        }

        SyncUserRolesJob::dispatch($account->user_id);

        return $this->respondText("⚡ Role synchronization job queued for user #{$userId} ({$account->formatted_tag}).", ephemeral: true);
    }

    protected function handleAdminForceRoleSync(array $interaction): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        MassRoleSyncJob::dispatch();

        return $this->respondText("🚀 Mass role synchronization job has been dispatched across all linked accounts.", ephemeral: true);
    }

    protected function handleAdminBroadcast(array $interaction, ?string $channelId, ?string $message): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        if (!$channelId || !$message) {
            return $this->respondText("❌ Please provide both channel_id and message.", ephemeral: true);
        }

        try {
            $embed = [
                'title' => "📢 Announcement",
                'description' => $message,
                'color' => 0x5865F2,
                'footer' => ['text' => config('app.name')],
                'timestamp' => now()->toIso8601String(),
            ];

            $this->apiService->sendChannelMessage($channelId, ['embeds' => [$embed]]);

            return $this->respondText("✅ Broadcast successfully sent to channel <#{$channelId}>.", ephemeral: true);
        } catch (Exception $e) {
            return $this->respondText("❌ Failed sending broadcast: " . $e->getMessage(), ephemeral: true);
        }
    }

    protected function handleAdminStats(array $interaction): array
    {
        if (!$this->checkAdmin($interaction)) {
            return $this->respondForbidden();
        }

        $totalUsers = User::count();
        $linkedUsers = LinkedDiscordAccount::count();
        $activeServices = Service::where('status', Service::STATUS_ACTIVE)->count();
        $unpaidInvoices = Invoice::where('status', Invoice::STATUS_PENDING)->count();

        $linkRate = $totalUsers > 0 ? round(($linkedUsers / $totalUsers) * 100, 1) : 0;

        $embed = [
            'title' => "📈 Hosting & Discord Integration Statistics",
            'color' => 0x2ECC71,
            'fields' => [
                ['name' => 'Total Users', 'value' => (string) $totalUsers, 'inline' => true],
                ['name' => 'Linked Accounts', 'value' => "{$linkedUsers} ({$linkRate}%)", 'inline' => true],
                ['name' => 'Active Services', 'value' => (string) $activeServices, 'inline' => true],
                ['name' => 'Unpaid Invoices', 'value' => (string) $unpaidInvoices, 'inline' => true],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->respondEmbed($embed, ephemeral: true);
    }

    // Helper utilities

    protected function checkAdmin(array $interaction): bool
    {
        $permissions = (int) ($interaction['member']['permissions'] ?? 0);
        if (($permissions & 0x8) === 0x8) {
            return true;
        }

        $discordId = $interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? null;
        if ($discordId) {
            $account = LinkedDiscordAccount::where('discord_user_id', $discordId)->with('user')->first();
            if ($account?->user && $account->user->role_id !== null) {
                return true;
            }
        }

        return false;
    }

    protected function getOption(array $interaction, string $name): mixed
    {
        foreach ($interaction['data']['options'] ?? [] as $opt) {
            if ($opt['name'] === $name) {
                return $opt['value'] ?? null;
            }
        }

        return null;
    }

    protected function respondNotLinked(): array
    {
        $linkUrl = route('discord-suite.oauth.redirect');

        $embed = [
            'title' => "⚠️ Discord Account Not Linked",
            'description' => "You have not linked your Discord account to a Paymenter profile yet. Click below to link your account in seconds!",
            'color' => 0xE74C3C,
        ];

        $button = [
            'type' => 1,
            'components' => [
                [
                    'type' => 2,
                    'style' => 5,
                    'label' => 'Link Account Now',
                    'url' => $linkUrl,
                ],
            ],
        ];

        return $this->respondEmbed($embed, ephemeral: true, components: [$button]);
    }

    protected function respondForbidden(): array
    {
        return $this->respondText("⛔ You do not have permission to execute this administrative command.", ephemeral: true);
    }

    protected function respondText(string $content, bool $ephemeral = false): array
    {
        $res = [
            'type' => 4,
            'data' => ['content' => $content],
        ];
        if ($ephemeral) {
            $res['data']['flags'] = 64;
        }

        return $res;
    }

    protected function respondEmbed(array $embed, bool $ephemeral = false, array $components = []): array
    {
        $res = [
            'type' => 4,
            'data' => ['embeds' => [$embed]],
        ];
        if ($ephemeral) {
            $res['data']['flags'] = 64;
        }
        if (!empty($components)) {
            $res['data']['components'] = $components;
        }

        return $res;
    }

    /**
     * Returns the array of slash command definitions for Discord API registration.
     */
    public function getCommandDefinitions(): array
    {
        return [
            // Customer Commands
            ['name' => 'profile', 'description' => 'View your linked customer account details and credit balance.'],
            ['name' => 'services', 'description' => 'List all active and pending services on your account.'],
            [
                'name' => 'service',
                'description' => 'View detailed information on a specific service.',
                'options' => [
                    ['name' => 'id', 'description' => 'The ID of the service', 'type' => 4, 'required' => true],
                ],
            ],
            ['name' => 'invoices', 'description' => 'View your recent unpaid invoices with instant payment links.'],
            ['name' => 'tickets', 'description' => 'Check your active support tickets and their statuses.'],
            [
                'name' => 'renew',
                'description' => 'Generate an instant renewal link for a service.',
                'options' => [
                    ['name' => 'id', 'description' => 'Service ID to renew', 'type' => 4, 'required' => true],
                ],
            ],
            ['name' => 'balance', 'description' => 'Check your current account credit balance.'],
            ['name' => 'credits', 'description' => 'Check your current account credit balance.'],
            ['name' => 'support', 'description' => 'Get immediate support links and open a ticket.'],
            ['name' => 'status', 'description' => 'Check the operational status of all hosting nodes and systems.'],
            ['name' => 'link', 'description' => 'Link your Discord account to Paymenter billing.'],
            ['name' => 'unlink', 'description' => 'Disconnect your Discord account from Paymenter.'],

            // Admin Commands
            [
                'name' => 'lookup',
                'description' => '[Admin] Search customer by email, name, or ID.',
                'options' => [
                    ['name' => 'query', 'description' => 'Search term', 'type' => 3, 'required' => true],
                ],
            ],
            [
                'name' => 'user',
                'description' => '[Admin] View full customer profile and financial summary.',
                'options' => [
                    ['name' => 'user_id', 'description' => 'Paymenter user ID', 'type' => 4, 'required' => true],
                ],
            ],
            [
                'name' => 'services-user',
                'description' => '[Admin] List all services belonging to a customer.',
                'options' => [
                    ['name' => 'user_id', 'description' => 'Customer user ID', 'type' => 4, 'required' => true],
                ],
            ],
            [
                'name' => 'invoices-user',
                'description' => '[Admin] List invoices belonging to a customer.',
                'options' => [
                    ['name' => 'user_id', 'description' => 'Customer user ID', 'type' => 4, 'required' => true],
                ],
            ],
            [
                'name' => 'tickets-user',
                'description' => '[Admin] List tickets belonging to a customer.',
                'options' => [
                    ['name' => 'user_id', 'description' => 'Customer user ID', 'type' => 4, 'required' => true],
                ],
            ],
            [
                'name' => 'syncroles',
                'description' => '[Admin] Force immediate role synchronization for a user.',
                'options' => [
                    ['name' => 'user_id', 'description' => 'Customer user ID', 'type' => 4, 'required' => true],
                ],
            ],
            ['name' => 'forcerolesync', 'description' => '[Admin] Dispatch mass role sync across all linked accounts.'],
            [
                'name' => 'broadcast',
                'description' => '[Admin] Send an announcement embed to a specified channel.',
                'options' => [
                    ['name' => 'channel_id', 'description' => 'Discord channel ID', 'type' => 3, 'required' => true],
                    ['name' => 'message', 'description' => 'Announcement content', 'type' => 3, 'required' => true],
                ],
            ],
            ['name' => 'stats', 'description' => '[Admin] View live statistics on users, services, and sync metrics.'],
        ];
    }
}
