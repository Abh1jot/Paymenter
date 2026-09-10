<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Livewire\Dashboard;

use App\Livewire\Component;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordWidget extends Component
{
    public ?LinkedDiscordAccount $account = null;

    public bool $isLinked = false;

    public function mount(): void
    {
        $this->loadAccount();
    }

    public function loadAccount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->account = LinkedDiscordAccount::where('user_id', $user->id)->first();
            $this->isLinked = $this->account !== null;
        }
    }

    public function syncNow(): void
    {
        if ($this->account) {
            SyncUserRolesJob::dispatch(Auth::id());
            $this->notify('Role synchronization queued. Your Discord roles will update shortly!');
            $this->account->refresh();
        }
    }

    public function render()
    {
        $user = Auth::user();
        $activeServices = $user ? $user->services()->where('status', Service::STATUS_ACTIVE)->with('product')->get() : collect();
        $expiringServices = $user ? $user->services()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->get() : collect();

        $guilds = DiscordGuild::all();

        return view('discord_suite::widgets.dashboard-card', [
            'activeServices' => $activeServices,
            'expiringServices' => $expiringServices,
            'guilds' => $guilds,
        ]);
    }
}
