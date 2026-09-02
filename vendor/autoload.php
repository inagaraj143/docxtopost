<?php
/**
 * Composer autoloader stub for DocxToWP.
 *
 * This file is a placeholder. To install the full Mammoth dependency
 * for best .docx parsing quality, run from the plugin directory:
 *
 *   composer install --no-dev --optimize-autoloader
 *
 * Without Mammoth installed, the plugin falls back to its built-in
 * ZipArchive + XML parser, which handles most formatting correctly.
 *
 * @package AutoFormatPost
 */

defined( 'ABSPATH' ) || exit;

// If a real Composer autoload has been generated, load it.
$real_autoload = __DIR__ . '/composer/autoload_real.php';
if ( file_exists( $real_autoload ) ) {
	require_once $real_autoload;
}

