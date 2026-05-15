<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="autodocs-import-wizard" class="autodocs-import-wizard" hidden aria-hidden="true">
    <div class="autodocs-import-wizard__backdrop" data-autodocs-wizard-close></div>
    <div class="autodocs-import-wizard__panel" role="dialog" aria-modal="true" aria-labelledby="autodocs-import-wizard-title">
        <header class="autodocs-import-wizard__header">
            <button type="button" class="button-link autodocs-import-wizard__back" data-autodocs-wizard-close>
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                <?php esc_html_e('Back to Articles', 'autodocs-publisher'); ?>
            </button>
            <h2 id="autodocs-import-wizard-title" class="autodocs-import-wizard__title"><?php esc_html_e('Import & Preview', 'autodocs-publisher'); ?></h2>
            <span class="autodocs-import-wizard__sync-hint" id="autodocs-wizard-last-sync"></span>
        </header>

        <ol class="autodocs-import-wizard__stepper" aria-label="<?php esc_attr_e('Import steps', 'autodocs-publisher'); ?>">
            <li class="autodocs-import-wizard__stepper-item is-active" data-wizard-step-indicator="1">
                <span class="autodocs-import-wizard__stepper-num">1</span>
                <span class="autodocs-import-wizard__stepper-label"><?php esc_html_e('Select Articles', 'autodocs-publisher'); ?></span>
            </li>
            <li class="autodocs-import-wizard__stepper-item" data-wizard-step-indicator="2">
                <span class="autodocs-import-wizard__stepper-num">2</span>
                <span class="autodocs-import-wizard__stepper-label"><?php esc_html_e('Preview & Configure', 'autodocs-publisher'); ?></span>
            </li>
            <li class="autodocs-import-wizard__stepper-item" data-wizard-step-indicator="3">
                <span class="autodocs-import-wizard__stepper-num">3</span>
                <span class="autodocs-import-wizard__stepper-label"><?php esc_html_e('Confirm & Import', 'autodocs-publisher'); ?></span>
            </li>
        </ol>

        <div class="autodocs-import-wizard__body">
            <section class="autodocs-import-wizard__step is-active" data-wizard-step="1" aria-label="<?php esc_attr_e('Select articles', 'autodocs-publisher'); ?>">
                <p class="description autodocs-import-wizard__step-intro"><?php esc_html_e('Select articles from the list below, then continue to preview and configure import settings.', 'autodocs-publisher'); ?></p>
                <div class="autodocs-import-wizard__select-toolbar">
                    <label class="autodocs-import-wizard__select-all-label">
                        <input type="checkbox" id="autodocs-wizard-select-all-page" />
                        <?php esc_html_e('Select all on this page', 'autodocs-publisher'); ?>
                    </label>
                    <input type="search" class="regular-text" id="autodocs-wizard-search" placeholder="<?php esc_attr_e('Search articles…', 'autodocs-publisher'); ?>" />
                </div>
                <div id="autodocs-wizard-select-list" class="autodocs-import-wizard__select-list"></div>
            </section>

            <section class="autodocs-import-wizard__step" data-wizard-step="2" hidden aria-label="<?php esc_attr_e('Preview and configure', 'autodocs-publisher'); ?>">
                <div class="autodocs-import-wizard__columns">
                    <aside class="autodocs-import-wizard__col autodocs-import-wizard__col--selected">
                        <div class="autodocs-import-wizard__col-head">
                            <h3 id="autodocs-wizard-selected-title"><?php esc_html_e('Selected Articles', 'autodocs-publisher'); ?></h3>
                            <button type="button" class="button-link" id="autodocs-wizard-clear-selected"><?php esc_html_e('Clear All', 'autodocs-publisher'); ?></button>
                        </div>
                        <p class="description" id="autodocs-wizard-selected-count"></p>
                        <ul id="autodocs-wizard-selected-nav" class="autodocs-import-wizard__selected-nav"></ul>
                    </aside>

                    <div class="autodocs-import-wizard__col autodocs-import-wizard__col--preview">
                        <div class="autodocs-import-wizard__preview-head">
                            <div>
                                <h3 id="autodocs-wizard-preview-title"></h3>
                                <p class="description" id="autodocs-wizard-preview-path"></p>
                            </div>
                            <div class="autodocs-import-wizard__preview-nav">
                                <button type="button" class="button" id="autodocs-wizard-prev-article" aria-label="<?php esc_attr_e('Previous article', 'autodocs-publisher'); ?>">&lsaquo;</button>
                                <button type="button" class="button" id="autodocs-wizard-next-article" aria-label="<?php esc_attr_e('Next article', 'autodocs-publisher'); ?>">&rsaquo;</button>
                            </div>
                        </div>
                        <div class="autodocs-import-wizard__tabs" role="tablist">
                            <button type="button" role="tab" class="autodocs-import-wizard__tab is-active" data-wizard-preview-tab="content" aria-selected="true"><?php esc_html_e('Content Preview', 'autodocs-publisher'); ?></button>
                            <button type="button" role="tab" class="autodocs-import-wizard__tab" data-wizard-preview-tab="meta" aria-selected="false"><?php esc_html_e('Metadata (META)', 'autodocs-publisher'); ?></button>
                            <button type="button" role="tab" class="autodocs-import-wizard__tab" data-wizard-preview-tab="images" aria-selected="false"><?php esc_html_e('Images & Files', 'autodocs-publisher'); ?></button>
                            <button type="button" role="tab" class="autodocs-import-wizard__tab" data-wizard-preview-tab="drive" aria-selected="false"><?php esc_html_e('Drive Info', 'autodocs-publisher'); ?></button>
                        </div>
                        <div id="autodocs-wizard-meta-notice" class="notice notice-info inline" hidden></div>
                        <div class="autodocs-import-wizard__preview-panels">
                            <div class="autodocs-import-wizard__preview-panel is-active" data-wizard-preview-panel="content">
                                <div class="autodocs-import-wizard__preview-layout">
                                    <div id="autodocs-wizard-content-html" class="autodocs-import-wizard__content-html"></div>
                                    <dl id="autodocs-wizard-doc-stats" class="autodocs-import-wizard__doc-stats"></dl>
                                </div>
                            </div>
                            <div class="autodocs-import-wizard__preview-panel" data-wizard-preview-panel="meta" hidden>
                                <dl id="autodocs-wizard-meta-dl" class="autodocs-meta-dl"></dl>
                            </div>
                            <div class="autodocs-import-wizard__preview-panel" data-wizard-preview-panel="images" hidden>
                                <div id="autodocs-wizard-images-panel"></div>
                            </div>
                            <div class="autodocs-import-wizard__preview-panel" data-wizard-preview-panel="drive" hidden>
                                <dl id="autodocs-wizard-drive-dl" class="autodocs-meta-dl"></dl>
                            </div>
                        </div>
                    </div>

                    <aside class="autodocs-import-wizard__col autodocs-import-wizard__col--options">
                        <h3><?php esc_html_e('Import Options', 'autodocs-publisher'); ?></h3>
                        <div id="autodocs-wizard-options" class="autodocs-import-wizard__options"></div>
                    </aside>
                </div>
            </section>

            <section class="autodocs-import-wizard__step" data-wizard-step="3" hidden aria-label="<?php esc_attr_e('Confirm import', 'autodocs-publisher'); ?>">
                <p class="description"><?php esc_html_e('Review your selection and import settings, then import to WordPress.', 'autodocs-publisher'); ?></p>
                <div id="autodocs-wizard-confirm-summary" class="autodocs-import-wizard__confirm"></div>
                <div id="autodocs-wizard-import-progress" class="autodocs-import-wizard__progress" hidden></div>
            </section>
        </div>

        <footer class="autodocs-import-wizard__footer">
            <span id="autodocs-wizard-footer-count" class="autodocs-import-wizard__footer-count"></span>
            <div class="autodocs-import-wizard__footer-actions">
                <button type="button" class="button" data-autodocs-wizard-close><?php esc_html_e('Cancel', 'autodocs-publisher'); ?></button>
                <button type="button" class="button" id="autodocs-wizard-btn-back" hidden><?php esc_html_e('Back', 'autodocs-publisher'); ?></button>
                <button type="button" class="button button-primary" id="autodocs-wizard-btn-next"><?php esc_html_e('Continue to Preview', 'autodocs-publisher'); ?></button>
            </div>
        </footer>
    </div>
</div>
