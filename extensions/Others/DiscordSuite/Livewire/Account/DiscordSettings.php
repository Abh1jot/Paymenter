<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Livewire\Account;

use App\Livewire\Component;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\AutoJoinGuildJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordSettings extends Component
{
    public ?LinkedDiscordAccount $account = null;

    public bool $isLinked = false;

    public function mount(): void
    {
        $this->refreshAccount();
    }

    public function refreshAccount(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->account = LinkedDiscordAccount::where('user_id', $user->id)->first();
            $this->isLinked = $this->account !== null;
        }
    }

    public function syncRoles(): void
    {
        if ($this->account) {
            SyncUserRolesJob::dispatch(Auth::id());
            $this->notify('Role synchronization requested. Discord servers will update in moments.');
        }
    }

    public function syncLinkedRoles(): void
    {
        if ($this->account) {
            SyncLinkedRolesJob::dispatch(Auth::id());
            $this->notify('Discord Linked Roles metadata synchronization queued!');
        }
    }

    public function rejoinServers(): void
    {
        if ($this->account) {
            AutoJoinGuildJob::dispatch(Auth::id());
            $this->notify('Auto-join dispatched! Check your Discord client for server invitations.');
        }
    }

    public function render()
    {
        $user = Auth::user();
        $activeServices = $user ? $user->services()->where('status', Service::STATUS_ACTIVE)->with('product.category')->get() : collect();
        $guilds = DiscordGuild::all();

        return view('discord_suite::account.settings', [
            'activeServices' => $activeServices,
            'guilds' => $guilds,
        ])->layoutData([
            'sidebar' => true,
        ]);
    }
}
