<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Service to manage, generate, and securely store customer Pterodactyl panel passwords.
 *
 * Implements backward compatibility with EasyPtroClient's 'epc_password' property key.
 */
class PasswordService
{
    /**
     * Retrieve and decrypt the stored password for a service, if available.
     */
    public static function getStoredPassword(Service $service): ?string
    {
        $properties = $service->properties->pluck('value', 'key')->toArray();

        // Check new key first, then legacy EasyPtroClient key
        $encrypted = $properties['ptero_password'] ?? $properties['epc_password'] ?? null;

        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException $e) {
            return null;
        }
    }

    /**
     * Generate a new secure password, update it in Pterodactyl, and persist it encrypted.
     */
    public static function generateAndSyncPassword(Service $service, PterodactylClient $client): string
    {
        $newPassword = self::generateSecurePassword();

        // 1. Find the Pterodactyl user by email
        $userEmail = $service->user->email;
        $usersResponse = $client->appRequest('users', 'GET', ['filter' => ['email' => $userEmail]]);
        $userData = $usersResponse['data'][0]['attributes'] ?? null;

        if (!$userData) {
            throw new Exception("No Pterodactyl user account found for email '{$userEmail}'.");
        }

        $userId = $userData['id'];

        // 2. Patch user password on Pterodactyl
        $client->appRequest("users/{$userId}", 'PATCH', [
            'email' => $userData['email'],
            'username' => $userData['username'],
            'first_name' => $userData['first_name'] ?? 'User',
            'last_name' => $userData['last_name'] ?? '',
            'password' => $newPassword,
        ]);

        // 3. Store encrypted in service properties
        $encrypted = Crypt::encryptString($newPassword);

        // Store in both new and legacy keys for absolute backward compatibility
        $service->properties()->updateOrCreate(
            ['key' => 'ptero_password'],
            ['value' => $encrypted]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'epc_password'],
            ['value' => $encrypted]
        );

        return $newPassword;
    }

    /**
     * Generate a cryptographically strong 16-character password.
     */
    public static function generateSecurePassword(int $length = 16): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }
}
