<?php
/**
 * The mentions of Pro's Elementor import, and the rules they have to keep.
 *
 *   php tests/test-elementor-promo.php
 *
 * This plugin has no Elementor import and is not going to. It says so where it
 * helps: on sites that run Elementor, on its own screens, and dismissibly. So
 * what is checked here is mostly what must not happen, per guidelines 5 and 11
 * of the plugin directory: nothing locked, nothing for a form to submit, nothing
 * outside this plugin's own screens, and nothing at all on a site without
 * Elementor except the Upgrade screen's comparison.
 *
 * Same for the review card: one ask, shared with the existing notice.
 *
 * @package DocxToPost
 */

define( 'ABSPATH', true );

$root    = dirname( __DIR__ );
$main    = (string) file_get_contents( $root . '/docxtopost.php' );
$preview = (string) file_get_contents( $root . '/admin/preview-page.php' );
$upload  = (string) file_get_contents( $root . '/admin/upload-page.php' );
$upgrade = (string) file_get_contents( $root . '/admin/upgrade-page.php' );
$js      = (string) file_get_contents( $root . '/assets/admin-v2.js' );
$readme  = (string) file_get_contents( $root . '/readme.txt' );

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-68s %s%s\n", $label, $ok ? 'ok' : 'FAIL', '' !== $extra ? '  ' . $extra : '' );
	$ok ? $pass++ : $fail++;
}

function esc_html_e( $s, $d = null ) { echo htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }

function lift( string $source, string $fn ): string {
	return preg_match( '/function ' . $fn . '\(.*?\r?\n}\r?\n/s', $source, $m ) ? $m[0] : '';
}

foreach ( array( 'dtpost_elementor_active', 'dtpost_elementor_url', 'dtpost_render_elementor_promo', 'dtpost_elementor_notice_key', 'dtpost_render_elementor_notice' ) as $fn ) {
	$code = lift( $main, $fn );
	check( "found $fn", '' !== $code );
	eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test harness, own source.
}

$render = static function ( string $context ): string {
	ob_start();
	dtpost_render_elementor_promo( $context, 'test' );
	return (string) ob_get_clean();
};

echo "\nA site without Elementor\n\n";

check( 'not detected', ! dtpost_elementor_active() );
check( 'the upload screen shows nothing', '' === $render( 'upload' ) );
check( 'the preview screen shows nothing', '' === $render( 'preview' ) );

echo "\nA site running Elementor\n\n";

define( 'ELEMENTOR_VERSION', '4.3.6' );

check( 'detected', dtpost_elementor_active() );

$locked = $render( 'upload' );
check( 'upload screen: names the feature and marks it Pro', str_contains( $locked, 'Build an Elementor layout' ) && str_contains( $locked, 'dtpost-pro-card__badge' ) );
check( 'upload screen: the checkbox is disabled', 1 === preg_match( '/<input[^>]*\bdisabled\b/', $locked ) );
check( 'upload screen: the checkbox has no name, so it can never submit', 0 === preg_match( '/<input[^>]*\bname=/', $locked ) );
check( 'upload screen: links to the guide', str_contains( $locked, 'how-to-import-word-document-into-elementor' ) );

$banner = $render( 'preview' );
check( 'preview screen: a banner, with no form control at all', str_contains( $banner, 'dtpost-pro-banner' ) && ! str_contains( $banner, '<input' ) );
check( 'says it works with free Elementor', str_contains( $banner, 'free Elementor' ) );

ob_start();
dtpost_render_elementor_notice();
$notice = (string) ob_get_clean();
check( 'notice: dismissible', str_contains( $notice, 'is-dismissible' ) );
check( 'notice: carries its key, so the dismissal is remembered', str_contains( $notice, 'data-dtpost-notice="elementor"' ) && str_contains( $notice, 'dtpost-usage-notice' ) );

echo "\nPlugin directory rules\n\n";

$notices = lift( $main, 'dtpost_admin_notices' );
$dismiss = lift( $main, 'dtpost_ajax_dismiss_notice' );

