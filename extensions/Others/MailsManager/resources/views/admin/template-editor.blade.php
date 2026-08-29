<x-filament-panels::page>
    <div class="flex gap-6 items-start">

        {{-- ── Template list ── --}}
        <div class="w-72 flex-shrink-0">
            <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Email Templates
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $this->templates->count() }} templates</p>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[72vh] overflow-y-auto">
                    @foreach ($this->templates as $template)
                        <li>
                            <button
                                wire:click="editTemplate({{ $template->id }})"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $editingId === $template->id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' : '' }}"
                            >
                                <div class="flex items-center gap-2">
                                    @if ($template->enabled)
                                        <span class="inline-flex h-2 w-2 flex-shrink-0 rounded-full bg-green-400"></span>
                                    @else
                                        <span class="inline-flex h-2 w-2 flex-shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                    @endif
                                    <span class="text-sm font-mono text-gray-700 dark:text-gray-200 truncate">{{ $template->key }}</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5 pl-4">{{ $template->subject }}</p>
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
                <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm flex items-center justify-center" style="min-height: 400px;">
                    <div class="text-center px-8 py-12">
                        <x-ri-mail-settings-line class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                        <h3 class="text-base font-medium text-gray-700 dark:text-gray-300 mb-1">Select a template</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Click a template on the left to start editing.</p>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    {{-- Header card --}}
                    <div class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div>
                                <code class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $this->editingTemplate?->key }}</code>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Edit subject and body below</p>
                            </div>
                            <div class="flex items-center gap-2">
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
                                        {{ $showPreview ? 'Hide Preview' : 'Preview' }}
                                    </span>
                                </button>
                                <button
                                    wire:click="sendTestEmail"
                                    wire:loading.attr="disabled"
                                    wire:target="sendTestEmail"
                                    class="px-3 py-1.5 text-sm font-medium bg-white dark:bg-gray-800 border border-amber-400 dark:border-amber-500 text-amber-600 dark:text-amber-400 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors focus:outline-none"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <x-ri-mail-send-line class="w-4 h-4" />
                                        <span wire:loading.remove wire:target="sendTestEmail">Send Test</span>
                                        <span wire:loading wire:target="sendTestEmail">Sending...</span>
                                    </span>
                                </button>
                                <button
                                    wire:click="saveTemplate"
                                    wire:loading.attr="disabled"
                                    wire:target="saveTemplate"
                                    class="px-3 py-1.5 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors focus:outline-none"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <x-ri-save-line class="w-4 h-4" />
                                        <span wire:loading.remove wire:target="saveTemplate">Save</span>
                                        <span wire:loading wire:target="saveTemplate">Saving...</span>
                                    </span>
                                </button>
                                <button
                                    wire:click="closeEditor"
                                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                                    title="Close editor"
                                >
                                    <x-ri-close-line class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {{-- Editor + preview layout --}}
                        <div class="{{ $showPreview ? 'grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700' : '' }}">

                            {{-- LEFT: Form fields --}}
                            <div class="p-5 space-y-5">
                                {{-- Subject --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Subject Line</label>
                                    <input
                                        type="text"
                                        wire:model.live="editSubject"
                                        placeholder="Email subject..."
                                        class="block w-full p-2.5 text-sm border-gray-300 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                {{-- Body --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                        Email Body
                                        <span class="font-normal text-gray-500 ml-1">(Markdown or HTML)</span>
                                    </label>
                                    <textarea
                                        wire:model.live.debounce.300ms="editBody"
                                        rows="18"
                                        placeholder="Write your email body here..."
                                        class="block w-full p-2.5 text-sm border-gray-300 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500 font-mono resize-y"
                                    ></textarea>
                                </div>

                                {{-- Variable hints --}}
                                @if ($this->availableVariables)
                                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg">
                                        <h4 class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wider mb-2 flex items-center gap-1">
                                            <x-ri-code-line class="w-3.5 h-3.5" />
                                            Available Variables
                                        </h4>
                                        <div class="space-y-1.5 text-xs">
                                            @foreach ($this->availableVariables as $var => $examples)
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $var }}</span>
                                                    @foreach ($examples as $ex)
                                                        <code class="bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-1.5 py-0.5 rounded font-mono">@php echo htmlspecialchars('{{ ' . $ex . ' }}'); @endphp</code>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- RIGHT: Live preview --}}
                            @if ($showPreview)
                                <div class="p-4">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1">
                                        <x-ri-eye-line class="w-3.5 h-3.5" />
                                        Live Preview <span class="font-normal">(sample data)</span>
                                    </p>
                                    <iframe
                                        srcdoc="{{ $this->previewHtml }}"
                                        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
                                        style="min-height: 520px;"
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
</x-filament-panels::page>
