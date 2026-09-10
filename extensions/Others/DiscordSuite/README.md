# Discord Suite for Paymenter v1.x

The ultimate enterprise Discord integration for Paymenter hosting providers.

## Features

1. **Discord Account Linking**:
   - OAuth2 authorization flow with CSRF validation.
   - Livewire Customer Dashboard widget card.
   - Seamless account linking & unlinking.
   - Stores user snowflake ID, username, discriminator, global name, avatar, and token scopes with at-rest encryption.

2. **Automatic Guild Joining**:
   - Uses Discord `guilds.join` OAuth2 scope to add customers automatically to configured Discord servers upon linking.
   - Multi-server support with join greeting messages.

3. **Bidirectional Role Synchronization**:
   - Map Products to Discord Roles.
   - Map Categories to Discord Roles.
   - Customer Verification Tiers:
     - **Verified Customer**: Email verified or has paid invoices.
     - **Active Customer**: Currently owns at least 1 active service.
     - **Premium Customer**: Lifetime spend exceeds minimum threshold (e.g. $100).
     - **VPS / Dedicated / Minecraft Customer**: Automatic detection of server hosting tiers.
   - Differential Sync Algorithm: computes roles to add and remove while safely isolating unmanaged Discord roles (staff/admin/moderator/booster roles are never touched).

4. **Discord Linked Roles API**:
   - Official implementation of Discord Application Role Connection Metadata.
   - Pushes real-time metadata:
     - `active_services`
     - `total_spent`
     - `account_age_days`
     - `invoices_paid`
     - `is_verified`
     - `support_level`
   - Server owners can build custom verification gates directly inside Discord server settings.

5. **Direct Message Notifications & Embeds**:
   - DMs for New Invoice, Invoice Paid, Invoice Overdue, Service Activated, Service Suspended, Service Reactivated, Service Expiring, Service Terminated, Staff Ticket Reply, Product Upgraded, and Credits Added.
   - Rich branded embeds with custom hex colors and interactive payment / dashboard action buttons.
   - Rate-limit aware queue dispatcher with automatic backoff and staff channel logging on bounced DMs.

6. **Automated Renewal Reminders**:
   - Scheduled scanning for services nearing expiration (14 days, 7 days, 3 days, 1 day, and day of expiry).
   - Idempotent daily notifications preventing duplicates.
   - 1-click renewal button linking directly to invoice/checkout.

7. **Discord Slash Commands via HTTP REST Webhooks**:
   - Uses Discord's official HTTP Interactions API with Ed25519 signature verification (`sodium_crypto_sign_verify_detached`).
   - Zero daemon requirement: works on standard PHP-FPM, shared hosting, or Docker.
   - **Customer Commands**:
     - `/profile`
     - `/services`
     - `/service <id>`
     - `/invoices`
     - `/tickets`
     - `/renew <id>`
     - `/balance`
     - `/credits`
     - `/support`
     - `/status`
     - `/link`
     - `/unlink`
   - **Admin Commands**:
     - `/lookup <query>`
     - `/user <user_id>`
     - `/services-user <user_id>`
     - `/invoices-user <user_id>`
     - `/tickets-user <user_id>`
     - `/syncroles [user_id]`
     - `/forcerolesync`
     - `/broadcast <channel_id> <message>`
     - `/stats`

8. **Comprehensive Filament Admin Suite**:
   - **Discord Overview**: Real-time KPI metrics, linked accounts percentage, connected servers, sync health, and interactive sync log table with retry button.
   - **Role Mappings**: Dynamic rule builder supporting product, category, and tier conditions with priorities.
   - **Bot & Setup Guide**: Credentials configuration with live diagnostic buttons (`Test Bot Connection`, `Register Slash Commands`, `Sync Linked Roles Schema`) and step-by-step documentation right on the page.
   - **Notification Settings**: Matrix of 11 toggleable DM notifications, custom embed brand colors, and staff audit channel.
   - **Analytics & Insights**: Growth metrics, role sync velocity, and DM delivery health.

## Installation & Setup

1. Copy the `DiscordSuite` folder to `extensions/Others/DiscordSuite`.
2. In Paymenter Admin -> Extensions -> Available Extensions, locate **Discord Suite** and click **Install**.
3. Enable the extension.
4. Visit **Discord Suite -> Bot & Setup Guide** in the admin sidebar.
5. Follow the step-by-step instructions to enter your Discord Application ID, Public Key, Client Secret, and Bot Token.
6. Click **Test Bot Connection**, then click **Register Slash Commands**.
