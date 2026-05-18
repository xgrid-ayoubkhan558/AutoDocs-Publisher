<?php
/**
 * Automatic sync settings panel (included from settings-page.php).
 *
 * @var bool   $cron_enabled
 * @var string $cron_interval
 * @var string $cron_time
 * @var bool   $cron_show_time
 * @var string $cron_timezone_label
 * @var string $cron_site_time_now
 * @var string $cron_general_settings_url
 * @var int    $cron_next_run_ts
 * @var string $cron_next_run_relative
 * @var string $cron_last_run
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<section
    class="autodocs-cron-settings"
    id="autodocs-cron-settings"
    data-timezone="<?php echo esc_attr(AutoDocs_Cron::timezone_for_intl()); ?>"
    data-timezone-label="<?php echo esc_attr($cron_timezone_label); ?>"
    data-gmt-offset="<?php echo esc_attr((string) AutoDocs_Cron::site_gmt_offset_hours()); ?>"
    data-next-ts="<?php echo esc_attr((string) $cron_next_run_ts); ?>"
>
    <h3 class="autodocs-cron-settings__title"><?php esc_html_e('Automatic sync', 'autodocs-publisher'); ?></h3>
    <p class="description"><?php esc_html_e('Refresh modified status and update Synced bucket posts from Drive.', 'autodocs-publisher'); ?></p>

    <p class="autodocs-cron-settings__controls">
        <label class="autodocs-cron-settings__enable">
            <input type="checkbox" id="autodocs-cron-enabled" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_enabled]" value="1" <?php checked($cron_enabled); ?> />
            <?php esc_html_e('Enable', 'autodocs-publisher'); ?>
        </label>
        <label for="autodocs-cron-interval"><?php esc_html_e('Every', 'autodocs-publisher'); ?></label>
        <select id="autodocs-cron-interval" name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_interval]">
            <?php foreach (AutoDocs_Cron::interval_labels() as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($cron_interval, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="autodocs-cron-settings__at" id="autodocs-cron-time-row"<?php echo $cron_show_time ? '' : ' hidden'; ?>>
            <label for="autodocs-cron-time"><?php esc_html_e('at', 'autodocs-publisher'); ?></label>
            <input
                type="time"
                id="autodocs-cron-time"
                name="<?php echo esc_attr(AutoDocs_Settings::OPTION_NAME); ?>[cron_time]"
                value="<?php echo esc_attr($cron_time); ?>"
                step="60"
            />
        </span>
    </p>

    <dl class="autodocs-cron-settings__clocks">
        <div class="autodocs-cron-settings__clock">
            <dt><?php esc_html_e('Your computer', 'autodocs-publisher'); ?></dt>
            <dd id="autodocs-cron-computer-now" aria-live="polite">&mdash;</dd>
        </div>
        <div class="autodocs-cron-settings__clock">
            <dt><?php esc_html_e('WordPress site', 'autodocs-publisher'); ?></dt>
            <dd>
                <span id="autodocs-cron-site-now"><?php echo esc_html($cron_site_time_now); ?></span>
                <a class="autodocs-cron-settings__tz-link" href="<?php echo esc_url($cron_general_settings_url); ?>"><?php esc_html_e('Timezone', 'autodocs-publisher'); ?></a>
            </dd>
        </div>
    </dl>

    <dl class="autodocs-cron-settings__meta" id="autodocs-cron-next-block"<?php echo $cron_enabled ? '' : ' hidden'; ?>>
        <div class="autodocs-cron-settings__clock">
            <dt>
                <?php esc_html_e('Next run', 'autodocs-publisher'); ?>
                <span class="autodocs-cron-settings__relative" id="autodocs-cron-next-run-relative"<?php echo $cron_next_run_relative === '' ? ' hidden' : ''; ?>><?php echo esc_html($cron_next_run_relative); ?></span>
            </dt>
            <dd class="autodocs-cron-settings__next-grid">
                <span class="autodocs-cron-settings__next-item">
                    <span class="autodocs-cron-settings__next-label"><?php esc_html_e('WordPress', 'autodocs-publisher'); ?></span>
                    <span id="autodocs-cron-next-run-site"><?php echo $cron_next_run_ts > 0 ? esc_html(AutoDocs_Cron::format_timestamp($cron_next_run_ts)) : '&mdash;'; ?></span>
                </span>
                <span class="autodocs-cron-settings__next-item">
                    <span class="autodocs-cron-settings__next-label"><?php esc_html_e('Computer', 'autodocs-publisher'); ?></span>
                    <span id="autodocs-cron-next-run-local">&mdash;</span>
                </span>
            </dd>
        </div>
        <div class="autodocs-cron-settings__clock">
            <dt><?php esc_html_e('Last run', 'autodocs-publisher'); ?></dt>
            <dd id="autodocs-cron-last-run"><?php echo $cron_last_run !== '' ? esc_html($cron_last_run) : esc_html__('Never', 'autodocs-publisher'); ?></dd>
        </div>
    </dl>

    <p class="description autodocs-cron-settings__footnote" id="autodocs-cron-footnote"><?php esc_html_e('Save settings to apply interval changes. This page checks for due runs while open.', 'autodocs-publisher'); ?></p>
</section>
