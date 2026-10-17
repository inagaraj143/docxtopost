<?php
/**
 * The layout-preserving import advert, and why it cannot be turned on.
 *
 *   php tests/test-layout-promo.php
 *
 * Layout-preserving import is a Pro feature. This plugin draws the control
 * for it so that people can see what the paid version does, in the place
 * where the paid version does it, and that raises a fair question: what
 * happens when somebody opens the browser inspector, deletes `disabled` and
 * ticks the box?
 *
 * Nothing, for three separate reasons, and this file checks all three
 * because a claim like that is worth more when it is measured.
 *
 *   1. The input has no `name`. A form control without one is not
 *      "successful" in the HTML specification's sense and is never included
 *      in a submission, whatever its disabled state.
 *   2. Nothing in this plugin reads such a field. The upload handler reads a
 *      known, short list of POST keys and no layout key is among them.
 *   3. There is no layout importer here to call. The classes that do the
 *      work live in the Pro plugin, which is a separate download.
 *
 * The third is the one that matters. The first two are checks, and a check
 * is something that can be got around; the third is an absence of
 * capability, which is not.
 *
 * @package DocxToPost
 */

define( 'ABSPATH', true );

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_url( $s ) { return (string) $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
	function __( $s, $d = null ) { return $s; }
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
	function add_query_arg( $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-58s %s%s\n", $label, $ok ? 'ok' : 'FAIL', '' !== $extra ? '  ' . $extra : '' );
	$ok ? $pass++ : $fail++;
}

$root = dirname( __DIR__ );
$main = (string) file_get_contents( $root . '/docxtopost.php' );

/*
 * The renderer is defined in the plugin's main file, which cannot be loaded
 * here: it bootstraps WordPress hooks on include. The function is lifted out
 * on its own instead, which is enough to render it and is honest about what
 * is being tested, namely the markup.
 */
if ( preg_match( '/^function dtpost_pro_url.*?^\}/ms', $main, $m ) ) {
	eval( $m[0] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}
if ( preg_match( '/^function dtpost_render_layout_promo.*?^\}/ms', $main, $m ) ) {
	eval( $m[0] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}

check( 'the advert renders', function_exists( 'dtpost_render_layout_promo' ) );

if ( ! function_exists( 'dtpost_render_layout_promo' ) ) {
	printf( "\n  %d passed, %d failed.\n\n", $pass, $fail + 1 );
	exit( 1 );
}

ob_start();
dtpost_render_layout_promo( 'upload', 'layout-upload' );
$upload_html = (string) ob_get_clean();

ob_start();
dtpost_render_layout_promo( 'preview', 'layout-preview' );
$preview_html = (string) ob_get_clean();

// ── 1. The control cannot be submitted ────────────────────────────────────

echo "\nThe control itself\n\n";

check( 'it looks like the Pro import-mode option', str_contains( $upload_html, 'Import Mode' )
	&& str_contains( $upload_html, 'Preserve the page layout' ) );
check( 'it is marked as a Pro feature', str_contains( $upload_html, 'dtpost-pro-card__badge' )
	&& str_contains( $upload_html, '>Pro<' ) );
check( 'and links to the upgrade page', str_contains( $upload_html, 'docxtowp.com' )
	&& str_contains( $upload_html, 'utm_medium=layout-upload' ) );

check( 'the checkbox is disabled', (bool) preg_match( '/<input type="checkbox"[^>]*\bdisabled\b/', $upload_html ) );
check( 'and says so to a screen reader', str_contains( $upload_html, 'aria-disabled="true"' ) );
check( 'and is out of the tab order', str_contains( $upload_html, 'tabindex="-1"' ) );

/*
 * The one that survives an inspector. Everything above can be deleted in a
 * browser in a second; this cannot be added back by deleting anything.
 */
preg_match_all( '/<input\b[^>]*>/', $upload_html, $inputs );
$named = array_filter( $inputs[0], static fn( $tag ) => (bool) preg_match( '/\bname\s*=/', $tag ) );

check( 'the checkbox has no name, so no browser will submit it',
	1 === count( $inputs[0] ) && array() === $named,
	count( $inputs[0] ) . ' inputs, ' . count( $named ) . ' with a name' );

// ── 2. Nothing reads such a field ─────────────────────────────────────────

echo "\nWhat happens if one is posted anyway\n\n";

/*
 * Every POST key this plugin reads, anywhere. The list is short enough to
 * print, which is the point: there is nowhere for a layout flag to hide.
 */
preg_match_all( "/\\\$_POST\s*\[\s*'([a-z0-9_]+)'/i", $main, $keys );
$post_keys = array_values( array_unique( $keys[1] ) );
sort( $post_keys );

$layout_keys = array_filter(
	$post_keys,
	static fn( $k ) => str_contains( strtolower( $k ), 'layout' ) || str_contains( strtolower( $k ), 'preserve' )
);

check( 'no POST key the plugin reads mentions layout', array() === $layout_keys,
	implode( ', ', $layout_keys ) ?: 'none of ' . count( $post_keys ) );
printf( "    reads: %s\n", implode( ', ', $post_keys ) );

// The same for the whole package, not just the main file: an admin page or
// an include could read one too.
$leaks = array();
foreach ( glob( $root . '/{*.php,admin/*.php,includes/*.php}', GLOB_BRACE ) ?: array() as $file ) {
	$body = (string) file_get_contents( $file );
	if ( preg_match( "/\\\$_(POST|REQUEST|GET)\s*\[\s*'[a-z0-9_]*(layout|preserve)[a-z0-9_]*'/i", $body ) ) {
		$leaks[] = basename( $file );
	}
}
check( 'and no file anywhere in the plugin reads one', array() === $leaks, implode( ', ', $leaks ) );

// ── 3. There is nothing to call ───────────────────────────────────────────

echo "\nWhat there is to switch on\n\n";

$classes = glob( $root . '/includes/*layout*.php' ) ?: array();
check( 'the plugin ships no layout classes', array() === $classes,
	implode( ', ', array_map( 'basename', $classes ) ) );

$referenced = array();
foreach ( glob( $root . '/{*.php,admin/*.php,includes/*.php}', GLOB_BRACE ) ?: array() as $file ) {
	$body = (string) file_get_contents( $file );
	if ( preg_match( '/\b(DWP_Layout|DTPost_Layout)[A-Za-z_]*\b/', $body, $hit ) ) {
		$referenced[] = basename( $file ) . ': ' . $hit[0];
	}
}
check( 'and names no layout class anywhere', array() === $referenced, implode( ', ', $referenced ) );

// The upload handler converts one way, and the only branch in it is the one
// that tells a Word file from a Markdown one.
if ( preg_match( '/function dtpost_ajax_upload_docx.*?\n\}/s', $main, $handler ) ) {
	check( 'the upload handler has one conversion path per format',
		1 === preg_match_all( '/new DTPost_Parser\(\)/', $handler[0] )
		&& 1 === preg_match_all( '/new DTPost_Markdown\(\)/', $handler[0] ),
		preg_match_all( '/new DTPost_Parser\(\)/', $handler[0] ) . ' parser, '
			. preg_match_all( '/new DTPost_Markdown\(\)/', $handler[0] ) . ' markdown' );
	check( 'and no conditional around which one runs for a .docx',
		! preg_match( '/preserve|layout_mode|import_mode/i', $handler[0] ) );
}

// ── The preview hook ──────────────────────────────────────────────────────

echo "\nThe hook on the preview screen\n\n";

check( 'it says what the free plugin did', str_contains( $preview_html, 'plain formatted text' ) );
check( 'and what Pro would do instead', str_contains( $preview_html, 'columns, background panels and headings' ) );
check( 'it is a banner, not a control', ! str_contains( $preview_html, '<input' )
	&& ! str_contains( $preview_html, '<button' ) );
check( 'it carries its own campaign tag', str_contains( $preview_html, 'utm_medium=layout-preview' ) );
check( 'and it is shown for Word documents only', (bool) preg_match(
	"/'markdown' !== \\( \\\$session\['source'\].*?dtpost_render_layout_promo\( 'preview'/s",
	(string) file_get_contents( $root . '/admin/preview-page.php' )
) );

printf( "\n  %d passed, %d failed.\n\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
