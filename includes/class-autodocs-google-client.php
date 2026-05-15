<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoDocs_Google_Client
{
    /** Full Drive access required to move article folders (e.g. New → Synced), list files, export. */
    const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive';
    const DOCS_SCOPE = 'https://www.googleapis.com/auth/documents.readonly';
    /** Shown in admin sidebar; requires reconnect if added after user first connected. */
    const USERINFO_EMAIL_SCOPE = 'https://www.googleapis.com/auth/userinfo.email';

    /**
     * Bump when OAuth scope string changes so existing tokens prompt reconnect.
     */
    const OAUTH_SCOPE_VERSION = 3;

    private $settings;

    public function __construct(AutoDocs_Settings $settings)
    {
        $this->settings = $settings;
    }

    // -------------------------------------------------------------------------
    // OAuth
    // -------------------------------------------------------------------------

    /**
     * @param string $state Random opaque value stored in a transient before redirect.
     */
    public function auth_url($state)
    {
        $client_id = $this->settings->get('client_id');
        $redirect_uri = $this->settings->redirect_uri();

        $scope = implode(
            ' ',
            array(self::DOCS_SCOPE, self::DRIVE_SCOPE, self::USERINFO_EMAIL_SCOPE)
        );

        return add_query_arg(array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ), 'https://accounts.google.com/o/oauth2/v2/auth');
    }

    /**
     * Exchange authorization code for tokens (redirect_uri must match the auth request).
     */
    public function exchange_authorization_code($code)
    {
        $code = is_string($code) ? $code : '';

        if ($code === '') {
            return new WP_Error('autodocs_oauth_invalid', __('Missing authorization code.', 'autodocs-publisher'));
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body' => array(
                'code' => $code,
                'client_id' => $this->settings->get('client_id'),
                'client_secret' => $this->settings->get('client_secret'),
                'redirect_uri' => $this->settings->redirect_uri(),
                'grant_type' => 'authorization_code',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status > 299 || !is_array($body)) {
            $error_desc = isset($body['error_description']) ? $body['error_description'] : __('Invalid response from Google token endpoint.', 'autodocs-publisher');
            return new WP_Error('autodocs_oauth_token', $error_desc);
        }

        if (empty($body['access_token'])) {
            $error_desc = isset($body['error_description']) ? $body['error_description'] : __('No access token returned.', 'autodocs-publisher');
            return new WP_Error('autodocs_oauth_token', $error_desc);
        }

        $existing = $this->settings->get_tokens();

        if (empty($body['refresh_token']) && !empty($existing['refresh_token'])) {
            $body['refresh_token'] = $existing['refresh_token'];
        }

        $fingerprint = $this->settings->credentials_fingerprint();
        $merged = array_merge($existing, $body, array(
            'created_at' => time(),
            'credentials_fingerprint' => $fingerprint,
            'oauth_scope_version' => self::OAUTH_SCOPE_VERSION,
        ));

        if (empty($merged['refresh_token'])) {
            return new WP_Error(
                'autodocs_no_refresh',
                __('Google did not return a refresh token. Revoke this app under your Google Account security settings, then connect again.', 'autodocs-publisher')
            );
        }

        $this->settings->update_tokens($merged);

        return true;
    }

    public function disconnect()
    {
        $this->settings->clear_tokens();
    }

    public function is_connected()
    {
        if ($this->settings->get('client_id') === '' || $this->settings->get('client_secret') === '') {
            return false;
        }

        $tokens = $this->settings->get_tokens();

        if (empty($tokens['refresh_token'])) {
            return false;
        }

        $current = $this->settings->credentials_fingerprint();

        if ($current === '') {
            return false;
        }

        if (!empty($tokens['credentials_fingerprint'])) {
            return hash_equals($tokens['credentials_fingerprint'], $current);
        }

        $tokens['credentials_fingerprint'] = $current;
        $this->settings->update_tokens($tokens);

        return true;
    }

    // -------------------------------------------------------------------------
    // Drive file / folder listing
    // -------------------------------------------------------------------------

    /**
     * List all sub-folders inside a given parent (default: My Drive root).
     */
    public function list_folders($parent_id = 'root')
    {
        $query = sprintf(
            "'%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            str_replace("'", "\\'", $parent_id)
        );
        $url = add_query_arg(array(
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 1000,
            'orderBy' => 'name',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ), 'https://www.googleapis.com/drive/v3/files');

        $response = $this->request('GET', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['files']) && is_array($response['files']) ? $response['files'] : array();
    }

    /**
     * List Google Docs inside a folder.
     */
    public function list_docs_in_folder($folder_id)
    {
        $query = sprintf(
            "'%s' in parents and mimeType='application/vnd.google-apps.document' and trashed=false",
            str_replace("'", "\\'", $folder_id)
        );
        $url = add_query_arg(array(
            'q' => $query,
            'fields' => 'files(id,name,modifiedTime,parents,webViewLink)',
            'pageSize' => 1000,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ), 'https://www.googleapis.com/drive/v3/files');

        $response = $this->request('GET', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['files']) && is_array($response['files']) ? $response['files'] : array();
    }

    /**
     * List all files (any type) directly inside a folder — used to find
     * per-post sub-folders and their contents (doc + featured image).
     */
    public function list_files_in_folder($folder_id)
    {
        $query = sprintf(
            "'%s' in parents and trashed=false",
            str_replace("'", "\\'", $folder_id)
        );
        $url = add_query_arg(array(
            'q' => $query,
            'fields' => 'files(id,name,mimeType,modifiedTime,size,thumbnailLink,webViewLink,webContentLink)',
            'pageSize' => 1000,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ), 'https://www.googleapis.com/drive/v3/files');

        $response = $this->request('GET', $url);
        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['files']) && is_array($response['files']) ? $response['files'] : array();
    }

    /**
     * Fetch metadata for a single file/folder.
     */
    public function get_file_meta($file_id)
    {
        $url = add_query_arg(
            array(
                'fields' => 'id,name,mimeType,modifiedTime,parents,webViewLink',
                'supportsAllDrives' => 'true',
            ),
            sprintf('https://www.googleapis.com/drive/v3/files/%s', rawurlencode($file_id))
        );
        return $this->request('GET', $url);
    }

    /**
     * Move a Drive file or folder under a new parent (removed from previous parent).
     *
     * @param string $file_id
     * @param string $new_parent_id
     * @return true|WP_Error
     */
    public function move_file_to_parent($file_id, $new_parent_id)
    {
        $file_id = is_string($file_id) ? trim($file_id) : '';
        $new_parent_id = is_string($new_parent_id) ? trim($new_parent_id) : '';

        if ($file_id === '' || $new_parent_id === '') {
            return new WP_Error('autodocs_drive_move', __('Missing folder or target.', 'autodocs-publisher'));
        }

        $meta = $this->get_file_meta($file_id);
        if (is_wp_error($meta)) {
            return $meta;
        }

        if (empty($meta['parents']) || ! is_array($meta['parents'])) {
            return new WP_Error('autodocs_drive_parents', __('Could not read Drive item parents.', 'autodocs-publisher'));
        }

        $remove = implode(',', $meta['parents']);
        $url = sprintf(
            'https://www.googleapis.com/drive/v3/files/%s?addParents=%s&removeParents=%s&supportsAllDrives=true',
            rawurlencode($file_id),
            rawurlencode($new_parent_id),
            rawurlencode($remove)
        );

        $result = $this->request_raw('PATCH', $url, '{}');
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Docs export
    // -------------------------------------------------------------------------

    public function export_doc_html($file_id)
    {
        $url = sprintf(
            'https://www.googleapis.com/drive/v3/files/%s/export?mimeType=%s',
            rawurlencode($file_id),
            rawurlencode('text/html')
        );
        return $this->request_raw('GET', $url);
    }

    /**
     * Structured Google Doc (preserves paragraph line breaks for META blocks).
     *
     * @param string $file_id Drive file ID of the Google Doc.
     * @return array<string, mixed>|WP_Error
     */
    public function get_document($file_id)
    {
        $file_id = is_string($file_id) ? trim($file_id) : '';
        if ($file_id === '') {
            return new WP_Error('autodocs_invalid_doc', __('Missing Google Doc ID.', 'autodocs-publisher'));
        }

        $url = sprintf('https://docs.googleapis.com/v1/documents/%s', rawurlencode($file_id));

        return $this->request('GET', $url);
    }

    /**
     * Download raw file bytes (for images stored in Drive).
     */
    public function download_file($file_id)
    {
        $url = sprintf(
            'https://www.googleapis.com/drive/v3/files/%s?alt=media',
            rawurlencode($file_id)
        );
        return $this->request_raw('GET', $url);
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    private function request($method, $url)
    {
        $response = $this->request_raw($method, $url);
        if (is_wp_error($response)) {
            return $response;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function request_raw($method, $url, $body = null)
    {
        $access_token = $this->access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
        );

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => $headers,
        );

        if (is_string($body) && $body !== '') {
            $args['body'] = $body;
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code > 299) {
            $body = wp_remote_retrieve_body($response);
            $parsed = json_decode($body, true);
            $msg = isset($parsed['error']['message']) ? $parsed['error']['message'] : $body;
            if (is_string($msg) && stripos($msg, 'insufficient authentication scopes') !== false) {
                return new WP_Error(
                    'autodocs_insufficient_scope',
                    __('Google reported insufficient permissions for this action. Open the Settings tab, disconnect Google, then connect again. In Google Cloud Console, ensure the OAuth client includes Drive (full) and Docs read-only scopes.', 'autodocs-publisher'),
                    array('status' => $code)
                );
            }
            return new WP_Error('autodocs_google_api_error', $msg, array('status' => $code));
        }

        return wp_remote_retrieve_body($response);
    }

    private function access_token()
    {
        $tokens = $this->settings->get_tokens();
        $has_refresh = !empty($tokens['refresh_token']);

        if (empty($tokens['access_token'])) {
            if (!$has_refresh) {
                return new WP_Error('autodocs_not_connected', __('Google account is not connected.', 'autodocs-publisher'));
            }

            $refreshed = $this->refresh_token($tokens['refresh_token']);
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }
            $tokens = $this->settings->get_tokens();
        }

        $expires_in = isset($tokens['expires_in']) ? (int) $tokens['expires_in'] : 3600;
        $created_at = isset($tokens['created_at']) ? (int) $tokens['created_at'] : 0;

        if ($has_refresh && time() >= ($created_at + $expires_in - 60)) {
            $refreshed = $this->refresh_token($tokens['refresh_token']);
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }
            $tokens = $this->settings->get_tokens();
        }

        return $tokens['access_token'];
    }

    private function refresh_token($refresh_token)
    {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body' => array(
                'client_id' => $this->settings->get('client_id'),
                'client_secret' => $this->settings->get('client_secret'),
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            return new WP_Error('autodocs_refresh_failed', __('Could not refresh Google access token.', 'autodocs-publisher'));
        }

        $existing = $this->settings->get_tokens();
        $fingerprint = $this->settings->credentials_fingerprint();
        $this->settings->update_tokens(array_merge($existing, $body, array(
            'created_at' => time(),
            'credentials_fingerprint' => $fingerprint !== '' ? $fingerprint : (isset($existing['credentials_fingerprint']) ? $existing['credentials_fingerprint'] : ''),
            'oauth_scope_version' => isset($existing['oauth_scope_version']) ? (int) $existing['oauth_scope_version'] : 0,
        )));

        return true;
    }

    /**
     * Tokens issued before a scope bump need a full reconnect (Disconnect → Connect).
     */
    public function tokens_need_reconnect_for_scopes()
    {
        if (! $this->is_connected()) {
            return false;
        }

        $tokens = $this->settings->get_tokens();
        $v = isset($tokens['oauth_scope_version']) ? (int) $tokens['oauth_scope_version'] : 0;

        return $v < self::OAUTH_SCOPE_VERSION;
    }

    /**
     * Google account email (requires USERINFO_EMAIL_SCOPE on the token).
     *
     * @return string|WP_Error
     */
    public function fetch_user_email()
    {
        $access_token = $this->access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code > 299 || ! is_array($body) || empty($body['email'])) {
            return new WP_Error('autodocs_userinfo', __('Could not read Google account email.', 'autodocs-publisher'));
        }

        return (string) $body['email'];
    }
}