<div class="rounded-2xl border border-white/10 bg-[#090b10] shadow-xl p-5 md:p-6 space-y-6">
    @if($loadFailed)
        <div class="rounded-xl p-5 bg-rose-500/10 border border-rose-500/20 text-center">
            <div class="w-10 h-10 mx-auto rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400 mb-2">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="text-sm font-semibold text-rose-400 mb-1">Server Unavailable</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">{{ $loadErrorMessage }}</p>
            <button wire:click="loadData" class="mt-3 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-xs font-medium text-white transition-all">
                Retry Connection
            </button>
        </div>
    @else
        {{-- Header: Server Identity, Status Badge, and Open Panel Button --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-white/10">
            <div class="flex items-center gap-3.5">
                <div class="p-2.5 rounded-xl bg-white/[0.04] border border-white/10 text-slate-300 shrink-0">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-white tracking-tight">{{ $credentials['server_name'] ?? 'Pterodactyl Server' }}</h2>
                        @if(!empty($credentials['identifier']))
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['identifier'] }}'); window.Alpine?.store('notifications')?.addNotification([{message: 'Identifier copied!', type: 'success'}])"
                                    title="Click to copy identifier"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono font-medium bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-slate-300 transition-colors">
                                <span>{{ $credentials['identifier'] }}</span>
                                <svg class="size-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                    <p class="text-xs font-mono text-slate-400 mt-0.5">
                        {{ $credentials['node_name'] ?? 'Pterodactyl Node' }} &bull; Allocated: {{ $credentials['memory_formatted'] ?? 'N/A' }} RAM &bull; {{ $credentials['disk_formatted'] ?? 'N/A' }} Disk &bull; {{ $credentials['cpu_formatted'] ?? 'N/A' }} CPU
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- Status Badge --}}
                <div class="inline-flex items-center justify-center gap-2 h-9 px-3 rounded-lg border text-xs font-semibold font-mono leading-none shrink-0
                    @if($currentState === 'active' || $currentState === 'running') bg-emerald-500/10 text-emerald-400 border-emerald-500/20
                    @elseif($currentState === 'suspended') bg-amber-500/10 text-amber-400 border-amber-500/20
                    @elseif($currentState === 'installing') bg-blue-500/10 text-blue-400 border-blue-500/20
                    @else bg-white/5 text-slate-400 border-white/10 @endif">
                    <span class="size-2 rounded-full shrink-0 @if($currentState === 'active' || $currentState === 'running') bg-emerald-400 animate-pulse @elseif($currentState === 'suspended') bg-amber-400 @else bg-blue-400 @endif"></span>
                    <span class="capitalize leading-none">{{ $currentState }}</span>
                </div>

                {{-- Direct Link to Server in Panel --}}
                @if($panelUrl)
                    <a href="{{ $panelUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-xl bg-white hover:bg-slate-200 text-black text-xs font-bold leading-none shadow-sm transition-all shrink-0">
                        <span class="leading-none">Open Panel</span>
                        <svg class="size-3.5 shrink-0 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Clean Grid: Node & Account Details --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Node / Location --}}
            <div class="p-3.5 rounded-xl bg-[#06080c] border border-white/10 flex flex-col justify-between">
                <span class="text-[11px] font-mono font-medium text-slate-400 uppercase tracking-wider">Node / Location</span>
                <div class="mt-1.5">
                    <span class="text-sm font-semibold font-mono text-white truncate">{{ $credentials['node_name'] ?? 'Primary Node' }}</span>
                </div>
            </div>

            {{-- Panel Username --}}
            <div class="p-3.5 rounded-xl bg-[#06080c] border border-white/10 flex flex-col justify-between">
                <span class="text-[11px] font-mono font-medium text-slate-400 uppercase tracking-wider">Panel Username</span>
                <div class="flex items-center justify-between gap-2 mt-1.5">
                    <span class="font-mono text-sm font-semibold text-white truncate">{{ $credentials['username'] ?? 'User' }}</span>
                    <button onclick="navigator.clipboard.writeText('{{ $credentials['username'] ?? '' }}'); window.Alpine?.store('notifications')?.addNotification([{message: 'Username copied!', type: 'success'}])"
                            title="Copy Username" class="text-slate-400 hover:text-white p-1 transition-colors">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Account Email --}}
            <div class="p-3.5 rounded-xl bg-[#06080c] border border-white/10 flex flex-col justify-between">
                <span class="text-[11px] font-mono font-medium text-slate-400 uppercase tracking-wider">Account Email</span>
                <div class="flex items-center justify-between gap-2 mt-1.5">
                    <span class="font-mono text-sm font-semibold text-white truncate">{{ $credentials['email'] ?? 'N/A' }}</span>
                    <button onclick="navigator.clipboard.writeText('{{ $credentials['email'] ?? '' }}'); window.Alpine?.store('notifications')?.addNotification([{message: 'Email copied!', type: 'success'}])"
                            title="Copy Email" class="text-slate-400 hover:text-white p-1 transition-colors">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Panel Password Notice & Direct Reset Link --}}
        <div class="rounded-xl p-4 bg-[#06080c] border border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-white/[0.04] border border-white/10 text-slate-300 mt-0.5 shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-white">Panel Account & Password</h4>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Check your email to set up your password, or reset your password directly from the panel.
                    </p>

                    @if(!empty($credentials['password']))
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-xs text-slate-400 font-medium">Initial Password:</span>
                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-white/[0.06] border border-white/10 text-slate-200">
                                {{ $showPassword ? $credentials['password'] : '••••••••••••••••' }}
                            </span>
                            <button wire:click="togglePassword" class="text-xs text-slate-300 hover:text-white underline font-medium">
                                {{ $showPassword ? 'Hide' : 'Show' }}
                            </button>
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['password'] }}'); window.Alpine?.store('notifications')?.addNotification([{message: 'Password copied!', type: 'success'}])"
                                    class="text-xs text-slate-400 hover:text-white font-medium">
                                Copy
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-start md:self-auto shrink-0">
                @if($resetPasswordUrl)
                    <a href="{{ $resetPasswordUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl text-xs font-semibold leading-none bg-white/[0.04] hover:bg-white/[0.08] text-slate-200 hover:text-white border border-white/10 shadow-sm transition-all shrink-0">
                        <span class="leading-none">Reset Password</span>
                        <svg class="size-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
                @if($panelUrl)
                    <a href="{{ $panelUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl text-xs font-bold leading-none bg-white hover:bg-slate-200 text-black shadow-sm transition-all shrink-0">
                        <span class="leading-none">Go to Server</span>
                        <svg class="size-3.5 shrink-0 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
