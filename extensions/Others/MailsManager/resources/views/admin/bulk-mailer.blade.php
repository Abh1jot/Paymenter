<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Compose card ── --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="flex items-center gap-2">
                    <x-ri-mail-send-line class="w-5 h-5 text-primary-500" />
                    New Campaign
                </span>
            </x-slot>
            <x-slot name="description">Send bulk emails to your users via the queue worker</x-slot>
            <x-slot name="headerEnd">
                <x-filament::button
                    wire:click="togglePreview"
                    color="{{ $showPreview ? 'primary' : 'gray' }}"
                    size="sm"
                    icon="ri-eye-line"
                >
                    {{ $showPreview ? 'Hide Preview' : 'Preview Email' }}
                </x-filament::button>
            </x-slot>

            <div @class(['grid grid-cols-2 gap-6' => $showPreview])>

                {{-- LEFT: Form --}}
                <div class="space-y-5">

                    {{-- Campaign name --}}
                    <div>
                        <x-filament::input.wrapper label="Campaign Name" required>
                            <x-filament::input
                                type="text"
                                wire:model.live="campaignName"
                                placeholder="e.g. August Newsletter"
                            />
                        </x-filament::input.wrapper>
                        @error('campaignName')<p class="mt-1 text-xs text-danger-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Subject --}}
                    <div>
                        <x-filament::input.wrapper label="Subject" required>
                            <x-filament::input
                                type="text"
                                wire:model.live="subject"
                                placeholder="Email subject line…"
                            />
                        </x-filament::input.wrapper>
                        @error('subject')<p class="mt-1 text-xs text-danger-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm font-medium leading-6 text-gray-950 dark:text-white mb-1.5">
                            Email Body
                            <span class="font-normal text-gray-500 ml-1 text-xs">(HTML · personalise with @{{ $user->first_name }}, @{{ $user->email }})</span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.textarea
                                wire:model.live.debounce.300ms="body"
                                rows="12"
                                placeholder="Write your email here…"
                                class="font-mono text-xs"
                            />
                        </x-filament::input.wrapper>
                        @error('body')<p class="mt-1 text-xs text-danger-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Recipients --}}
                    <div>
                        <p class="text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2">
                            Recipients <span class="text-danger-500">*</span>
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ([
                                'all'    => ['label' => 'All Users',         'sub' => 'Every registered user',      'icon' => 'ri-group-line'],
                                'active' => ['label' => 'Active Customers',  'sub' => 'Users with active services', 'icon' => 'ri-shield-check-line'],
                            ] as $value => $opt)
                                <label @class([
                                    'flex cursor-pointer rounded-lg border p-4 transition-colors gap-3',
                                    'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-500' => $recipientType === $value,
                                    'border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 hover:bg-gray-50 dark:hover:bg-white/10' => $recipientType !== $value,
                                ])>
                                    <input type="radio" wire:model.live="recipientType" value="{{ $value }}" class="sr-only" />
                                    <x-dynamic-component :component="$opt['icon']" @class([
                                        'w-5 h-5 mt-0.5 flex-shrink-0',
                                        'text-primary-500' => $recipientType === $value,
                                        'text-gray-400'    => $recipientType !== $value,
                                    ]) />
                                    <div>
                                        <span @class([
                                            'block text-sm font-semibold',
                                            'text-primary-700 dark:text-primary-300' => $recipientType === $value,
                                            'text-gray-900 dark:text-white'          => $recipientType !== $value,
                                        ])>{{ $opt['label'] }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $opt['sub'] }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Recipient count badge --}}
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 px-4 py-3">
                        <span class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <x-ri-user-line class="w-4 h-4" />
                            Will send to
                        </span>
                        <span class="text-xl font-bold text-primary-600 dark:text-primary-400">
                            {{ number_format($this->recipientCount) }}
                            <span class="text-sm font-normal text-gray-500">users</span>
                        </span>
                    </div>

                    {{-- Send button / confirmation --}}
                    @if (!$confirmSend)
                        <x-filament::button
                            wire:click="prepareSend"
                            icon="ri-mail-send-line"
                            class="w-full justify-center"
                        >
                            Send Campaign
                        </x-filament::button>
                    @else
                        <x-filament::section color="warning">
                            <x-slot name="heading">
                                <span class="flex items-center gap-2">
                                    <x-ri-alert-line class="w-4 h-4" />
                                    Confirm Send
                                </span>
                            </x-slot>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                                This will queue <strong>{{ number_format($this->recipientCount) }} emails</strong>. This action cannot be undone.
                            </p>
                            <div class="flex gap-3">
                                <x-filament::button
                                    wire:click="sendCampaign"
                                    wire:loading.attr="disabled"
                                    color="warning"
                                    icon="ri-send-plane-line"
                                >
                                    <span wire:loading.remove wire:target="sendCampaign">Yes, Send Now</span>
                                    <span wire:loading wire:target="sendCampaign">Queuing…</span>
                                </x-filament::button>
                                <x-filament::button wire:click="cancelSend" color="gray">
                                    Cancel
                                </x-filament::button>
                            </div>
                        </x-filament::section>
                    @endif

                </div>

                {{-- RIGHT: Preview --}}
                @if ($showPreview)
                    <div>
                        <p class="text-sm font-medium leading-6 text-gray-950 dark:text-white mb-2 flex items-center gap-1.5">
                            <x-ri-eye-line class="w-4 h-4 text-gray-400" />
                            Email Preview
                        </p>
                        <iframe
                            srcdoc="{{ $this->previewHtml }}"
                            class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white"
                            style="min-height: 500px;"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                @endif

            </div>
        </x-filament::section>

        {{-- ── Campaign history ── --}}
        <x-filament::section>
            <x-slot name="heading">Campaign History</x-slot>

            @if ($this->campaigns->isEmpty())
                <div class="py-10 text-center">
                    <x-ri-mail-line class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No campaigns sent yet.</p>
                </div>
            @else
                <div class="-mx-6 -mb-6 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipients</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($this->campaigns as $campaign)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $campaign->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs mt-0.5">{{ $campaign->subject }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                                            @if ($campaign->recipient_type === 'active')
                                                <x-ri-shield-check-line class="w-3.5 h-3.5 text-success-500" /> Active
                                            @else
                                                <x-ri-group-line class="w-3.5 h-3.5 text-info-500" /> All users
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($campaign->total_count > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden" style="max-width:6rem">
                                                    <div class="h-full rounded-full {{ $campaign->status === 'done' ? 'bg-success-500' : ($campaign->status === 'failed' ? 'bg-danger-500' : 'bg-primary-500') }}"
                                                        style="width: {{ min(100, round($campaign->sent_count / $campaign->total_count * 100)) }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                    {{ number_format($campaign->sent_count) }}/{{ number_format($campaign->total_count) }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $badge = match($campaign->status) {
                                                'done'    => 'success',
                                                'sending' => 'warning',
                                                'failed'  => 'danger',
                                                default   => 'gray',
                                            };
                                        @endphp
                                        <x-filament::badge :color="$badge">
                                            {{ ucfirst($campaign->status) }}
                                        </x-filament::badge>
                                        @if ($campaign->status === 'failed' && $campaign->error_message)
                                            <p class="text-xs text-danger-500 mt-1 max-w-xs truncate">{{ Str::limit($campaign->error_message, 60) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ $campaign->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
