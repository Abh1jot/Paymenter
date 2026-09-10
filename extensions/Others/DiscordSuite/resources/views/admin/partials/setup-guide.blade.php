<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    <!-- Intro Banner -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="padding: 0.75rem; background: rgba(88, 101, 242, 0.15); color: #5865F2; border-radius: 0.75rem; border: 1px solid rgba(88, 101, 242, 0.3); flex-shrink: 0;">
                <svg style="width: 1.75rem; height: 1.75rem; fill: currentColor;" viewBox="0 0 24 24">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.929 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.894a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
            </div>
            <div>
                <h3 style="font-size: 1rem; font-weight: 700; color: #f3f4f6;">Discord Application Setup Guide</h3>
                <p style="font-size: 0.8125rem; color: #9ca3af; margin-top: 0.25rem;">Follow these 5 simple steps in the Discord Developer Portal to configure your bot, OAuth2 linking, and slash commands.</p>
            </div>
        </div>
    </x-filament::section>

    <!-- Step 1 -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 700;">1</span>
            <h4 style="font-weight: 700; color: #f3f4f6; font-size: 0.875rem;">Create Application in Discord Developer Portal</h4>
        </div>
        <p style="font-size: 0.8125rem; color: #d1d5db; line-height: 1.6; padding-left: 2.25rem;">
            Head to the <a href="https://discord.com/developers/applications" target="_blank" style="color: #60a5fa; font-weight: 600; text-decoration: underline;">Discord Developer Portal</a>, click <strong>"New Application"</strong> in the top right, and name it your company brand (e.g. <em>Azion Cloud Bot</em>).
        </p>
    </x-filament::section>

    <!-- Step 2 -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 700;">2</span>
            <h4 style="font-weight: 700; color: #f3f4f6; font-size: 0.875rem;">Enable Privileged Gateway Intents & Copy Bot Token</h4>
        </div>
        <div style="padding-left: 2.25rem; font-size: 0.8125rem; color: #d1d5db; line-height: 1.6;">
            <p>In your Discord application, navigate to the <strong>Bot</strong> tab on the left menu:</p>
            <ul style="list-style: disc; padding-left: 1.25rem; margin-top: 0.35rem; color: #9ca3af; display: flex; flex-direction: column; gap: 0.25rem;">
                <li>Click <strong>"Reset Token"</strong> to generate your secret <strong>Bot Token</strong>, then paste it in the <em>Bot Credentials</em> tab above.</li>
                <li>Scroll down to <strong>Privileged Gateway Intents</strong> and turn on <strong style="color: #fbbf24;">SERVER MEMBERS INTENT</strong>.</li>
            </ul>
        </div>
    </x-filament::section>

    <!-- Step 3 -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 700;">3</span>
            <h4 style="font-weight: 700; color: #f3f4f6; font-size: 0.875rem;">Configure OAuth2 Redirect URLs</h4>
        </div>
        <div style="padding-left: 2.25rem; font-size: 0.8125rem; color: #d1d5db; line-height: 1.6;">
            <p>Go to <strong>OAuth2 -> General</strong>, copy your <strong>Client ID</strong> and <strong>Client Secret</strong>, then add both Redirect URLs:</p>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); padding: 0.5rem 0.75rem; font-family: monospace; font-size: 0.75rem; color: #93c5fd;">
                    <span>{{ url('/discord-suite/oauth/callback') }}</span>
                    <span style="color: #9ca3af; font-family: sans-serif; font-size: 0.6875rem;">(Main Account Linking)</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); padding: 0.5rem 0.75rem; font-family: monospace; font-size: 0.75rem; color: #93c5fd;">
                    <span>{{ url('/discord-suite/linked-roles/callback') }}</span>
                    <span style="color: #9ca3af; font-family: sans-serif; font-size: 0.6875rem;">(Discord Linked Roles)</span>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Step 4 -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 700;">4</span>
            <h4 style="font-weight: 700; color: #f3f4f6; font-size: 0.875rem;">Configure Interactions Endpoint URL (Slash Commands)</h4>
        </div>
        <div style="padding-left: 2.25rem; font-size: 0.8125rem; color: #d1d5db; line-height: 1.6;">
            <p>In <strong>General Information</strong>, copy your <strong>Public Key</strong> into the <em>Bot Credentials</em> tab and save. Then set your <strong>Interactions Endpoint URL</strong> to:</p>
            <div style="border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); padding: 0.5rem 0.75rem; font-family: monospace; font-size: 0.75rem; color: #93c5fd; margin-top: 0.5rem;">
                {{ url('/api/discord-suite/interactions') }}
            </div>
        </div>
    </x-filament::section>

    <!-- Step 5 -->
    <x-filament::section>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 700;">5</span>
            <h4 style="font-weight: 700; color: #f3f4f6; font-size: 0.875rem;">Invite Bot & Set Role Hierarchy</h4>
        </div>
        <div style="padding-left: 2.25rem; font-size: 0.8125rem; color: #d1d5db; line-height: 1.6;">
            <p>Generate an invite link under <strong>OAuth2 -> URL Generator</strong> with scopes <code>bot</code> and <code>applications.commands</code>, granting permissions:</p>
            <div style="display: flex; flex-wrap: wrap; gap: 0.375rem; margin: 0.5rem 0;">
                <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(255,255,255,0.08); font-family: monospace; font-size: 0.75rem; color: #e5e7eb;">Manage Roles</span>
                <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(255,255,255,0.08); font-family: monospace; font-size: 0.75rem; color: #e5e7eb;">Create Instant Invite</span>
                <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(255,255,255,0.08); font-family: monospace; font-size: 0.75rem; color: #e5e7eb;">Send Messages</span>
                <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(255,255,255,0.08); font-family: monospace; font-size: 0.75rem; color: #e5e7eb;">Embed Links</span>
                <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(255,255,255,0.08); font-family: monospace; font-size: 0.75rem; color: #e5e7eb;">Use Slash Commands</span>
            </div>
            <div style="padding: 0.75rem; border-radius: 0.5rem; border: 1px solid rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.1); color: #fde68a; font-size: 0.75rem; line-height: 1.5; margin-top: 0.5rem;">
                <strong>⚠️ CRITICAL ROLE HIERARCHY RULE:</strong> In Discord's <em>Server Settings -> Roles</em>, drag the Bot's managed role <strong>ABOVE</strong> all customer roles it will assign.
            </div>
        </div>
    </x-filament::section>
</div>
