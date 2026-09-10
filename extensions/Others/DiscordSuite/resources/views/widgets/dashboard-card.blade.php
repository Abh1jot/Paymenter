<div class="bg-background-secondary border border-neutral rounded-xl p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="bg-[#5865F2]/10 text-[#5865F2] border border-[#5865F2]/20 p-2.5 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-lg text-base">{{ $isLinked ? 'Discord Integration' : 'Link Discord' }}</h3>
                <p class="text-xs text-base/60">Community roles, notifications & bot access</p>
            </div>
        </div>

        @if($isLinked)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Connected
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-500 border border-amber-500/20">
                <span class="size-1.5 rounded-full bg-amber-500"></span>
                Not Linked
            </span>
        @endif
    </div>

    @if($isLinked && $account)
        <div class="flex items-center gap-4 bg-background border border-neutral p-4 rounded-lg mb-4">
            <img src="{{ $account->avatar_url }}" alt="Discord Avatar" class="size-12 rounded-full border border-neutral object-cover shadow-inner" />
            <div class="flex-grow">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-base">{{ $account->display_name }}</span>
                    <span class="text-xs text-base/60 bg-neutral/50 px-1.5 py-0.5 rounded">{{ $account->formatted_tag }}</span>
                </div>
                <div class="text-xs text-base/60 mt-1 flex items-center gap-2">
                    <span>ID: <code class="text-[11px] font-mono">{{ $account->discord_user_id }}</code></span>
                    @if($account->last_synced_at)
                        <span>• Synced {{ $account->last_synced_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
            <button wire:click="syncNow" wire:loading.attr="disabled" class="text-xs bg-background-secondary hover:bg-neutral border border-neutral px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition font-medium">
                <svg wire:loading.class="animate-spin" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                </svg>
                Sync Roles
            </button>
        </div>

        @if($expiringServices->isNotEmpty())
            <div class="mb-4 p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg">
                <div class="flex items-center justify-between text-xs font-medium text-amber-600 dark:text-amber-400 mb-1">
                    <span>⚠️ Service Nearing Expiration</span>
                    <span>{{ $expiringServices->count() }} service(s)</span>
                </div>
                <div class="text-xs text-base/80">
                    Your services will automatically renew via Discord alerts. Keep your account active to retain roles!
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 pt-2">
            <a href="{{ route('discord-suite.account.settings') }}" class="text-xs text-primary hover:underline font-medium flex items-center gap-1">
                Manage Discord Settings & Servers
                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <form action="{{ route('discord-suite.unlink') }}" method="POST" onsubmit="return confirm('Are you sure you want to unlink your Discord account? You will lose server customer roles.')">
                @csrf
                <button type="submit" class="text-xs text-red-500 hover:text-red-600 hover:underline">
                    Unlink
                </button>
            </form>
        </div>
    @else
        <div class="space-y-4">
            <p class="text-sm text-base/70">
                Link your Discord account to automatically receive customer roles in our Discord servers, direct message renewal reminders, and use our bot slash commands.
            </p>

            <div class="grid grid-cols-2 gap-2 text-xs text-base/80 mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-500">✓</span> Automated VIP & Customer Roles
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-500">✓</span> Instant Renewal & Expiry DMs
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-500">✓</span> Bot Slash Commands (/profile, /renew)
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-emerald-500">✓</span> Discord Linked Roles Support
                </div>
            </div>

            <a href="{{ route('discord-suite.oauth.redirect') }}" class="w-full bg-[#5865F2] hover:bg-[#4752C4] text-white font-medium py-2.5 px-4 rounded-lg flex items-center justify-center gap-2 transition shadow-sm">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
                Connect & Authorize Discord
            </a>
        </div>
    @endif
</div>