check( 'the notice renders only from the plugin-screens-only hook', 2 === substr_count( $main, 'dtpost_render_elementor_notice(' ) && str_contains( $notices, 'dtpost_render_elementor_notice()' ) );
check( 'that hook still returns early on any other screen', str_contains( $notices, "'docxtowp' !== \$page && strpos(\$page, 'dtpost-') !== 0" ) );
check( 'it only shows where Elementor is running, and until dismissed', str_contains( $notices, 'dtpost_elementor_active() && !dtpost_notice_dismissed(dtpost_elementor_notice_key())' ) );
check( 'Black Friday keeps the one notice slot while it runs', strpos( $notices, 'dtpost_render_bf_notice()' ) < strpos( $notices, 'dtpost_render_elementor_notice()' ) );
check( 'the dismiss handler accepts its key', str_contains( $dismiss, 'dtpost_elementor_notice_key() !== $key' ) );
check( 'no outbound request anywhere in the promo code', ! str_contains( lift( $main, 'dtpost_render_elementor_promo' ) . lift( $main, 'dtpost_elementor_active' ), 'wp_remote_' ) );
check( 'this plugin writes no Elementor layout: nothing locked', ! str_contains( $main, '_elementor_data' ) && ! is_file( $root . '/includes/class-dtpost-elementor.php' ) );

echo "\nWhere it appears\n\n";

check( 'upload screen calls it', str_contains( $upload, "dtpost_render_elementor_promo( 'upload'" ) );
check( 'preview screen: Elementor banner replaces the layout banner', 1 === preg_match( "/if \( dtpost_elementor_active\(\) \) \{\s*dtpost_render_elementor_promo\( 'preview'.*?\} elseif .*?dtpost_render_layout_promo\( 'preview'/s", $preview ) );
check( 'success screen: Elementor card replaces the bulk card', 1 === preg_match( '/if \( dtpost_elementor_active\(\) \) : \?>.*?Editing this in Elementor.*?<\?php else : \?>.*?dtpost-followup--pro.*?<\?php endif; \?>/s', $preview ) );
check( 'Upgrade screen: a pillar and a comparison row', str_contains( $upgrade, 'Import straight into Elementor' ) && str_contains( $upgrade, 'Containers and separate widgets' ) );
check( 'readme: highlighted first under Why upgrade to Pro', 1 === preg_match( '/= Why upgrade to Pro\? =.*?\n\* \*\*Import straight into Elementor\.\*\*/s', $readme ) );
check( 'readme: an honest FAQ answer about the free plugin', str_contains( $readme, '= Does it work with Elementor? =' ) && str_contains( $readme, 'opens in Elementor as one Text Editor widget' ) );
check( 'readme: no elementor tag, since this plugin does not do it', 0 === preg_match( '/^Tags:.*elementor/mi', $readme ) );

echo "\nThe review ask\n\n";

check( 'success card waits for a few imports', str_contains( $preview, "\$dtpost_done >= 3 && ! dtpost_notice_dismissed( 'review' )" ) );
check( 'same key as the existing notice, so one ask', str_contains( $preview, 'data-dtpost-notice="review"' ) && str_contains( $main, "'review' => 5" ) );
check( 'the notice stays off the screen that has the card', str_contains( $notices, "'review' === \$key && \$on_success_screen" ) );
check( 'the link and No thanks both end the ask', 2 === substr_count( $preview, 'data-dtpost-dismiss' ) );
check( 'the script handles them and records it', str_contains( $js, "closest('[data-dtpost-dismiss]')" ) && str_contains( $js, 'remember(card)' ) );
check( 'still records the × on an ordinary notice', str_contains( $js, "closest('.notice-dismiss')" ) && str_contains( $js, 'remember(notice)' ) );
check( 'it asks for an honest review and offers nothing for it', str_contains( $preview, 'short honest review' ) && 0 === preg_match( '/discount|coupon|free month/i', substr( $preview, (int) strpos( $preview, 'The review ask' ), 900 ) ) );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
