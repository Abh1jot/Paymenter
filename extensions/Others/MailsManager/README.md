# 📧 MailsManager — Email Templates & Bulk Mailer for Paymenter

> A professional email management extension for [Paymenter](https://paymenter.org) that gives you full control over your transactional email templates and lets you send targeted bulk campaigns to your customers — all from your admin panel.

---

## ✨ Features

### 📝 Email Template Editor
- **Edit all Paymenter email templates** from one clean interface — no more digging through server files
- **Live preview** as you type — see exactly how your email will look before saving
- **Syntax-highlighted editor** with monospace font for clean HTML/Markdown editing
- **Subject line editing** alongside the body
- **Send a test email** to yourself instantly with one click
- **Variable reference panel** — shows every available personalisation variable for the template you're editing (e.g. `{{ $user->first_name }}`, `{{ $invoice->total }}`)
- Works with **all built-in Paymenter templates**: invoices, orders, tickets, password reset, email verification, service events, and more

### 📣 Bulk Mailer
- **Compose and send bulk campaigns** to your entire user base or only active customers
- **HTML email body** support with full personalisation variables
- **Recipient targeting** — choose between:
  - All registered users
  - Active customers only (users with at least one active service)
- **Live recipient count** — updates in real time as you change your targeting
- **Live email preview** — see a rendered preview of your campaign before sending
- **Queue-based sending** — emails are dispatched via Laravel's queue worker so your UI never freezes, even for thousands of recipients
- **Campaign history** — track every campaign you've sent with name, subject, recipient type, send progress, status, and timestamp
- **Real-time progress tracking** — see how many emails have been sent
- **Status badges** — Pending / Sending / Done / Failed at a glance

---

## 🔧 Requirements

| Requirement | Version |
|---|---|
| Paymenter | Any (development / stable) |
| PHP | 8.1+ |
| Laravel | 10+ |
| Filament | v5 (bundled with Paymenter) |
| Queue Worker | Required for bulk sending |

---

## 📦 Installation

1. Upload the `MailsManager` folder to your Paymenter server:
   ```
   /var/www/paymenter/extensions/Others/MailsManager/
   ```

2. Clear Paymenter's cache:
   ```bash
   php artisan optimize:clear
   ```

3. In your Paymenter admin panel, go to **Extensions** and enable **MailsManager**.  
   The database migration runs automatically on first enable.

4. Make sure your queue worker is running for bulk mail delivery:
   ```bash
   php artisan queue:work
   ```

That's it — no additional configuration needed.

---

## 🚀 Usage

### Editing Email Templates

1. Go to **Admin → MailsManager → Email Templates**
2. Click any template from the left sidebar (e.g. `invoice_paid`, `email_verification`)
3. Edit the **Subject** and **Body** fields — live preview updates as you type
4. Click **Preview** to see the rendered email with sample data
5. Click **Send Test** to receive a test email at your admin address
6. Click **Save** to apply changes

### Sending a Bulk Campaign

1. Go to **Admin → MailsManager → Bulk Mailer**
2. Enter a **Campaign Name** (internal reference)
3. Write your **Subject** and **Email Body** (HTML supported)
4. Personalise with variables: `{{ $user->first_name }}`, `{{ $user->last_name }}`, `{{ $user->email }}`
5. Choose **Recipients**: All Users or Active Customers
6. The live **recipient count** updates automatically
7. Click **Send Campaign** → confirm → emails are queued immediately
8. Track delivery in **Campaign History** below the form

---

## ⚙️ Personalisation Variables

Use these in both template bodies and bulk campaign bodies:

| Variable | Description |
|---|---|
| `{{ $user->first_name }}` | Recipient's first name |
| `{{ $user->last_name }}` | Recipient's last name |
| `{{ $user->email }}` | Recipient's email address |
| `{{ $user->name }}` | Recipient's full name |

Template-specific variables (invoices, services, tickets, etc.) are shown automatically in the **Available Variables** panel when editing a template.

---

## 🔒 Notes

- **Bulk sending uses the queue worker** — ensure `php artisan queue:work` is running (or use Supervisor)
- All emails use your existing Paymenter SMTP configuration — no additional setup required
- The template editor only edits existing Paymenter templates — it does not create new ones
- Bulk campaigns inherit Paymenter's mail settings — if mail is disabled in settings, campaigns will not send

---

## 📄 License

This extension is licensed for use on a **single Paymenter installation** per purchase. Redistribution, resale, or sharing is not permitted.

---

## 💬 Support

Open a conversation on BuiltByBit. Please include:
- Your Paymenter version
- Your PHP version  
- Any relevant error from `storage/logs/laravel.log`

---

*Built with ❤️ for the Paymenter community.*
