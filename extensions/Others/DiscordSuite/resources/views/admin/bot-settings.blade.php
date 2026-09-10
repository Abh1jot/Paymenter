<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="flex items-center gap-3 mt-6">
            <x-filament::button type="submit">
                Save Credentials
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="testConnection">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-500 fill-current" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                    Test Bot Connection
                </span>
            </x-filament::button>

            <x-filament::button type="button" color="info" wire:click="registerCommands">
                Register Slash Commands (/profile, /renew, etc.)
            </x-filament::button>

            <x-filament::button type="button" color="warning" wire:click="syncLinkedRolesSchema">
                Sync Discord Linked Roles Schema
            </x-filament::button>
        </div>
    </form>

    <!-- Comprehensive Discord Setup Guide -->
    <div class="mt-10 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
            <div class="p-2.5 bg-[#5865F2]/10 text-[#5865F2] rounded-lg">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Complete Discord Application Setup Guide</h2>
                <p class="text-sm text-gray-500">Follow these step-by-step instructions to configure Discord Suite with zero downtime</p>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Step 1 -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-2">
                    <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">1</span>
                    <h3 class="font-bold text-gray-900 dark:text-white">Create Discord Application</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 ml-9">
                    Visit the <a href="https://discord.com/developers/applications" target="_blank" class="text-primary font-semibold hover:underline">Discord Developer Portal</a>, click <strong>"New Application"</strong>, and enter your company or community name (e.g. <em>"MyHosting Billing"</em>).
                </p>
            </div>

            <!-- Step 2 -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-2">
                    <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">2</span>
                    <h3 class="font-bold text-gray-900 dark:text-white">Enable Privileged Gateway Intents</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 ml-9 mb-2">
                    In your Application, navigate to the <strong>Bot</strong> tab on the left menu:
                </p>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 ml-9 space-y-1">
                    <li>Click <strong>"Reset Token"</strong> to generate your <strong>Bot Token</strong> and copy it into the field above.</li>
                    <li>Scroll down to <strong>Privileged Gateway Intents</strong> and enable <strong class="text-amber-500">SERVER MEMBERS INTENT</strong> (Required for member sync and role assignment).</li>
                </ul>
            </div>

            <!-- Step 3 -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-2">
                    <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">3</span>
                    <h3 class="font-bold text-gray-900 dark:text-white">Configure OAuth2 Redirect URLs</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 ml-9 mb-2">
                    Navigate to <strong>OAuth2 -> General</strong>, copy your <strong>Client ID</strong> and <strong>Client Secret</strong>, then add the following two Redirect URLs:
                </p>
                <div class="ml-9 space-y-2">
                    <div class="flex items-center justify-between bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 rounded font-mono text-xs text-gray-800 dark:text-gray-200">
                        <span>{{ url('/discord-suite/oauth/callback') }}</span>
                        <span class="text-gray-400 text-[11px]">(Main OAuth)</span>
                    </div>
                    <div class="flex items-center justify-between bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 rounded font-mono text-xs text-gray-800 dark:text-gray-200">
                        <span>{{ url('/discord-suite/linked-roles/callback') }}</span>
                        <span class="text-gray-400 text-[11px]">(Linked Roles)</span>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-2">
                    <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">4</span>
                    <h3 class="font-bold text-gray-900 dark:text-white">Configure Interactions Endpoint URL (Slash Commands)</h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 ml-9 mb-2">
                    In your Application's <strong>General Information</strong> tab:
                </p>
                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 ml-9 space-y-1 mb-3">
                    <li>Copy your <strong>Public Key</strong> into the field above and save.</li>
                    <li>In Discord's portal, set the <strong>Interactions Endpoint URL</strong> to:</li>
                </ul>
                <div class="ml-9 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 px-3 py-2 rounded font-mono text-xs text-primary font-semibold">
                    {{ url('/api/discord-suite/interactions') }}
                </div>
                <p class="text-xs text-gray-500 ml-9 mt-2">
                    <em>Discord will automatically send an Ed25519 signature test ping to verify this endpoint. Ensure you have saved your Public Key above first!</em>
                </p>
            </div>

            <!-- Step 5 -->
            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-2">
                    <span class="flex items-center justify-center size-6 rounded-full bg-primary text-white text-xs font-bold">5</span>
                    <h3 class="font-bold text-gray-900 dark:text-white">Bot Server Invite & Role Hierarchy</h3>
                </div>
                <div class="ml-9 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <p>
                        In Discord Developer Portal under <strong>OAuth2 -> URL Generator</strong>, select the <code>bot</code> and <code>applications.commands</code> scopes, and grant the following permissions:
                    </p>
                    <div class="inline-flex flex-wrap gap-1.5 py-1">
                        <span class="px-2 py-0.5 bg-neutral text-xs font-mono rounded">Manage Roles (0x10000000)</span>
                        <span class="px-2 py-0.5 bg-neutral text-xs font-mono rounded">Create Instant Invite</span>
                        <span class="px-2 py-0.5 bg-neutral text-xs font-mono rounded">Send Messages</span>
                        <span class="px-2 py-0.5 bg-neutral text-xs font-mono rounded">Embed Links</span>
                        <span class="px-2 py-0.5 bg-neutral text-xs font-mono rounded">Use Slash Commands</span>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-200 text-xs">
                        <strong>⚠️ CRITICAL ROLE HIERARCHY RULE:</strong> In your Discord server settings (<em>Server Settings -> Roles</em>), ensure the Bot's integrated role sits <strong>higher</strong> than any customer roles it manages. Discord forbids bots from assigning roles that sit above their own rank.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
