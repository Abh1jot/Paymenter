# Pterodactyl v2 & v1 Multi-Version Extension for Paymenter

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-blue.svg)](https://php.net)
[![Paymenter Version](https://img.shields.io/badge/Paymenter-1.1%2B-green.svg)](https://paymenter.org)
[![Pterodactyl Version](https://img.shields.io/badge/Pterodactyl-v1.x%20%7C%20v2.0%2B-purple.svg)](https://pterodactyl.io)
[![Test Suite](https://img.shields.io/badge/Tests-19%20passed%20(118%20assertions)-brightgreen.svg)](#automated-testing)
[![License](https://img.shields.io/badge/License-Proprietary%20%2F%20BuiltByBit-orange.svg)](https://builtbybit.com)

The **Pterodactyl v2 Multi-Version Extension** is a production-grade, enterprise hosting module built from the ground up for Paymenter. Designed specifically to support modern **Pterodactyl 2.0+** while retaining rock-solid backward compatibility with **Pterodactyl 1.x** and existing **EasyPtroClient Gold** services.

---

## Key Highlights

- **Native Pterodactyl v2.0+ Architecture**: Direct egg endpoints (`/api/application/eggs/{id}`), top-level variable relationships, updated limits schemas, and new panel route structures (`/panel/servers/{id}` for admin, `/server/{identifier}` for clients).
- **Full Backward Compatibility**: Automatically detects and adapts to legacy Pterodactyl 1.x panels with nested nests/eggs routes (`/api/application/nests/{nest}/eggs/{egg}`).
- **Zero-Downtime Migration for EasyPtroClient Gold**: Existing Paymenter services provisioned with the older EasyPtroClient extension continue to function seamlessly without any server reprovisioning or customer disruption. Reads and writes both `epc_password` and `ptero_password`.
- **Multi-Tier Server Resolution**: Resolves servers with fail-safe fallback order: cached server ID &rarr; `external_id = (string) $service->id` &rarr; identifier &rarr; UUID. Prevents false "Server Not Found" errors.
- **Real-Time Client Telemetry & Controls**: 5-second polling Livewire customer dashboard displaying live CPU percentage, Memory (MB and %), Disk (MB and %), Network I/O (RX/TX), and container uptime.
- **Interactive Container Controls**: Start, restart, stop, and force kill buttons wired directly to Pterodactyl's Client API with real-time feedback.
- **Enterprise Lifecycle Safety**:
  - **Idempotency Safeguard**: Server creation checks if a server matching `external_id` already exists, returning the existing server instead of spawning expensive duplicate servers on retry.
  - **Termination Verification**: Remote server deletion strictly validates ownership (`external_id === (string) $service->id`) before issuing any destructive API call.
- **Secure Password Rotation**: In-browser password reveal, one-click clipboard copy, and one-click password rotation that synchronizes directly with the Pterodactyl user account via the Application API.
- **Artisan Audit & Sync Tool**: Includes `php artisan pterodactylv2:sync-services` to audit, test match, and synchronize legacy services with dry-run support.

---

## Extension Architecture

```
extensions/Servers/PterodactylV2/
├── Console/
│   └── Commands/
│       └── SyncServicesCommand.php      # Artisan service auditing & synchronization
├── DTO/
│   ├── ResourceUsage.php                # Normalized live container metrics (CPU, RAM, Disk, Net)
│   └── ServerDetails.php                # Universal server attributes, limits, and allocations
├── Livewire/
│   └── ServerManager.php                # High-performance client control component
├── Services/
│   ├── LifecycleService.php             # Suspension, unsuspension, termination & upgrade
│   ├── PanelVersionDetector.php         # In-memory cached API version detection
│   ├── PasswordService.php              # Cryptographically secure password generation & sync
│   ├── PowerService.php                 # Client API power actions & live utilization
│   ├── ProvisioningService.php          # Idempotent server creation & deployment
│   ├── PterodactylClient.php            # HTTP transport, TLS enforcement, error translation & secret masking
│   └── ServerResolver.php               # Multi-tier backward-compatible server association
├── views/
│   └── livewire/
│       └── server-manager.blade.php     # Modern, responsive client UI with dark mode styling
├── PterodactylV2.php                    # Extension entry point, metadata, and lifecycle bindings
└── extension.json                       # Extension packaging metadata
```

---

## Feature Comparison Matrix

| Feature | Legacy Pterodactyl v1 | EasyPtroClient Gold | Pterodactyl v2 Extension (This Release) |
| :--- | :---: | :---: | :---: |
| **Pterodactyl v2.0+ Support** | ❌ No | ❌ No | ✅ Full Native Support |
| **Pterodactyl v1.x Support** | ✅ Yes | ✅ Yes | ✅ Automatic Fallback Support |
| **Top-Level Eggs API (`/eggs/{id}`)** | ❌ No | ❌ No | ✅ Yes |
| **Live Telemetry Gauges (CPU/RAM/Disk)** | ❌ No | ⚠️ Partial | ✅ Real-Time 5s Polling (Livewire) |
| **Interactive Power Actions (Start/Stop/Kill)** | ❌ No | ✅ Yes | ✅ Client API Verified |
| **Duplicate Provisioning Guard** | ❌ No | ❌ No | ✅ Idempotent (`external_id` check) |
| **Safe Cross-Customer Termination Guard** | ❌ No | ❌ No | ✅ Verified Ownership Match |
| **EasyPtroClient Password Compatibility** | ❌ No | ✅ Only EPC | ✅ Dual Sync (`ptero_password` & `epc_password`) |
| **Secret Masking in Application Logs** | ❌ No | ❌ No | ✅ Regex `ptla_` / `ptlc_` Masking |
| **CLI Audit & Sync Tool** | ❌ No | ❌ No | ✅ `pterodactylv2:sync-services` |

---

## Configuration Settings

### Admin Server Settings
- **Pterodactyl Panel URL**: The base URL of your panel (e.g. `https://panel.yourdomain.com`).
- **Application API Key**: Administrative token starting with `ptla_` having read/write permissions for Users, Servers, Nests, and Nodes.
- **Client API Key**: User client API key starting with `ptlc_` used for real-time telemetry and power controls.
- **Panel Version**:
  - `Auto-Detect (Recommended)`: Queries endpoints dynamically and caches results.
  - `Force Pterodactyl v2.0+`: Optimized for Panel 2.0.
  - `Force Pterodactyl v1.x (Legacy)`: Forces legacy nested nest/egg endpoints.

### Product Configuration
- **Egg ID**: The egg ID in Pterodactyl (top-level in v2 or specific ID in v1).
- **Nest ID**: The nest ID (required when running on v1.x panels).
- **Memory, Swap, Disk, CPU, IO**: Resource limits for the container.
- **Node Allocation**: Select a specific Node ID or enable automatic deployment.
- **Port Array**: Configure advanced multi-port allocations (e.g., `25565,25566,25567`).
- **Egg Variables**: Custom startup environment variables matching egg requirements.

---

## Automated Testing

This extension includes a dedicated test suite with **100% passing tests** covering unit logic, error translations, mock API transport, and full service lifecycle workflows.

```bash
# Run Unit Tests (DTOs, Token Masking, Version Detection, Password Generator)
php -c php.ini vendor/bin/phpunit tests/Unit/Extensions/PterodactylV2Test.php

# Run Feature Tests (Idempotency, v2 Eggs, Lifecycle, Client API, CLI Sync)
php -c php.ini vendor/bin/phpunit tests/Feature/Extensions/PterodactylV2FeatureTest.php
```

### Verified Test Results
```
Runtime:       PHP 8.5.2
Tests:         19 total (8 Unit, 11 Feature)
Assertions:    118 assertions
Errors:        0
Failures:      0
Status:        OK (100% Pass)
```

---

## Support & Documentation

- [Installation Guide](INSTALLATION.md)
- [Upgrade Guide from EasyPtroClient](UPGRADE_GUIDE.md)
- [Security Architecture](SECURITY.md)

---
*Developed for BuiltByBit & Paymenter Enterprise Hosting Environments.*
