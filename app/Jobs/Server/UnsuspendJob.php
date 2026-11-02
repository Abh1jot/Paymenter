<?php

namespace App\Jobs\Server;

use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UnsuspendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public Service $service) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ExtensionHelper::unsuspendServer($this->service);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if ($msg === 'no server assigned to this product'
                || str_contains($msg, 'not suspended')
                || str_contains($msg, 'already active')
                || str_contains($msg, 'cannot unsuspend')
                || str_contains($msg, 'already unsuspended')) {
                return;
            }
            throw $e;
        }
    }
}
