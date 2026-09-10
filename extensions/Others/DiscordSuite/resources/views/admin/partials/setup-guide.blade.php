<div class="space-y-6">
    <!-- Intro Banner -->
    <div class="flex items-center gap-4 p-5 rounded-xl border border-white/10 bg-white/5 backdrop-blur-sm">
        <div class="p-3 bg-[#5865F2]/20 text-[#5865F2] rounded-xl border border-[#5865F2]/30 flex-shrink-0">
            <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24">
                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-base font-bold text-white">Discord Application Setup Guide</h3>
            <p class="text-xs text-gray-400 mt-0.5">Follow these 5 simple steps in the Discord Developer Portal to configure your bot, OAuth2 linking, and slash commands.</p>
        </div>
    </div>

    <!-- Step 1 -->
    <div class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm space-y-2">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center size-6 rounded-full bg-primary-500/20 border border-primary-500/30 text-primary-400 text-xs font-bold">1</span>
            <h4 class="font-bold text-white text-sm">Create Application in Discord Developer Portal</h4>
        </div>
        <p class="text-xs text-gray-300 ml-9 leading-relaxed">
            Head to the <a href="https://discord.com/developers/applications" target="_blank" class="text-primary-400 font-semibold underline hover:text-primary-300">Discord Developer Portal</a>, click <strong>"New Application"</strong> in the top right, and name it your company brand (e.g. <em>Azion Cloud Bot</em>).
        </p>
    </div>

    <!-- Step 2 -->
    <div class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm space-y-2.5">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center size-6 rounded-full bg-primary-500/20 border border-primary-500/30 text-primary-400 text-xs font-bold">2</span>
            <h4 class="font-bold text-white text-sm">Enable Privileged Gateway Intents & Copy Bot Token</h4>
        </div>
        <div class="ml-9 space-y-2 text-xs text-gray-300 leading-relaxed">
            <p>In your Discord application, navigate to the <strong>Bot</strong> tab on the left menu:</p>
            <ul class="list-disc list-inside space-y-1.5 text-gray-400">
                <li>Click <strong>"Reset Token"</strong> to generate your secret <strong>Bot Token</strong>, then paste it in the <em>Bot Credentials</em> tab above.</li>
                <li>Scroll down to <strong>Privileged Gateway Intents</strong> and turn on <strong class="text-amber-400">SERVER MEMBERS INTENT</strong> (Required for reading members, checking server presence, and managing roles).</li>
            </ul>
        </div>
    </div>

    <!-- Step 3 -->
    <div class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center size-6 rounded-full bg-primary-500/20 border border-primary-500/30 text-primary-400 text-xs font-bold">3</span>
            <h4 class="font-bold text-white text-sm">Configure OAuth2 Redirect URLs</h4>
        </div>
        <div class="ml-9 space-y-2 text-xs text-gray-300 leading-relaxed">
            <p>Go to <strong>OAuth2 -> General</strong>, copy your <strong>Client ID</strong> and <strong>Client Secret</strong>, then add both of the following Redirect URLs:</p>
            <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 font-mono text-xs text-primary-300">
                    <span>{{ url('/discord-suite/oauth/callback') }}</span>
                    <span class="text-gray-500 text-[11px] font-sans">(Main Account Linking)</span>
                </div>
                <div class="flex items-center justify-between rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 font-mono text-xs text-primary-300">
                    <span>{{ url('/discord-suite/linked-roles/callback') }}</span>
                    <span class="text-gray-500 text-[11px] font-sans">(Discord Linked Roles)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 4 -->
    <div class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center size-6 rounded-full bg-primary-500/20 border border-primary-500/30 text-primary-400 text-xs font-bold">4</span>
            <h4 class="font-bold text-white text-sm">Configure Interactions Endpoint URL (Slash Commands)</h4>
        </div>
        <div class="ml-9 space-y-2 text-xs text-gray-300 leading-relaxed">
            <p>In <strong>General Information</strong>, copy your <strong>Public Key</strong> into the <em>Bot Credentials</em> tab and save. Then set your <strong>Interactions Endpoint URL</strong> to:</p>
            <div class="rounded-lg border border-white/10 bg-black/40 px-3.5 py-2 font-mono text-xs text-primary-300">
                {{ url('/api/discord-suite/interactions') }}
            </div>
            <p class="text-[11px] text-gray-400">
                <em>Discord will instantly test this URL using an Ed25519 signature ping. Save your Public Key first so the cryptographic verification succeeds!</em>
            </p>
        </div>
    </div>

    <!-- Step 5 -->
    <div class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center size-6 rounded-full bg-primary-500/20 border border-primary-500/30 text-primary-400 text-xs font-bold">5</span>
            <h4 class="font-bold text-white text-sm">Invite Bot & Set Role Hierarchy</h4>
        </div>
        <div class="ml-9 space-y-3 text-xs text-gray-300 leading-relaxed">
            <p>Generate an invite link under <strong>OAuth2 -> URL Generator</strong> with scopes <code>bot</code> and <code>applications.commands</code>, granting these permissions:</p>
            <div class="flex flex-wrap gap-1.5">
                <span class="px-2.5 py-1 rounded bg-white/10 border border-white/10 text-xs font-mono text-gray-200">Manage Roles</span>
                <span class="px-2.5 py-1 rounded bg-white/10 border border-white/10 text-xs font-mono text-gray-200">Create Instant Invite</span>
                <span class="px-2.5 py-1 rounded bg-white/10 border border-white/10 text-xs font-mono text-gray-200">Send Messages</span>
                <span class="px-2.5 py-1 rounded bg-white/10 border border-white/10 text-xs font-mono text-gray-200">Embed Links</span>
                <span class="px-2.5 py-1 rounded bg-white/10 border border-white/10 text-xs font-mono text-gray-200">Use Slash Commands</span>
            </div>
            <div class="p-3.5 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-200 text-xs leading-relaxed">
                <strong>⚠️ CRITICAL ROLE HIERARCHY RULE:</strong> In your Discord server's <em>Server Settings -> Roles</em>, drag the Bot's managed role <strong>ABOVE</strong> all customer roles it will assign. Discord blocks bots from assigning any role higher than their own rank.
            </div>
        </div>
    </div>
</div>
