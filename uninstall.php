<?php
/**
 * Uninstall AAWEB Advanced Image Resizer.
 *
 * @package AAWEB_Advanced_Image_Resizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings. Do not delete media files, attachments, or backup metadata.
delete_option( 'aaweb_air_settings' );
delete_site_option( 'aaweb_air_settings' );
