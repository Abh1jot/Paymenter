# CategorySort — Paymenter Extension

**Author:** Azion Cloud  
**Version:** 1.0.0  
**Requires:** Paymenter ≥ 0.9 (any version with Filament admin panel)

---

## What it does

Adds a **drag-and-drop "Category Order"** page under **Administration** in the Paymenter admin panel.

By default, Paymenter shows categories in the order they were created (by `id`). After installing this extension, you can drag categories up and down to control which appear first in the client storefront.

---

## Installation

1. Copy the `CategorySort/` folder into `extensions/Others/`.
2. In the admin panel go to **Extensions → Others** and click **Install** next to *Category Sort*.
3. Go to **Administration → Category Order** and drag categories into your preferred order.

---

## How it works

- The `categories` table already has a `sort` column (added in Paymenter's core migration).
- On the public storefront (any non-admin request), a global Eloquent scope applies `ORDER BY COALESCE(sort, 9999) ASC`, so categories without a sort value fall to the bottom.
- In the admin panel, Filament's built-in `->reorderable('sort')` handles the drag-and-drop and writes the new sort values automatically.

---

## Notes

- **No database migration needed** — the `sort` column already exists in the core schema.
- Category reordering also affects subcategory tab order on pages that use `$childCategories`.
- Works alongside the **HideCategories** extension without conflict.
