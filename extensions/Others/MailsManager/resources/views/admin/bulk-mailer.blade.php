<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Compose card ── --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <x-ri-mail-send-line class="w-5 h-5 text-primary-500" />
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">New Campaign</h2>
                </div>
                <button
                    wire:click="togglePreview"
                    class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors focus:outline-none
                        {{ $showPreview
                            ? 'bg-primary-600 border-primary-600 text-white hover:bg-primary-700'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                        }}"
                >
                    <span class="flex items-center gap-1.5">
                        <x-ri-eye-line class="w-4 h-4" />
                        {{ $showPreview ? 'Hide Preview' : 'Preview Email' }}
                    </span>
                </button>
            </div>

            <div class="{{ $showPreview ? 'grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700' : '' }}">

                {{-- LEFT: Form --}}
                <div class="p-5 space-y-5">

                    {{-- Campaign name --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Campaign Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model.live="campaignName"
                            placeholder="e.g. August Newsletter"
                            class="block w-full p-2.5 text-sm border-gray-300 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                        />
                        @error('campaignName')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Subject --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Subject <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model.live="subject"
                            placeholder="Email subject line..."
                            class="block w-full p-2.5 text-sm border-gray-300 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                        />
                        @error('subject')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Email Body <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-500 ml-1">(HTML supported · use @{{ $user->first_name }} to personalise)</span>
                        </label>
                        <textarea
                            wire:model.live.debounce.300ms="body"
                            rows="12"
                            placeholder="Write your email here..."
                            class="block w-full p-2.5 text-sm border-gray-300 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500 font-mono resize-y"
                        ></textarea>
                        @error('body')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Recipients --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recipients <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ([
                                'all'    => ['label' => 'All Users',          'sub' => 'Every registered user',      'icon' => 'ri-group-line'],
                                'active' => ['label' => 'Active Customers',   'sub' => 'Users with active services', 'icon' => 'ri-shield-check-line'],
                            ] as $value => $opt)
                                <label class="flex cursor-pointer rounded-lg border p-4 transition-colors
                                    {{ $recipientType === $value
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700'
                                    }}">
                                    <input type="radio" wire:model.live="recipientType" value="{{ $value }}" class="sr-only" />
                                    <div class="flex items-start gap-3">
                                        <x-dynamic-component :component="$opt['icon']" class="w-5 h-5 mt-0.5 {{ $recipientType === $value ? 'text-primary-500' : 'text-gray-400' }}" />
                                        <div>
                                            <span class="block text-sm font-medium {{ $recipientType === $value ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-white' }}">{{ $opt['label'] }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $opt['sub'] }}</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Recipient count --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <x-ri-user-line class="w-4 h-4 text-gray-400" />
                            Will send to
                        </div>
                        <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                            {{ number_format($this->recipientCount) }} users
                        </span>
                    </div>

                    {{-- Send / Confirm --}}
                    @if (!$confirmSend)
                        <button
                            wire:click="prepareSend"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors focus:outline-none"
                        >
                            <x-ri-mail-send-line class="w-4 h-4" />
                            Send Campaign
                        </button>
                    @else
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg space-y-3">
                            <div class="flex items-start gap-2">
                                <x-ri-alert-line class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Confirm Send</p>
                                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-0.5">
                                        This will queue <strong>{{ number_format($this->recipientCount) }} emails</strong>. This cannot be undone.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="sendCampaign" wire:loading.attr="disabled" class="flex-1 px-4 py-2 text-sm font-semibold bg-amber-600 text-white rounded-lg hover:bg-amber-500 transition-colors">
                                    <span wire:loading.remove wire:target="sendCampaign">Yes, Send Now</span>
                                    <span wire:loading wire:target="sendCampaign">Queuing...</span>
                                </button>
                                <button wire:click="cancelSend" class="flex-1 px-4 py-2 text-sm font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- RIGHT: Preview --}}
                @if ($showPreview)
                    <div class="p-4">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1">
                            <x-ri-eye-line class="w-3.5 h-3.5" />
                            Email Preview
                        </p>
                        <iframe
                            srcdoc="{{ $this->previewHtml }}"
                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
                            style="min-height: 480px;"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                @endif

            </div>
        </div>

        {{-- ── Campaign history ── --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Campaign History</h2>
            </div>

            @if ($this->campaigns->isEmpty())
                <div class="px-6 py-12 text-center">
                    <x-ri-mail-line class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No campaigns sent yet.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipients</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($this->campaigns as $campaign)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $campaign->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $campaign->subject }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                            @if ($campaign->recipient_type === 'active')
                                                <x-ri-shield-check-line class="w-3.5 h-3.5 text-green-500" /> Active
                                            @else
                                                <x-ri-group-line class="w-3.5 h-3.5 text-blue-500" /> All users
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($campaign->total_count > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden max-w-24">
                                                    <div class="h-full rounded-full {{ $campaign->status === 'done' ? 'bg-green-500' : ($campaign->status === 'failed' ? 'bg-red-500' : 'bg-primary-500') }}"
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
                                    <td class="px-5 py-4">
                                        @php
                                            $badgeClass = match($campaign->status) {
                                                'done'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'sending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                'failed'  => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                            {{ ucfirst($campaign->status) }}
                                        </span>
                                        @if ($campaign->status === 'failed' && $campaign->error_message)
                                            <p class="text-xs text-red-500 mt-1 max-w-xs truncate" title="{{ $campaign->error_message }}">
                                                {{ Str::limit($campaign->error_message, 60) }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ $campaign->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
