# Upgrade & Backward Compatibility Guide

## Migrating from EasyPtroClient Gold / Pterodactyl v1 to Pterodactyl v2

This document provides a comprehensive, risk-free upgrade path for hosting providers migrating from the legacy **EasyPtroClient Gold** extension or the default **Pterodactyl v1** extension to **Pterodactyl v2 Multi-Version**.

> [!IMPORTANT]
> **Zero Downtime & Zero Reprovisioning Guarantee**:
> Upgrading to PterodactylV2 **will not** delete, reinstall, or recreate any existing customer servers. Your customers' files, world data, databases, and IP allocations remain completely intact.

---

## 1. How Backward Compatibility Works

The legacy EasyPtroClient Gold extension used the following conventions to link Paymenter services with Pterodactyl servers:

1. **Server Association (`external_id`)**:
   - EasyPtroClient set the Pterodactyl server's `external_id` attribute to `(string) $service->id`.
2. **Encrypted Credentials (`epc_password`)**:
   - EasyPtroClient encrypted the user's panel password in the Paymenter `service_properties` table using the key `'epc_password'`.
3. **Cached Server IDs**:
   - In some versions, the numeric server ID was stored under `server_id` or `ptero_server_id`.

### How PterodactylV2 Solves This:

Our new implementation features a **Multi-Tier Server Resolver** (`ServerResolver.php`):

```mermaid
graph TD
    A[Paymenter Service Request] --> B{Stored 'ptero_server_id' or 'server_id'?}
    B -- Found --> C[Fetch /api/application/servers/{id}]
    B -- Not Found --> D{Lookup by external_id = service->id}
    D -- Found --> E[Sync properties & Return Server]
    D -- Not Found --> F{Lookup by short identifier or UUID}
    F -- Found --> E
    F -- Not Found --> G[Safely Report Not Found - NEVER recreate]
```

- **Dual-Key Password Synchronization**: `PasswordService` reads from `ptero_password` first, with immediate fallback to `epc_password`. When rotating or saving passwords, both `ptero_password` and `epc_password` are updated, ensuring that legacy scripts or reporting tools continue to function without errors.
- **Idempotency Safeguard**: `PterodactylV2::createServer()` checks if a server matching `external_id = (string) $service->id` already exists remotely before making any creation call. If found, it links the server immediately instead of provisioning a duplicate container.

---

## 2. Step-by-Step Upgrade Procedure

### Step 1: Pre-Upgrade Database Backup
Always create a complete database backup before modifying extensions:
```bash
# If using MySQL/MariaDB
mysqldump -u paymenter -p paymenter > paymenter_backup_$(date +%Y%m%d_%H%M%S).sql

# Or if using SQLite
cp database/database.sqlite database/database.sqlite.bak
```

### Step 2: Install PterodactylV2 Extension Files
Copy the `PterodactylV2` extension into your Paymenter extensions directory:
```bash
cp -r /path/to/extracted/PterodactylV2 extensions/Servers/
```
Ensure correct permissions:
```bash
chown -R www-data:www-data extensions/Servers/PterodactylV2
```

### Step 3: Configure Panel Connection in Paymenter
1. Log in to the Paymenter Admin panel.
2. Go to **Settings** &rarr; **Extensions** &rarr; **Servers** &rarr; **PterodactylV2**.
3. Enter your Panel URL, Application API Key (`ptla_...`), and Client API Key (`ptlc_...`).
4. Click **Test Connection** to confirm connectivity.

### Step 4: Audit Existing Services (Dry-Run Mode)
The extension includes a dedicated Artisan command to inspect existing services without making any modifications:

```bash
php artisan pterodactylv2:sync-services --dry-run
```

**Expected Dry-Run Output:**
```
Starting Pterodactyl Service Synchronization & Verification...
DRY RUN MODE: No database changes will be saved.
Found 42 services to inspect.

+------------+-----------------------+------------------+------------------+-----------------------------+
| Service ID | Customer Email        | Ptero Server ID  | Ptero Identifier | Status / Action             |
+------------+-----------------------+------------------+------------------+-----------------------------+
| 101        | alex@example.com      | 2050             | srv105ab         | Matched (Dry Run)           |
| 102        | bob@example.com       | 2051             | srv106bc         | Matched (Dry Run)           |
| 103        | charlie@example.com   | 2052             | srv107cd         | Matched (Dry Run)           |
+------------+-----------------------+------------------+------------------+-----------------------------+
Audit complete: 42 verified, 0 errors, 0 orphaned.
```

### Step 5: Perform Live Service Synchronization
Once you have reviewed the dry-run output, execute the live synchronization:

```bash
php artisan pterodactylv2:sync-services
```

This writes the normalized `ptero_server_id`, `ptero_uuid`, and `ptero_identifier` properties to the database and ensures existing `epc_password` records are verified.

> [!TIP]
> You can also synchronize a single specific service by passing `--service={id}`:
> ```bash
> php artisan pterodactylv2:sync-services --service=101
> ```

### Step 6: Update Products to Use PterodactylV2
1. In Paymenter Admin, navigate to **Products**.
2. For each product currently linked to `EasyPtroClient` or `Pterodactyl`, change the **Server Extension** dropdown to **PterodactylV2**.
3. Save the product.

Existing active services attached to those products will now automatically load the new, responsive **PterodactylV2** Livewire control dashboard.

---

## 3. Handling Unmatched or Orphaned Services

If the audit command reports that a service could not be matched automatically:

1. **Verify Server Existence in Pterodactyl**: Check if the customer's server was manually deleted directly from Pterodactyl.
2. **Check External ID**: In Pterodactyl Admin &rarr; Servers &rarr; Select Server, verify if the `external_id` matches the Paymenter Service ID.
3. **Manual Link Recovery**: If the external ID was cleared or altered, manually update the `external_id` in Pterodactyl to match the Paymenter Service ID, then re-run:
   ```bash
   php artisan pterodactylv2:sync-services --service={id} --force
   ```

---

## 4. Rollback Procedure

In the unlikely event that you need to revert to the old extension:

1. In Paymenter Admin &rarr; **Products**, change the Server Extension back to `EasyPtroClient` or `Pterodactyl`.
2. Because PterodactylV2 preserved the original `external_id` and all `epc_password` properties without destructive modifications, the old extension will resume functioning immediately.
3. If necessary, restore your pre-upgrade database backup:
   ```bash
   mysql -u paymenter -p paymenter < paymenter_backup_YYYYMMDD_HHMMSS.sql
   ```
