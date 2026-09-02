<?php
/**
 * Runs when the plugin is deleted from WordPress.
 * Removes all plugin options and temp files.
 *
 * @package DocxToPost
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete all plugin options.
$options = array(
	'dtpost_default_post_type',
	'dtpost_default_category',
	'dtpost_default_status',
	'dtpost_max_upload_mb',
	'dtpost_content_format',
	'dtpost_allowed_roles',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove per-user state: the conversion counter and dismissed notices.
if ( function_exists( 'delete_metadata' ) ) {
	delete_metadata( 'user', 0, 'dtpost_conversion_count', '', true );
	delete_metadata( 'user', 0, 'dtpost_dismissed_notices', '', true );
}

// Remove temp directory — use wp_upload_dir() for dynamic path.
$upload_dir = wp_upload_dir();
$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'dtpost-temp/';

if ( is_dir( $temp_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $temp_dir, true, 'd' );
	} else {
		// Fallback if WP_Filesystem fails to initialize (e.g. requires FTP credentials).
		$files = glob( $temp_dir . '*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $temp_dir );
	}
}
