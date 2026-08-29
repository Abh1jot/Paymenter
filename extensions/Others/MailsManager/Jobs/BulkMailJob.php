<?php

namespace Paymenter\Extensions\Others\MailsManager\Jobs;

use App\Models\Service;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Paymenter\Extensions\Others\MailsManager\Mail\BulkMail;
use Paymenter\Extensions\Others\MailsManager\Models\BulkCampaign;

class BulkMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    public function __construct(
        public readonly BulkCampaign $campaign
    ) {}

    public function handle(): void
    {
        $campaign = $this->campaign->fresh();

        if (!$campaign || $campaign->status === 'done') {
            return;
        }

        // Mark as sending
        $campaign->update([
            'status'     => 'sending',
            'started_at' => now(),
        ]);

        try {
            $users = $this->resolveRecipients($campaign->recipient_type);

            $campaign->update(['total_count' => $users->count()]);

            $sent = 0;

            // Send in chunks of 50 to be memory-efficient
            foreach ($users->chunk(50) as $chunk) {
                foreach ($chunk as $user) {
                    try {
                        Mail::to($user->email)->queue(
                            new BulkMail($campaign, $user)
                        );
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning("MailsManager: Failed to queue mail to {$user->email}: " . $e->getMessage());
                    }
                }

                // Update progress periodically
                $campaign->update(['sent_count' => $sent]);
            }

            $campaign->update([
                'status'      => 'done',
                'sent_count'  => $sent,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $campaign->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
            Log::error('MailsManager: BulkMailJob failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the list of users to send to based on recipient_type.
     *
     * 'all'    → all users
     * 'active' → users with at least one active service
     */
    private function resolveRecipients(string $type): \Illuminate\Database\Eloquent\Collection
    {
        if ($type === 'active') {
            $userIds = Service::where('status', 'active')
                ->distinct()
                ->pluck('user_id');

            return User::whereIn('id', $userIds)->get();
        }

        return User::all();
    }

    public function failed(\Throwable $exception): void
    {
        try {
            $this->campaign->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Campaign might not exist anymore
        }

        Log::error('MailsManager: BulkMailJob permanently failed: ' . $exception->getMessage());
    }
}
