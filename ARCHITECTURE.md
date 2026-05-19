# AutoDocs Publisher — architecture

WordPress plugin that imports Google Docs from Drive folders into posts and keeps them in sync.

## Bootstrap

`autodocs-publisher.php` loads `includes/class-autodocs-plugin.php`, which wires settings, Google OAuth, sync, cron, and admin.

Shared helpers load first via `includes/autodocs-autoload.php` (bucket keys, Drive file picking, datetime/timezone).

## Sync pipeline

| Layer | Class | Role |
|-------|--------|------|
| Facade | `AutoDocs_Sync_Service` | Public API for admin and cron |
| Import | `AutoDocs_Sync_Import` | Manual/cron import from a Drive article folder |
| Engine | `AutoDocs_Sync_Engine` | Re-sync existing posts (skips **New** bucket) |
| Repository | `AutoDocs_Sync_Repository` | Post ↔ folder meta queries |
| Catalog / media / status | `AutoDocs_Sync_Catalog`, `_Media`, `_Status_Manager` | Lists, attachments, status labels |

**Buckets:** `folder_new` (articles awaiting import) and `folder_synced` (published/moved). Keys `new` / `synced` are normalized in `AutoDocs_Bucket_Keys`.

**Cron:** `AutoDocs_Cron_Runner` imports up to 10 unlinked folders from **New**, then `sync_all()` for **Synced** only. Due jobs also run on `init` when site traffic wakes PHP.

## Cron modules

- `AutoDocs_Cron_Schedule` — WP cron intervals and next-run timestamps
- `AutoDocs_Cron_Runner` — lock, import, sync, last-run option
- `AutoDocs_Cron_Timezone` — site timezone labels for UI
- `AutoDocs_Cron` — thin facade for backward compatibility

## Admin JS

Modular scripts under `assets/js/autodocs-admin-*.js`; `autodocs-admin-cron.js` handles automatic sync settings preview and status polling.

## Key options

- `autodocs_publisher_settings` — Drive root, buckets, cron, ACF field
- `autodocs_last_cron_run` — last successful automatic sync (site time)
