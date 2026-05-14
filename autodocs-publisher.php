<?php
/**
 * Plugin Name: AutoDocs Publisher
 * Description: Sync Google Drive / Google Docs content into WordPress posts with change tracking.
 * Version: 0.4.0
 * Author: M. Ayoub Khan
 * Author URI:  https://mayoub.dev
 * Requires at least: 6.2
 * Requires PHP: 7.8
 * Text Domain: autodocs-publisher
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTODOCS_PUBLISHER_VERSION', '0.4.0');
define('AUTODOCS_PUBLISHER_FILE', __FILE__);
define('AUTODOCS_PUBLISHER_DIR', plugin_dir_path(__FILE__));
define('AUTODOCS_PUBLISHER_URL', plugin_dir_url(__FILE__));

require_once AUTODOCS_PUBLISHER_DIR . 'includes/class-autodocs-plugin.php';

register_activation_hook(__FILE__, array('AutoDocs_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('AutoDocs_Plugin', 'deactivate'));

add_action('plugins_loaded', array('AutoDocs_Plugin', 'instance'));