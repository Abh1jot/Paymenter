<div @if($clientApiAvailable && !$loadFailed) wire:poll.5s="pollResources" @endif class="space-y-6">
    @if($loadFailed)
        <div class="rounded-2xl p-6 bg-red-950/20 border border-red-500/30 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-red-500/10 flex items-center justify-center text-red-400 mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-red-300 mb-1">Server Unavailable</h3>
            <p class="text-sm text-neutral-400 max-w-md mx-auto">{{ $loadErrorMessage }}</p>
            <button wire:click="loadData" class="mt-4 px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-xs font-medium text-white transition-all">
                Retry Connection
            </button>
        </div>
    @else
        {{-- Flash / Status Notification Banner --}}
        @if($statusMessage)
            <div class="p-4 rounded-xl flex items-center justify-between transition-all {{ $statusType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/25 text-emerald-300' : 'bg-red-500/10 border border-red-500/25 text-red-300' }}">
                <div class="flex items-center gap-3">
                    @if($statusType === 'success')
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                    <span class="text-sm font-medium">{{ $statusMessage }}</span>
                </div>
                <button wire:click="$set('statusMessage', null)" class="text-neutral-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- Top Server Header Card --}}
        <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 md:p-6 backdrop-blur-xl shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-white tracking-tight">{{ $credentials['server_name'] ?? 'Pterodactyl Server' }}</h2>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-mono font-medium bg-neutral-800 border border-neutral-700 text-neutral-300">
                            {{ $credentials['identifier'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-neutral-400">
                        <span class="flex items-center gap-1.5 font-mono">
                            <svg class="w-3.5 h-3.5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                            {{ $credentials['connection'] ?? 'Pending IP' }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            {{ $credentials['node_name'] ?? 'Primary Node' }}
                        </span>
                        @if(!empty($resources['uptime']) && $resources['uptime'] !== 'Offline')
                            <span class="flex items-center gap-1.5 text-emerald-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Uptime: {{ $resources['uptime'] }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Status & Quick Action Buttons --}}
                <div class="flex items-center gap-2.5">
                    {{-- Status Badge --}}
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold
                        @if($currentState === 'running') bg-emerald-500/10 text-emerald-400 border-emerald-500/30
                        @elseif($currentState === 'starting') bg-amber-500/10 text-amber-400 border-amber-500/30
                        @elseif($currentState === 'stopping') bg-orange-500/10 text-orange-400 border-orange-500/30
                        @elseif($currentState === 'suspended') bg-purple-500/10 text-purple-400 border-purple-500/30
                        @elseif($currentState === 'installing') bg-blue-500/10 text-blue-400 border-blue-500/30
                        @else bg-red-500/10 text-red-400 border-red-500/30 @endif">
                        <span class="relative flex h-2 w-2">
                            @if(in_array($currentState, ['running', 'starting']))
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75
                                    {{ $currentState === 'running' ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            @endif
                            <span class="relative inline-flex rounded-full h-2 w-2
                                @if($currentState === 'running') bg-emerald-500
                                @elseif($currentState === 'starting') bg-amber-500
                                @elseif($currentState === 'stopping') bg-orange-500
                                @elseif($currentState === 'suspended') bg-purple-500
                                @elseif($currentState === 'installing') bg-blue-500
                                @else bg-red-500 @endif"></span>
                        </span>
                        <span class="capitalize">{{ $currentState }}</span>
                    </div>

                    @if($panelUrl)
                        <a href="{{ $panelUrl }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-xs font-medium text-white border border-neutral-700/80 transition-all">
                            <span>Open Panel</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    @endif

                    @if($ssoAvailable && $ssoUrl)
                        <a href="{{ $ssoUrl }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-medium text-white shadow-sm transition-all"
                           style="background-color: hsl(var(--color-primary, 221 83% 53%));">
                            <span>SSO Login</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Navigation Tabs --}}
            <div class="flex items-center gap-2 mt-6 pt-5 border-t border-neutral-800/80">
                <button wire:click="setTab('controls')"
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all {{ $activeTab === 'controls' ? 'bg-white/10 text-white shadow-inner' : 'text-neutral-400 hover:text-white hover:bg-neutral-800/50' }}">
                    Power & Performance
                </button>
                <button wire:click="setTab('credentials')"
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all {{ $activeTab === 'credentials' ? 'bg-white/10 text-white shadow-inner' : 'text-neutral-400 hover:text-white hover:bg-neutral-800/50' }}">
                    Access & Credentials
                </button>
            </div>
        </div>

        {{-- TAB 1: POWER CONTROLS & REAL-TIME PERFORMANCE --}}
        @if($activeTab === 'controls')
            <div class="space-y-6">
                {{-- Power Controls Bar --}}
                <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 md:p-6 backdrop-blur-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <h3 class="text-sm font-semibold text-white">Power Management</h3>
                        </div>
                        @if(!$clientApiAvailable)
                            <span class="text-xs text-amber-400/90 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                                Note: Client API key not configured. Controls redirected to panel.
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        {{-- Start Button --}}
                        <button wire:click="powerAction('start')"
                                wire:loading.attr="disabled"
                                @if(!$clientApiAvailable || $currentState === 'running') disabled @endif
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white transition-all
                                    {{ $currentState === 'running' || !$clientApiAvailable ? 'opacity-40 cursor-not-allowed bg-emerald-950/40 border border-emerald-900/50 text-neutral-400' : 'bg-emerald-600 hover:bg-emerald-500 active:scale-95 shadow-lg shadow-emerald-900/20' }}">
                            <svg wire:loading.remove wire:target="powerAction('start')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <svg wire:loading wire:target="powerAction('start')" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Start</span>
                        </button>

                        {{-- Restart Button --}}
                        <button wire:click="powerAction('restart')"
                                wire:loading.attr="disabled"
                                @if(!$clientApiAvailable) disabled @endif
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white transition-all
                                    {{ !$clientApiAvailable ? 'opacity-40 cursor-not-allowed bg-blue-950/40 border border-blue-900/50 text-neutral-400' : 'bg-blue-600 hover:bg-blue-500 active:scale-95 shadow-lg shadow-blue-900/20' }}">
                            <svg wire:loading.remove wire:target="powerAction('restart')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <svg wire:loading wire:target="powerAction('restart')" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Restart</span>
                        </button>

                        {{-- Stop Button --}}
                        <button wire:click="powerAction('stop')"
                                wire:loading.attr="disabled"
                                @if(!$clientApiAvailable || $currentState === 'offline') disabled @endif
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white transition-all
                                    {{ $currentState === 'offline' || !$clientApiAvailable ? 'opacity-40 cursor-not-allowed bg-amber-950/40 border border-amber-900/50 text-neutral-400' : 'bg-amber-600 hover:bg-amber-500 active:scale-95 shadow-lg shadow-amber-900/20' }}">
                            <svg wire:loading.remove wire:target="powerAction('stop')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                            </svg>
                            <svg wire:loading wire:target="powerAction('stop')" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Stop</span>
                        </button>

                        {{-- Kill Button --}}
                        <button wire:click="powerAction('kill')"
                                wire:loading.attr="disabled"
                                @if(!$clientApiAvailable || $currentState === 'offline') disabled @endif
                                onclick="return confirm('Force killing the server container may result in unsaved data. Are you sure?')"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white transition-all
                                    {{ $currentState === 'offline' || !$clientApiAvailable ? 'opacity-40 cursor-not-allowed bg-rose-950/40 border border-rose-900/50 text-neutral-400' : 'bg-rose-700 hover:bg-rose-600 active:scale-95 shadow-lg shadow-rose-950/20' }}">
                            <svg wire:loading.remove wire:target="powerAction('kill')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            <svg wire:loading wire:target="powerAction('kill')" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Kill</span>
                        </button>
                    </div>
                </div>

                {{-- Live Performance Telemetry Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- CPU Usage Card --}}
                    <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 backdrop-blur-xl">
                        <div class="flex items-center justify-between text-xs text-neutral-400 mb-2">
                            <span class="font-medium">CPU Utilization</span>
                            <span class="font-mono text-neutral-300">
                                @if($resources) {{ $resources['cpu_absolute'] }}% @else 0% @endif
                            </span>
                        </div>
                        <div class="text-2xl font-bold text-white tracking-tight mb-3">
                            @if($resources) {{ $resources['cpu_absolute'] }}% @else 0% @endif
                        </div>
                        <div class="w-full bg-neutral-800 rounded-full h-2 overflow-hidden">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min(100, $resources['cpu_percentage'] ?? 0) }}%;"></div>
                        </div>
                        <div class="mt-2 text-right text-[11px] text-neutral-500">
                            Limit: {{ $credentials['cpu_limit'] ? $credentials['cpu_limit'] . '%' : 'Unlimited' }}
                        </div>
                    </div>

                    {{-- Memory Usage Card --}}
                    <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 backdrop-blur-xl">
                        <div class="flex items-center justify-between text-xs text-neutral-400 mb-2">
                            <span class="font-medium">Memory (RAM)</span>
                            <span class="font-mono text-neutral-300">
                                @if($resources) {{ $resources['memory_percentage'] }}% @else 0% @endif
                            </span>
                        </div>
                        <div class="text-2xl font-bold text-white tracking-tight mb-3">
                            @if($resources) {{ $resources['memory_mb'] }} <span class="text-xs font-normal text-neutral-400">MB</span> @else 0 MB @endif
                        </div>
                        <div class="w-full bg-neutral-800 rounded-full h-2 overflow-hidden">
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min(100, $resources['memory_percentage'] ?? 0) }}%;"></div>
                        </div>
                        <div class="mt-2 text-right text-[11px] text-neutral-500">
                            Limit: {{ $credentials['memory_limit'] ? $credentials['memory_limit'] . ' MB' : 'Unlimited' }}
                        </div>
                    </div>

                    {{-- Disk Storage Card --}}
                    <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 backdrop-blur-xl">
                        <div class="flex items-center justify-between text-xs text-neutral-400 mb-2">
                            <span class="font-medium">Disk Storage</span>
                            <span class="font-mono text-neutral-300">
                                @if($resources) {{ $resources['disk_percentage'] }}% @else 0% @endif
                            </span>
                        </div>
                        <div class="text-2xl font-bold text-white tracking-tight mb-3">
                            @if($resources) {{ $resources['disk_mb'] }} <span class="text-xs font-normal text-neutral-400">MB</span> @else 0 MB @endif
                        </div>
                        <div class="w-full bg-neutral-800 rounded-full h-2 overflow-hidden">
                            <div class="bg-purple-500 h-2 rounded-full transition-all duration-500"
                                 style="width: {{ min(100, $resources['disk_percentage'] ?? 0) }}%;"></div>
                        </div>
                        <div class="mt-2 text-right text-[11px] text-neutral-500">
                            Limit: {{ $credentials['disk_limit'] ? $credentials['disk_limit'] . ' MB' : 'Unlimited' }}
                        </div>
                    </div>

                    {{-- Network I/O Card --}}
                    <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 backdrop-blur-xl">
                        <div class="flex items-center justify-between text-xs text-neutral-400 mb-2">
                            <span class="font-medium">Network I/O</span>
                            <span class="font-mono text-neutral-300">Traffic</span>
                        </div>
                        <div class="space-y-1.5 mt-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    Inbound (RX):
                                </span>
                                <span class="font-mono font-medium text-white">{{ $resources['network_rx'] ?? '0 B' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                    Outbound (TX):
                                </span>
                                <span class="font-mono font-medium text-white">{{ $resources['network_tx'] ?? '0 B' }}</span>
                            </div>
                        </div>
                        <div class="mt-3 text-right text-[11px] text-neutral-500">
                            Auto-refreshes every 5s
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB 2: ACCESS & CREDENTIALS --}}
        @if($activeTab === 'credentials')
            <div class="rounded-2xl bg-neutral-900/70 border border-neutral-800/80 p-5 md:p-6 backdrop-blur-xl space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-neutral-800/80">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-white">Panel Credentials & Access</h3>
                            <p class="text-xs text-neutral-400">Use these credentials to log in to your Pterodactyl server.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Server Identifier --}}
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Server Identifier</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 px-4 py-2.5 rounded-xl bg-neutral-950 border border-neutral-800 text-sm font-mono text-neutral-200 truncate">
                                {{ $credentials['identifier'] ?? 'N/A' }}
                            </div>
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['identifier'] ?? '' }}')"
                                    class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                Copy
                            </button>
                        </div>
                    </div>

                    {{-- Connection Address --}}
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Server Address (IP:Port)</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 px-4 py-2.5 rounded-xl bg-neutral-950 border border-neutral-800 text-sm font-mono text-neutral-200 truncate">
                                {{ $credentials['connection'] ?? 'Pending' }}
                            </div>
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['connection'] ?? '' }}')"
                                    class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                Copy
                            </button>
                        </div>
                    </div>

                    {{-- Username --}}
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Panel Username</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 px-4 py-2.5 rounded-xl bg-neutral-950 border border-neutral-800 text-sm font-mono text-neutral-200 truncate">
                                {{ $credentials['username'] ?? 'N/A' }}
                            </div>
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['username'] ?? '' }}')"
                                    class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                Copy
                            </button>
                        </div>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-xs font-medium text-neutral-400 mb-1.5">Account Email</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 px-4 py-2.5 rounded-xl bg-neutral-950 border border-neutral-800 text-sm font-mono text-neutral-200 truncate">
                                {{ $credentials['email'] ?? 'N/A' }}
                            </div>
                            <button onclick="navigator.clipboard.writeText('{{ $credentials['email'] ?? '' }}')"
                                    class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                Copy
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Password Row --}}
                <div class="pt-2">
                    <label class="block text-xs font-medium text-neutral-400 mb-1.5">Panel Password</label>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <div class="flex-1 px-4 py-2.5 rounded-xl bg-neutral-950 border border-neutral-800 text-sm font-mono text-neutral-200 truncate">
                            @if($credentials['password'])
                                @if($showPassword)
                                    {{ $credentials['password'] }}
                                @else
                                    ••••••••••••••••
                                @endif
                            @else
                                <span class="italic text-neutral-500">No password saved. Use the generate button below.</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($credentials['password'])
                                <button wire:click="togglePassword"
                                        class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                    {{ $showPassword ? 'Hide' : 'Show' }}
                                </button>
                                <button onclick="navigator.clipboard.writeText('{{ $credentials['password'] }}')"
                                        class="px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-neutral-800 hover:bg-neutral-700 text-white border border-neutral-700 transition-all">
                                    Copy
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Password Rotation Button --}}
                <div class="pt-2">
                    <button wire:click="generatePassword"
                            wire:loading.attr="disabled"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white shadow-lg transition-all"
                            style="background-color: hsl(var(--color-primary, 221 83% 53%));">
                        <svg wire:loading.class="animate-spin" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span wire:loading.remove wire:target="generatePassword">Generate & Synchronize New Password</span>
                        <span wire:loading wire:target="generatePassword">Updating Password on Pterodactyl...</span>
                    </button>
                    @if($passwordUpdated)
                        <p class="text-xs text-emerald-400 mt-2 text-center font-medium">
                            ✓ Password successfully updated on Pterodactyl. Please make sure to record it in a safe place.
                        </p>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
