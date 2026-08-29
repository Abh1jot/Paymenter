<div wire:poll.5s>
    {{-- This component is embedded inside template-editor or bulk-mailer as needed --}}
    <iframe
        srcdoc="{{ $this->renderedHtml }}"
        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white"
        style="min-height: 400px;"
        sandbox="allow-same-origin"
    ></iframe>
</div>
