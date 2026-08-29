<?php

namespace Paymenter\Extensions\Others\MailsManager;

use App\Classes\Extension\Extension;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

class MailsManager extends Extension
{
    // ─── Extension metadata ───────────────────────────────────────

    public function getConfig($values = []): array
    {
        return [
            [
                'name'  => 'info',
                'type'  => 'placeholder',
                'label' => 'MailsManager adds a rich email template editor with live preview, test-send, and a bulk email campaign tool to your Paymenter admin.',
            ],
        ];
    }

    // ─── Migration helpers ────────────────────────────────────────

    private function runMigrations(): void
    {
        Artisan::call('migrate', [
            '--path'  => 'extensions/Others/MailsManager/database/migrations',
            '--force' => true,
        ]);
        $output = Artisan::output();
        if ($output) {
            Log::info('MailsManager migrate: ' . trim($output));
        }
    }

    private function rollbackMigrations(): void
    {
        Artisan::call('migrate:rollback', [
            '--path'  => 'extensions/Others/MailsManager/database/migrations',
            '--force' => true,
        ]);
    }

    // ─── Lifecycle hooks ──────────────────────────────────────────

    public function installed(): void
    {
        try {
            $this->runMigrations();
        } catch (\Throwable $e) {
            Log::error('MailsManager install: migration failed — ' . $e->getMessage());
        }

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            //
        }
    }

    public function uninstalled(): void
    {
        try {
            $this->rollbackMigrations();
        } catch (\Throwable $e) {
            Log::error('MailsManager uninstall: rollback failed — ' . $e->getMessage());
        }

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            //
        }
    }

    public function upgraded($oldVersion = null): void
    {
        try {
            $this->runMigrations();
        } catch (\Throwable $e) {
            Log::error('MailsManager upgrade: migration failed — ' . $e->getMessage());
        }
    }

    // ─── Boot (every request while enabled) ──────────────────────

    public function boot(): void
    {
        // Register views under 'mailsmanager::' namespace
        View::addNamespace('mailsmanager', __DIR__ . '/resources/views');

        // Register Livewire component for live email preview
        try {
            Livewire::component('mailsmanager.email-preview', Livewire\EmailPreview::class);
        } catch (\Throwable $e) {
            Log::warning('MailsManager: Livewire registration failed: ' . $e->getMessage());
        }

        // Register Filament admin pages just before the panel renders
        try {
            Filament::serving(function () {
                try {
                    Filament::getPanel('admin')?->pages([
                        Admin\Pages\TemplateEditor::class,
                        Admin\Pages\BulkMailer::class,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('MailsManager: Panel page injection failed: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            Log::warning('MailsManager: Filament::serving() failed: ' . $e->getMessage());
        }
    }
}
