# AutoDocs Publisher

WordPress plugin that syncs **Google Docs** from **Google Drive** into WordPress posts, with buckets for **New**, **Synced**, and **Modified**, manual import from **New**, and post meta for sync status.

## Requirements

- WordPress 6.2+
- PHP 7.8+
- Google Cloud project with **Google Drive API** enabled
- OAuth 2.0 **Web application** client (Client ID + Client Secret)

## OAuth scopes (register on Google Cloud consent screen)

The plugin requests:

- `https://www.googleapis.com/auth/drive` — list folders, export Docs HTML, move folders between buckets
- `https://www.googleapis.com/auth/documents.readonly` — Docs access paired with Drive export
- `https://www.googleapis.com/auth/userinfo.email` — show the connected account in the admin sidebar

**Redirect URI** (must match exactly in the Google client):

```text
https://YOUR-SITE.com/wp-admin/admin-ajax.php?action=autodocs_google_oauth_callback
```

After changing scopes in the plugin, use **Settings → Disconnect Google → Connect** so Google issues a new refresh token with the updated permissions. If you see *insufficient authentication scopes*, reconnecting fixes it.

## Install

1. Copy this folder into `wp-content/plugins/AutoDocs Publisher` (or your chosen slug).
2. Activate **AutoDocs Publisher** in **Plugins**.
3. Go to **Settings → AutoDocs Publisher**.
4. Enter **Client ID** and **Client Secret**, save, then **Connect Google Drive**.
5. On **Drive & folders**, set **Drive root** (browse picks folder name + ID), then map **New / Synced / Modified** buckets.
6. On **Articles**, refresh lists; under **New**, select a row and use the **Import** sidebar to create or update a post. Optional `[META START]` … `[META END]` block in the Doc sets title, slug, type, status, categories, tags, featured image hint, excerpt.

## Admin UI (BEM)

New and updated markup uses BEM-style blocks such as `autodocs-settings__layout`, `autodocs-sidebar__card`, `autodocs-drive-root__card`, `autodocs-articles-shell`, and `autodocs-articles-table`. Legacy classes like `autodocs-api-card` remain for backwards compatibility.

## Post meta (tracking)

- `_autodocs_google_file_id` — Google Doc file id  
- `_autodocs_google_folder_id` — article folder id  
- `_autodocs_google_modified_time`  
- `_autodocs_sync_status` — `synced` | `new` | `modified` | `missing`  
- `_autodocs_last_synced_time`  
- `_autodocs_content_hash`  
- `_autodocs_source_root_folder` — bucket folder id the article lives under  

## Development

- `assets/js/autodocs-admin-*.js` — modular admin UI (core, Drive picker, buckets, import, init)  
- `assets/admin.css` — layout, sidebar, tables, drive root card  
- `includes/class-autodocs-google-client.php` — OAuth, Drive HTTP, scope version on token exchange  
- `includes/class-autodocs-sync-service.php` — sync, import, `list_bucket_articles_detailed`  
