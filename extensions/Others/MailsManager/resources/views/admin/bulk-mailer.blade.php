<x-filament-panels::page>
    <div class="mailsmanager-bulk-mailer space-y-6">

        {{-- ── Page header ── --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Bulk Email Campaigns</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Compose and send bulk emails to your users. Emails are sent via the Laravel queue worker.
            </p>
        </div>

        {{-- ── Compose card ── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-ri-mail-send-line class="w-5 h-5 text-primary-500" />
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">New Campaign</h2>
                </div>
                <button
                    wire:click="togglePreview"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                >
                    <x-ri-eye-line class="w-4 h-4" />
                    {{ $showPreview ? 'Hide Preview' : 'Preview' }}
                </button>
            </div>

            <div class="{{ $showPreview ? 'grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700' : '' }}">

                {{-- LEFT: Form --}}
                <div class="p-6 space-y-5">

                    {{-- Campaign name --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Campaign Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model.live="campaignName"
                            placeholder="e.g. August Newsletter"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                        @error('subject')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Body --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Email Body <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-500 ml-1">(HTML supported)</span>
                        </label>
                        <textarea
                            wire:model.live.debounce.300ms="body"
                            rows="12"
                            placeholder="Write your email here. You can use HTML for formatting.&#10;&#10;Personalisation variables:&#10;@{{ $user->first_name }}, @{{ $user->last_name }}, @{{ $user->email }}"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono resize-y"
                        ></textarea>
                        @error('body')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Recipients --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Recipients <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative flex cursor-pointer rounded-lg border p-4 focus-within:ring-2 focus-within:ring-primary-500 {{ $recipientType === 'all' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <input
                                    type="radio"
                                    wire:model.live="recipientType"
                                    value="all"
                                    class="sr-only"
                                />
                                <div class="flex items-start gap-3">
                                    <x-ri-group-line class="w-5 h-5 mt-0.5 {{ $recipientType === 'all' ? 'text-primary-500' : 'text-gray-400' }}" />
                                    <div>
                                        <span class="block text-sm font-medium {{ $recipientType === 'all' ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-white' }}">All Users</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Every registered user</span>
                                    </div>
                                </div>
                            </label>
                            <label class="relative flex cursor-pointer rounded-lg border p-4 focus-within:ring-2 focus-within:ring-primary-500 {{ $recipientType === 'active' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800' }}">
                                <input
                                    type="radio"
                                    wire:model.live="recipientType"
                                    value="active"
                                    class="sr-only"
                                />
                                <div class="flex items-start gap-3">
                                    <x-ri-shield-check-line class="w-5 h-5 mt-0.5 {{ $recipientType === 'active' ? 'text-primary-500' : 'text-gray-400' }}" />
                                    <div>
                                        <span class="block text-sm font-medium {{ $recipientType === 'active' ? 'text-primary-700 dark:text-primary-300' : 'text-gray-900 dark:text-white' }}">Active Customers</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Users with active services</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Recipient count badge --}}
                    <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex items-center gap-2">
                            <x-ri-user-line class="w-4 h-4 text-gray-400" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Recipients</span>
                        </div>
                        <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                            {{ number_format($this->recipientCount) }}
                        </span>
                    </div>

                    {{-- Send button --}}
                    @if (!$confirmSend)
                        <button
                            wire:click="prepareSend"
                            wire:loading.attr="disabled"
                            class="w-full flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors disabled:opacity-50"
                        >
                            <x-ri-mail-send-line class="w-4 h-4" />
                            Send Campaign
                        </button>
                    @else
                        {{-- Confirmation step --}}
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 space-y-3">
                            <div class="flex items-start gap-2">
                                <x-ri-alert-line class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Confirm Send</p>
                                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-0.5">
                                        This will send <strong>{{ number_format($this->recipientCount) }}</strong> emails via the queue. This cannot be undone.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    wire:click="sendCampaign"
                                    wire:loading.attr="disabled"
                                    class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold bg-amber-600 text-white hover:bg-amber-500 transition-colors"
                                >
                                    <span wire:loading.remove wire:target="sendCampaign">Yes, Send Now</span>
                                    <span wire:loading wire:target="sendCampaign">Queuing...</span>
                                </button>
                                <button
                                    wire:click="cancelSend"
                                    class="flex-1 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                                >
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
                            style="min-height: 500px;"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                @endif

            </div>
        </div>

        {{-- ── Campaign history ── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Campaign History</h2>
            </div>

            @if ($this->campaigns->isEmpty())
                <div class="px-6 py-12 text-center">
                    <x-ri-mail-line class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No campaigns sent yet.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Recipients</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Sent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->campaigns as $campaign)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $campaign->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $campaign->subject }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                            @if ($campaign->recipient_type === 'active')
                                                <x-ri-shield-check-line class="w-3.5 h-3.5 text-green-500" />
                                                Active customers
                                            @else
                                                <x-ri-group-line class="w-3.5 h-3.5 text-blue-500" />
                                                All users
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($campaign->total_count > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden max-w-24">
                                                    <div
                                                        class="h-full rounded-full {{ $campaign->status === 'done' ? 'bg-green-500' : ($campaign->status === 'failed' ? 'bg-red-500' : 'bg-primary-500') }}"
                                                        style="width: {{ min(100, round($campaign->sent_count / $campaign->total_count * 100)) }}%"
                                                    ></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ number_format($campaign->sent_count) }}/{{ number_format($campaign->total_count) }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $color = match($campaign->status) {
                                                'done'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'sending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                'failed'  => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                default   => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                            {{ ucfirst($campaign->status) }}
                                        </span>
                                        @if ($campaign->status === 'failed' && $campaign->error_message)
                                            <p class="text-xs text-red-500 mt-1 max-w-xs truncate" title="{{ $campaign->error_message }}">{{ $campaign->error_message }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
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
