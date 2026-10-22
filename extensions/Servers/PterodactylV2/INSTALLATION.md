# Installation Guide: Pterodactyl v2 Extension for Paymenter

This guide walks you through installing, configuring, and verifying the **Pterodactyl v2 Multi-Version Extension** in your Paymenter installation.

---

## 1. System Requirements

Ensure your hosting environment meets the following baseline requirements:

| Component | Minimum Requirement | Recommended |
| :--- | :--- | :--- |
| **PHP** | 8.2 or higher | PHP 8.3 / 8.4 |
| **Paymenter** | v1.1.0 or higher | Latest stable release |
| **Pterodactyl Panel** | v1.10.x or v2.0.x | v2.0.0+ |
| **PHP Extensions** | `curl`, `openssl`, `mbstring`, `json` | Enabled |
| **SSL/TLS** | Valid TLS Certificate on Panel | Strict HTTPS |

---

## 2. File Placement

1. Download or extract the extension files into your Paymenter root directory:
   ```
   extensions/Servers/PterodactylV2/
   ```

2. Confirm the directory structure looks as follows:
   ```
   d:/your-paymenter/extensions/Servers/PterodactylV2/
   ├── Console/
   │   └── Commands/SyncServicesCommand.php
   ├── DTO/
   │   ├── ResourceUsage.php
   │   └── ServerDetails.php
   ├── Livewire/
   │   └── ServerManager.php
   ├── Services/
   │   ├── LifecycleService.php
   │   ├── PanelVersionDetector.php
   │   ├── PasswordService.php
   │   ├── PowerService.php
   │   ├── ProvisioningService.php
   │   ├── PterodactylClient.php
   │   └── ServerResolver.php
   ├── views/
   │   └── livewire/server-manager.blade.php
   ├── PterodactylV2.php
   └── extension.json
   ```

3. Ensure correct file permissions:
   ```bash
   chown -R www-data:www-data extensions/Servers/PterodactylV2
   chmod -R 755 extensions/Servers/PterodactylV2
   ```

---

## 3. Pterodactyl API Key Configuration

The extension requires two types of API keys from your Pterodactyl panel:

### A. Application API Key (`ptla_...`)
1. Log in to your Pterodactyl Panel as an administrator.
2. Navigate to **Admin Control Panel** &rarr; **Application API** (`/admin/api`).
3. Click **Create New** with the following permissions:
   - **Users**: Read & Write
   - **Nodes**: Read & Write
   - **Allocations**: Read & Write
   - **Servers**: Read & Write
   - **Nests**: Read & Write
   - **Eggs**: Read
4. Copy the generated key (starts with `ptla_`).

### B. Client API Key (`ptlc_...`)
1. In the customer/user view of Pterodactyl, navigate to **Account Settings** &rarr; **API Credentials** (`/account/api`).
2. Create an administrative client API key (used by the panel to query real-time server telemetry and dispatch power actions).
3. Copy the generated key (starts with `ptlc_`).

> [!IMPORTANT]
> The extension implements automatic token redaction. Even if an API exception occurs, tokens starting with `ptla_` or `ptlc_` will be masked (`ptla_...***REDACTED***`) in all system logs.

---

## 4. Paymenter Admin Configuration

1. In Paymenter, navigate to **Admin** &rarr; **Settings** &rarr; **Extensions** &rarr; **Servers**.
2. Click **Add Server** or select **PterodactylV2**.
3. Fill in the connection settings:
   - **Name**: e.g. `Pterodactyl Panel (Production)`
   - **Pterodactyl Panel URL**: `https://panel.yourdomain.com` (Do not include a trailing slash)
   - **Application API Key**: Paste your `ptla_...` key.
   - **Client API Key**: Paste your `ptlc_...` key.
   - **Panel Version**: Select `Auto-Detect (Recommended)`.
4. Click **Test Connection**:
   - The extension will test both the Application API and Client API.
   - It verifies that eggs, servers, and nodes can be listed and outputs the detected panel version (e.g. `Connected to Pterodactyl v2.0+ (25 servers, 4 nodes online)`).
5. Click **Save Changes**.

---

## 5. Product Setup

1. Navigate to **Admin** &rarr; **Products** &rarr; **Create Product** (or edit an existing one).
2. Set the **Server Extension** to **PterodactylV2**.
3. Configure the hardware and allocation settings:
   - **Egg ID**: Enter the Pterodactyl Egg ID.
   - **Nest ID**: (Optional for v2, required for v1).
   - **Memory**: RAM limit in megabytes (e.g., `2048` for 2 GB).
   - **Swap**: Swap memory in megabytes (e.g., `0` or `512`).
   - **Disk**: Disk limit in megabytes (e.g., `10240` for 10 GB).
   - **CPU**: CPU limit in percent (e.g., `100` for 1 vCPU, `200` for 2 vCPUs).
   - **IO**: Block IO priority (default `500`).
   - **Node ID**: Specific node for provisioning, or leave blank to use location-based auto deployment.
   - **Location IDs**: Comma-separated location IDs for automatic placement.
   - **Dedicated IP**: Toggle if this product requires its own dedicated IPv4.
4. Save the product.

---

## 6. Verification and Smoke Testing

1. **Order a Test Service**:
   - Create a test order as a customer.
   - Paymenter will call `PterodactylV2::createServer()`.
2. **Verify Server Creation**:
   - Confirm the server appears in your Pterodactyl Panel with `external_id` equal to the Paymenter Service ID.
   - Verify that credentials (`ptero_password` and `epc_password`) are encrypted and saved to the service properties.
3. **Verify Customer Dashboard**:
   - Navigate to the service page as the customer.
   - Verify the Livewire container displays live CPU, Memory, Disk, and Network gauges.
   - Test power buttons (`Restart`, `Stop`, `Start`).
   - Click the **Reveal Password** and **Rotate Password** buttons to confirm seamless synchronization.
4. **Verify Lifecycle**:
   - Test suspending and unsuspending the service from the Paymenter Admin panel.
   - Verify the server in Pterodactyl reflects the suspended state immediately.

---

## 7. Need Help?

If you encounter connection errors or firewall restrictions, review [UPGRADE_GUIDE.md](UPGRADE_GUIDE.md) and [SECURITY.md](SECURITY.md) for troubleshooting tips and firewall recommendations.
