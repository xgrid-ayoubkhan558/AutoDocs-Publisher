<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Drive bucket keys used in settings and import/sync flows.
 */
final class AutoDocs_Bucket_Keys
{
    public const NEW = 'new';
    public const SYNCED = 'synced';

    /**
     * @param string $bucket_key Raw key from request or settings.
     * @return string "new" or "synced"
     */
    public static function normalize($bucket_key)
    {
        $bucket_key = sanitize_key((string) $bucket_key);
        if ('modified' === $bucket_key) {
            return self::SYNCED;
        }
        if (in_array($bucket_key, array(self::NEW, self::SYNCED), true)) {
            return $bucket_key;
        }

        return self::NEW;
    }

    /**
     * Settings option name for a bucket key (folder_new, folder_synced).
     *
     * @param string $bucket_key
     * @return string
     */
    public static function settings_option_key($bucket_key)
    {
        $map = array(
            self::NEW => 'folder_new',
            self::SYNCED => 'folder_synced',
        );

        return $map[ self::normalize($bucket_key) ];
    }
}
