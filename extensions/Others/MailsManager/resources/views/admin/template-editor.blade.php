<x-filament-panels::page>
    <div class="flex gap-6 items-start">

        {{-- ── Template list sidebar ── --}}
        <div class="w-64 flex-shrink-0">
            <x-filament::section>
                <x-slot name="heading">Email Templates</x-slot>
                <x-slot name="description">{{ $this->templates->count() }} templates</x-slot>

                <div class="-mx-4 -mb-4 divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($this->templates as $template)
                        <button
                            wire:click="editTemplate({{ $template->id }})"
                            @class([
                                'w-full text-left px-4 py-3 transition-colors',
                                'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' => $editingId === $template->id,
                                'hover:bg-gray-50 dark:hover:bg-white/5' => $editingId !== $template->id,
                            ])
                        >
                            <div class="flex items-center gap-2">
                                @if ($template->enabled)
                                    <span class="inline-block h-2 w-2 flex-shrink-0 rounded-full bg-green-400"></span>
                                @else
                                    <span class="inline-block h-2 w-2 flex-shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                @endif
                                <span class="font-mono font-medium text-gray-800 dark:text-gray-100 truncate text-xs">{{ $template->key }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5 pl-4">{{ $template->subject }}</p>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- ── Editor ── --}}
        <div class="flex-1 min-w-0">
            @if (!$editingId)
                <x-filament::section>
                    <div class="py-8 text-center">
                        <x-ri-mail-settings-line class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Select a template</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Click any template on the left to start editing.</p>
                    </div>
                </x-filament::section>
            @else
                <div class="space-y-4">
                    {{-- Action bar --}}
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <p class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $this->editingTemplate?->key }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Editing template</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::button
                                wire:click="togglePreview"
                                color="{{ $showPreview ? 'primary' : 'gray' }}"
                                size="sm"
                                icon="ri-eye-line"
                            >
                                {{ $showPreview ? 'Hide Preview' : 'Preview' }}
                            </x-filament::button>

                            <x-filament::button
                                wire:click="sendTestEmail"
                                wire:loading.attr="disabled"
                                wire:target="sendTestEmail"
                                color="warning"
                                size="sm"
                                icon="ri-mail-send-line"
                            >
                                <span wire:loading.remove wire:target="sendTestEmail">Send Test</span>
                                <span wire:loading wire:target="sendTestEmail">Sending…</span>
                            </x-filament::button>

                            <x-filament::button
                                wire:click="saveTemplate"
                                wire:loading.attr="disabled"
                                wire:target="saveTemplate"
                                size="sm"
                                icon="ri-save-line"
                            >
                                <span wire:loading.remove wire:target="saveTemplate">Save</span>
                                <span wire:loading wire:target="saveTemplate">Saving…</span>
                            </x-filament::button>

                            <button
                                wire:click="closeEditor"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors"
                                title="Close"
                            >
                                <x-ri-close-line class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    {{-- Editor + optional preview --}}
                    <div @class(['grid grid-cols-2 gap-4' => $showPreview])>

                        {{-- Filament form (renders TextInput + Textarea natively) --}}
                        <div>
                            <x-filament::section>
                                {{ $this->form }}
                            </x-filament::section>

                            {{-- Variable hints --}}
                            @if ($this->availableVariables)
                                <x-filament::section class="mt-4">
                                    <x-slot name="heading">
                                        <span class="flex items-center gap-1.5 text-sm">
                                            <x-ri-code-line class="w-4 h-4" />
                                            Available Variables
                                        </span>
                                    </x-slot>
                                    <div class="space-y-2 text-xs">
                                        @foreach ($this->availableVariables as $var => $examples)
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $var }}</span>
                                                @foreach ($examples as $ex)
                                                    <code class="bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-1.5 py-0.5 rounded font-mono">@php echo htmlspecialchars('{{ ' . $ex . ' }}'); @endphp</code>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </x-filament::section>
                            @endif
                        </div>

                        {{-- Live preview iframe --}}
                        @if ($showPreview)
                            <x-filament::section>
                                <x-slot name="heading">
                                    <span class="flex items-center gap-1.5">
                                        <x-ri-eye-line class="w-4 h-4" />
                                        Live Preview
                                    </span>
                                </x-slot>
                                <x-slot name="description">Rendered with sample data</x-slot>
                                <iframe
                                    x-data="{ html: {{ Js::from($this->previewHtml) }} }"
                                    x-bind:srcdoc="html"
                                    class="w-full rounded-lg border border-gray-200 dark:border-white/10 bg-white"
                                    style="min-height: 540px;"
                                    sandbox="allow-same-origin"
                                ></iframe>
                            </x-filament::section>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>

    {{-- Required by InteractsWithForms --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
