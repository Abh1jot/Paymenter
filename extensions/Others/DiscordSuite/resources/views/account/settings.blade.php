<div class="container mt-14">
    <x-navigation.breadcrumb />

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-base">{{ $isLinked ? 'Discord Account' : 'Link Discord Account' }}</h1>
            <p class="text-sm text-base/60 mt-1">Connect your Discord profile, synchronize customer roles, and access community servers</p>
        </div>
        @if($isLinked)
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Connected
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                <span class="size-2 rounded-full bg-amber-500"></span>
                Not Connected
            </span>
        @endif
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm flex items-center gap-2">
            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-sm flex items-center gap-2">
            <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($isLinked && $account)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Profile Overview Card -->
            <div class="bg-background-secondary border border-neutral rounded-xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <img src="{{ $account->avatar_url }}" alt="Discord Avatar" class="size-16 rounded-full border-2 border-primary object-cover shadow" />
                        <div>
                            <h2 class="text-lg font-bold text-base">{{ $account->display_name }}</h2>
                            <p class="text-sm text-base/60">{{ $account->formatted_tag }}</p>
                            <p class="text-xs font-mono text-base/40 mt-0.5">ID: {{ $account->discord_user_id }}</p>
                        </div>
                    </div>
                    <div class="text-xs text-base/70 space-y-2 py-3 border-t border-neutral">
                        <div class="flex justify-between">
                            <span>Connected:</span>
                            <span class="font-medium text-base">{{ $account->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Last Role Sync:</span>
                            <span class="font-medium text-base">{{ $account->last_synced_at?->diffForHumans() ?? 'Pending' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Linked Roles Synced:</span>
                            <span class="font-medium text-base">{{ $account->linked_roles_synced_at?->diffForHumans() ?? 'Pending' }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-neutral flex flex-col gap-2">
                    <div class="flex gap-2">
                        <button wire:click="syncRoles" wire:loading.attr="disabled" class="flex-1 bg-primary hover:bg-primary/90 text-white text-xs font-semibold py-2 px-3 rounded-lg transition text-center flex items-center justify-center gap-1">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                            Sync Roles
                        </button>
                        <button wire:click="syncLinkedRoles" wire:loading.attr="disabled" class="bg-background-secondary hover:bg-neutral border border-neutral text-xs font-semibold py-2 px-3 rounded-lg transition text-center">
                            Update Linked Roles
                        </button>
                    </div>
                    <a href="{{ route('discord-suite.oauth.redirect') }}" class="w-full bg-[#5865F2]/10 hover:bg-[#5865F2]/20 text-[#5865F2] border border-[#5865F2]/20 text-xs font-semibold py-2 px-3 rounded-lg transition text-center flex items-center justify-center gap-1.5">
                        <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028z"/></svg>
                        Re-authorize / Switch Account
                    </a>
                </div>
            </div>

            <!-- Connected Discord Servers -->
            <div class="bg-background-secondary border border-neutral rounded-xl p-6 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="font-bold text-base text-lg">Community Discord Servers</h2>
                        <p class="text-xs text-base/60">Join our servers where your customer and VIP roles are automatically assigned</p>
                    </div>
                    <button wire:click="rejoinServers" class="text-xs text-primary hover:underline font-medium flex items-center gap-1">
                        Re-join Configured Servers
                    </button>
                </div>

                @if($guilds->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($guilds as $guild)
                            <div class="bg-background border border-neutral p-4 rounded-lg flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($guild->icon_url)
                                        <img src="{{ $guild->icon_url }}" class="size-10 rounded-full border border-neutral" alt="{{ $guild->name }}" />
                                    @else
                                        <div class="size-10 rounded-full bg-neutral/50 flex items-center justify-center font-bold text-xs">
                                            {{ substr($guild->name, 0, 2) }}
                                        </div>
                                    @endif
                                    <div>
                                        <h3 class="font-semibold text-sm text-base">{{ $guild->name }}</h3>
                                        <span class="text-xs text-emerald-500 font-medium">Role Sync Active</span>
                                    </div>
                                </div>
                                @if($guild->invite_url)
                                    <a href="{{ $guild->invite_url }}" target="_blank" class="text-xs bg-neutral/60 hover:bg-neutral px-3 py-1.5 rounded font-medium transition">
                                        Join Server
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-base/60">No community servers configured yet.</p>
                @endif

                <div class="mt-6 p-4 bg-background border border-neutral rounded-lg flex items-center justify-between">
                    <div>
                        <h4 class="font-semibold text-sm text-base">Disconnect Discord Account</h4>
                        <p class="text-xs text-base/60">Remove connection and strip managed Discord roles.</p>
                    </div>
                    <form action="{{ route('discord-suite.unlink') }}" method="POST" onsubmit="return confirm('Are you sure you want to disconnect Discord?')">
                        @csrf
                        <button type="submit" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/30 text-xs font-semibold py-1.5 px-3 rounded-lg transition">
                            Disconnect Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <!-- Connect Prompt -->
        <div class="bg-background-secondary border border-neutral rounded-xl p-8 text-center max-w-xl mx-auto shadow-sm my-10">
            <div class="size-16 bg-[#5865F2]/10 text-[#5865F2] border border-[#5865F2]/20 rounded-2xl mx-auto flex items-center justify-center mb-4">
                <svg class="size-8 fill-current" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-base mb-2">Connect Your Discord Account</h2>
            <p class="text-sm text-base/60 mb-6">
                Link Discord to unlock customer roles, service expiration alerts, bot slash command controls, and official Discord Linked Roles verification.
            </p>
            <a href="{{ route('discord-suite.oauth.redirect') }}" class="inline-flex items-center justify-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white font-semibold py-3 px-8 rounded-xl transition shadow-md hover:shadow-lg">
                <svg class="size-5 fill-current" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
                Connect & Authorize Discord
            </a>
        </div>
    @endif
</div>
