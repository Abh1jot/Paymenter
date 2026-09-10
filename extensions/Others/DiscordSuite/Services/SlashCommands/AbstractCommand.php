<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services\SlashCommands;

use App\Models\User;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

abstract class AbstractCommand
{
    abstract public function getName(): string;

    abstract public function getDescription(): string;

    public function getOptions(): array
    {
        return [];
    }

    public function isAdminOnly(): bool
    {
        return false;
    }

    abstract public function handle(array $interaction): array;

    protected function respond(array $embed, bool $ephemeral = false, array $components = []): array
    {
        $response = [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'embeds' => [$embed],
            ],
        ];

        if ($ephemeral) {
            $response['data']['flags'] = 64; // EPHEMERAL
        }

        if (!empty($components)) {
            $response['data']['components'] = $components;
        }

        return $response;
    }

    protected function respondText(string $text, bool $ephemeral = false): array
    {
        $response = [
            'type' => 4,
            'data' => [
                'content' => $text,
            ],
        ];

        if ($ephemeral) {
            $response['data']['flags'] = 64;
        }

        return $response;
    }

    protected function getDiscordUserId(array $interaction): ?string
    {
        return $interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? null;
    }

    protected function getLinkedUser(array $interaction): ?User
    {
        $discordId = $this->getDiscordUserId($interaction);
        if (!$discordId) {
            return null;
        }

        $account = LinkedDiscordAccount::where('discord_user_id', $discordId)->with('user')->first();

        return $account?->user;
    }

    protected function isDiscordAdmin(array $interaction): bool
    {
        // Check Discord ADMINISTRATOR permission flag (0x8) in interaction member permissions
        $permissions = (int) ($interaction['member']['permissions'] ?? 0);
        if (($permissions & 0x8) === 0x8) {
            return true;
        }

        // Or check if linked user has Paymenter admin role
        $user = $this->getLinkedUser($interaction);

        return $user && $user->role_id !== null;
    }

    protected function getOptionValue(array $interaction, string $optionName): mixed
    {
        $options = $interaction['data']['options'] ?? [];
        foreach ($options as $opt) {
            if ($opt['name'] === $optionName) {
                return $opt['value'] ?? null;
            }
        }

        return null;
    }
}
