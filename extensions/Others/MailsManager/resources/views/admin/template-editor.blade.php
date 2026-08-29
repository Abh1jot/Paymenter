<x-filament-panels::page>
    <div class="mailsmanager-template-editor" x-data="{ tab: 'editor' }">

        {{-- ── Page header ── --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Template Editor</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Edit your Paymenter email templates with a rich editor and live preview.
                Changes are saved directly to the database and take effect immediately.
            </p>
        </div>

        <div class="flex gap-6 items-start" style="min-height: 75vh;">

            {{-- ── Template list sidebar ── --}}
            <div class="w-72 flex-shrink-0">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Templates</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $this->templates->count() }} templates</p>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[70vh] overflow-y-auto">
                        @foreach ($this->templates as $template)
                            <li>
                                <button
                                    wire:click="editTemplate({{ $template->id }})"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $editingId === $template->id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' : '' }}"
                                >
                                    <div class="flex items-start gap-2">
                                        <div class="mt-0.5">
                                            @if ($template->enabled)
                                                <span class="inline-flex h-2 w-2 rounded-full bg-green-400"></span>
                                            @else
                                                <span class="inline-flex h-2 w-2 rounded-full bg-gray-300"></span>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $template->name ?: $template->key }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate mt-0.5">{{ $template->key }}</p>
                                        </div>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- ── Editor panel ── --}}
            <div class="flex-1 min-w-0">
                @if (!$editingId)
                    {{-- Empty state --}}
                    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex items-center justify-center" style="min-height: 400px;">
                        <div class="text-center px-8 py-12">
                            <x-ri-mail-settings-line class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                            <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Select a template</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Choose an email template from the list on the left to start editing.</p>
                        </div>
                    </div>
                @else
                    <div class="space-y-4">
                        {{-- Editor card --}}
                        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">

                            {{-- Header bar --}}
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <div>
                                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $this->editingTemplate?->name ?: $this->editingTemplate?->key }}
                                    </h2>
                                    <code class="text-xs text-gray-500 dark:text-gray-400">{{ $this->editingTemplate?->key }}</code>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        wire:click="togglePreview"
                                        class="fi-btn fi-btn-color-gray fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                                    >
                                        <x-ri-eye-line class="w-4 h-4" />
                                        {{ $showPreview ? 'Hide Preview' : 'Show Preview' }}
                                    </button>
                                    <button
                                        wire:click="sendTestEmail"
                                        wire:loading.attr="disabled"
                                        wire:target="sendTestEmail"
                                        class="fi-btn fi-btn-color-warning fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-warning-700 dark:text-warning-300 ring-1 ring-warning-300 dark:ring-warning-600 hover:bg-warning-50 dark:hover:bg-warning-900/20 transition-colors"
                                    >
                                        <x-ri-mail-send-line class="w-4 h-4" />
                                        <span wire:loading.remove wire:target="sendTestEmail">Send Test</span>
                                        <span wire:loading wire:target="sendTestEmail">Sending...</span>
                                    </button>
                                    <button
                                        wire:click="saveTemplate"
                                        wire:loading.attr="disabled"
                                        wire:target="saveTemplate"
                                        class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium bg-primary-600 text-white hover:bg-primary-500 transition-colors"
                                    >
                                        <x-ri-save-line class="w-4 h-4" />
                                        <span wire:loading.remove wire:target="saveTemplate">Save</span>
                                        <span wire:loading wire:target="saveTemplate">Saving...</span>
                                    </button>
                                    <button
                                        wire:click="closeEditor"
                                        class="fi-btn fi-btn-size-sm inline-flex items-center rounded-lg px-2 py-1.5 text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                                        title="Close"
                                    >
                                        <x-ri-close-line class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>

                            {{-- Editor body --}}
                            <div class="{{ $showPreview ? 'grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700' : '' }}">

                                {{-- LEFT: Form --}}
                                <div class="p-6 space-y-4">
                                    {{-- Subject --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Subject Line
                                        </label>
                                        <input
                                            type="text"
                                            wire:model.live="editSubject"
                                            placeholder="Email subject..."
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        />
                                    </div>

                                    {{-- Body --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            Email Body
                                            <span class="text-xs font-normal text-gray-500 ml-1">(Markdown or HTML)</span>
                                        </label>
                                        <textarea
                                            wire:model.live.debounce.300ms="editBody"
                                            rows="20"
                                            placeholder="Write your email body here. Supports Markdown and HTML."
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono resize-y"
                                        ></textarea>
                                    </div>

                                    {{-- Variable hints --}}
                                    @if ($this->availableVariables)
                                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 p-4">
                                            <h4 class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wide mb-2 flex items-center gap-1">
                                                <x-ri-code-line class="w-3.5 h-3.5" />
                                                Available Variables
                                            </h4>
                                            <div class="space-y-1.5">
                                                @foreach ($this->availableVariables as $var => $examples)
                                                    <div>
                                                        <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">{{ $var }}</span>
                                                        <span class="text-xs text-blue-500 dark:text-blue-500 ml-2">→</span>
                                                        @foreach ($examples as $ex)
                                                            <code class="ml-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-1 py-0.5 rounded">@php echo htmlspecialchars('{{ ' . $ex . ' }}'); @endphp</code>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- RIGHT: Live preview (only when $showPreview) --}}
                                @if ($showPreview)
                                    <div class="p-4">
                                        <div class="mb-3 flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                <x-ri-eye-line class="w-3.5 h-3.5" />
                                                Live Preview
                                            </span>
                                            <span class="text-xs text-gray-400">(uses sample data)</span>
                                        </div>
                                        <iframe
                                            srcdoc="{{ $this->previewHtml }}"
                                            class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
                                            style="height: calc(100% - 2.5rem); min-height: 500px;"
                                            sandbox="allow-same-origin"
                                        ></iframe>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

        </div>

    </div>
</x-filament-panels::page>
