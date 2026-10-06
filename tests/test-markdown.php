<?php
/**
 * Standalone checks for the Markdown import path.
 *
 * Stubs the handful of WordPress functions the classes touch, so this runs
 * with plain PHP:
 *
 *     php tests/test-markdown.php
 *
 * wp_kses_post() is stubbed with a deliberately crude sanitiser. The point is
 * not to re-test kses. It is to prove the parser routes its output through
 * it, and that the block serializer copes with what comes out.
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── WordPress stubs ─────────────────────────────────────────────────────────

function __( string $text, string $domain = '' ): string { return $text; }
function _n( string $single, string $plural, int $number, string $domain = '' ): string { return 1 === $number ? $single : $plural; }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false ); }
function esc_url( string $url ): string { return $url; }
function wp_strip_all_tags( string $text ): string { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', $text ) ) ); }
function attachment_url_to_postid( string $url ): int { return 0; }
function serialize_block_attributes( array $attrs ): string {
	$json = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return str_replace( [ '--', '<', '>', '&', '\"' ], [ '--', '<', '>', '&', '"' ], $json );
}

$GLOBALS['kses_calls'] = 0;
function wp_kses_post( string $html ): string {
	$GLOBALS['kses_calls']++;
	$html = preg_replace( '@<(script|style|iframe)\b[^>]*>.*?</\1>@si', '', $html );
	$html = preg_replace( '/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $html );
	$html = preg_replace( '/\s+src\s*=\s*"data:[^"]*"/i', '', $html );
	return preg_replace( '/href\s*=\s*"\s*javascript:[^"]*"/i', 'href=""', $html );
}

class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error( mixed $thing ): bool { return $thing instanceof WP_Error; }
function get_option( string $key, $default = false ) { return $default; }

require dirname( __DIR__ ) . '/includes/class-dtpost-title.php';
require dirname( __DIR__ ) . '/includes/lib/Parsedown.php';
require dirname( __DIR__ ) . '/includes/class-dtpost-markdown.php';
require dirname( __DIR__ ) . '/includes/class-dtpost-blocks.php';

// ── Harness ─────────────────────────────────────────────────────────────────

$fail = 0;
$pass = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $fail, $pass;
	printf( "  %-70s %s\n", $label, $ok ? 'ok' : 'FAIL' );
	if ( ! $ok ) {
		$fail++;
		if ( '' !== $detail ) {
			echo "      " . str_replace( "\n", "\n      ", $detail ) . "\n";
		}
	} else {
		$pass++;
	}
}

$md = new DTPost_Markdown();

// ── 1. Title from the first H1, which is then removed ───────────────────────
$r = $md->parse( "Intro paragraph.\n\n# The Real Title\n\nBody text.\n", 'notes.md' );
check( 'H1 anywhere becomes the title',                    'The Real Title' === $r['title'] );
check( 'that H1 is removed from the body',                 ! str_contains( $r['content'], '<h1' ), $r['content'] );
check( 'other paragraphs survive',                         str_contains( $r['content'], '<p>Body text.</p>' ) );

// ── 2. No H1: filename, humanised ───────────────────────────────────────────
$r = $md->parse( "## Getting Started\n\nSome text.", 'my-first_post.v2.md' );
check( 'no H1 → filename in sentence case, no H2 fallback',  'My first post v2' === $r['title'], $r['title'] );
check( 'H2 stays in the body',                             str_contains( $r['content'], '<h2>Getting Started</h2>' ) );

// ── 3. Front matter ─────────────────────────────────────────────────────────
$src = "---\ntitle: \"Quoted Title\"\nslug: ignored\ncategories:\n  - WordPress\n---\n\n# Quoted Title\n\nAfter.\n";
$r   = $md->parse( $src, 'x.md' );
check( 'front matter title wins, quotes stripped',         'Quoted Title' === $r['title'], $r['title'] );
check( 'front matter block is gone from the body',         ! str_contains( $r['content'], 'slug:' ) && ! str_contains( $r['content'], 'categories' ), $r['content'] );
check( 'matching H1 is removed alongside a front matter title', ! str_contains( $r['content'], '<h1' ), $r['content'] );

$r = $md->parse( "---\ntitle: From Meta\n---\n# Different Heading\n\nText.", 'x.md' );
check( 'non-matching H1 is kept when title came from front matter', str_contains( $r['content'], '<h1>Different Heading</h1>' ), $r['content'] );

$r = $md->parse( "Not front matter.\n\n---\n\nAfter a rule.", 'x.md' );
check( '--- mid-document is a horizontal rule, not front matter', str_contains( $r['content'], '<hr' ) && str_contains( $r['content'], 'Not front matter' ), $r['content'] );

// ── 4. Images ───────────────────────────────────────────────────────────────
$src = "# T\n\n![Hero](images/hero.jpg)\n\n![Ext](https://cdn.example.com/x.png)\n\n![Proto](//cdn.example.com/y.png)\n\n![Data](data:image/png;base64,AAAA)\n";
$r   = $md->parse( $src, 'x.md' );
check( 'absolute https image kept',                        str_contains( $r['content'], 'https://cdn.example.com/x.png' ) );
check( 'protocol-relative image kept',                     str_contains( $r['content'], '//cdn.example.com/y.png' ) );
check( 'relative image removed',                           ! str_contains( $r['content'], 'hero.jpg' ), $r['content'] );
check( 'data: image removed',                              ! str_contains( $r['content'], 'data:image' ), $r['content'] );
check( 'paragraph emptied by removal is dropped',          ! preg_match( '/<p>\s*<\/p>/', $r['content'] ), $r['content'] );
check( 'one warning naming both skipped paths',            1 === count( $r['warnings'] ) && str_contains( $r['warnings'][0], 'images/hero.jpg' ) && str_contains( $r['warnings'][0], '2 images' ), implode( "\n", $r['warnings'] ) );

$r = $md->parse( "# T\n\nJust text.", 'x.md' );
check( 'no images → no warnings',                          [] === $r['warnings'] );

// ── 5. Code blocks ──────────────────────────────────────────────────────────
$src = "# T\n\n```php\n<?php echo \"a & b\"; // [shortcode]\n```\n\n    indented\n";
$r   = $md->parse( $src, 'x.md' );
check( 'fenced code becomes <pre><code>',                  str_contains( $r['content'], '<pre><code class="language-php">' ), $r['content'] );
check( 'code content is entity-escaped, not executed',     str_contains( $r['content'], '&lt;?php' ) );
check( 'indented code block too',                          str_contains( $r['content'], '<pre><code>indented</code></pre>' ) );

$blocks = DTPost_Blocks::serialize( $r['content'] );
check( 'serializer emits a core/code block',               str_contains( $blocks, '<!-- wp:code -->' ) && str_contains( $blocks, '<pre class="wp-block-code"><code>' ), $blocks );
check( 'language class dropped from the block',            ! str_contains( $blocks, 'language-php' ), $blocks );
check( '[ escaped so shortcodes never fire',               str_contains( $blocks, '&#91;shortcode]' ), $blocks );
check( 'ampersand double-encoded in code',                 str_contains( $blocks, 'a &amp; b' ), $blocks );
check( 'no HTML fallback block for <pre>',                 ! str_contains( $blocks, '<!-- wp:html -->' ), $blocks );

// ── 6. Tables ───────────────────────────────────────────────────────────────
$src = "# T\n\n| Left | Center | Right |\n|:-----|:------:|------:|\n| a | b | c |\n";
$r   = $md->parse( $src, 'x.md' );
check( 'table has thead/th',                               str_contains( $r['content'], '<thead>' ) && str_contains( $r['content'], '<th' ) );
check( 'alignment rewritten to block-friendly attributes', str_contains( $r['content'], '<th class="has-text-align-center" data-align="center">' ) && str_contains( $r['content'], '<td class="has-text-align-right" data-align="right">' ), $r['content'] );
check( 'no inline style left behind',                      ! str_contains( $r['content'], 'style=' ), $r['content'] );
$blocks = DTPost_Blocks::serialize( $r['content'] );
check( 'serializer emits a core/table block',              str_contains( $blocks, '<!-- wp:table -->' ) && str_contains( $blocks, '<figure class="wp-block-table"><table>' ), $blocks );

// ── 7. Raw HTML goes through kses ───────────────────────────────────────────
$GLOBALS['kses_calls'] = 0;
$src = "# T\n\nSafe <em>inline</em>.\n\n<script>alert(1)</script>\n\n<div onclick=\"x()\">raw</div>\n\n[bad](javascript:alert(1))\n";
$r   = $md->parse( $src, 'x.md' );
check( 'wp_kses_post() is called exactly once',            1 === $GLOBALS['kses_calls'] );
check( '<script> did not reach the output',                ! str_contains( $r['content'], '<script' ), $r['content'] );
check( 'on* attribute did not reach the output',           ! str_contains( $r['content'], 'onclick' ), $r['content'] );
check( 'javascript: href did not reach the output',        ! str_contains( $r['content'], 'javascript:' ), $r['content'] );
check( 'benign inline HTML kept',                          str_contains( $r['content'], '<em>inline</em>' ) );

// ── 8. Inline formatting and lists ──────────────────────────────────────────
$src = "# T\n\n**bold** _em_ ~~gone~~ `code` [link](https://example.com)\n\n- one\n  - nested\n- two\n\n1. first\n2. second\n\n> quoted\n";
$r   = $md->parse( $src, 'x.md' );
foreach ( [ '<strong>bold</strong>', '<em>em</em>', '<del>gone</del>', '<code>code</code>', '<a href="https://example.com">link</a>', '<ol>', '<blockquote>' ] as $needle ) {
	check( "inline/structure: $needle",                     str_contains( $r['content'], $needle ), $r['content'] );
}
check( 'nested list preserved',                            preg_match( '/<li>one\s*<ul>\s*<li>nested<\/li>/s', $r['content'] ) === 1, $r['content'] );
$blocks = DTPost_Blocks::serialize( $r['content'] );
check( 'lists → list blocks, quote → quote block',        str_contains( $blocks, '<!-- wp:list -->' ) && str_contains( $blocks, '<!-- wp:list {"ordered":true} -->' ) && str_contains( $blocks, '<!-- wp:quote -->' ), $blocks );

// ── 9. Encoding edge cases ──────────────────────────────────────────────────
$r = $md->parse( "\xEF\xBB\xBF# BOM Title\r\n\r\nWindows line endings.\r\n", 'x.md' );
check( 'UTF-8 BOM stripped so the H1 is still a heading',  'BOM Title' === $r['title'], $r['title'] );
check( 'CRLF normalised',                                  ! str_contains( $r['content'], "\r" ) && str_contains( $r['content'], 'Windows line endings.' ) );

$r = $md->parse( "# Ünïcödé, ok\n\nTëxt", 'x.md' );
check( 'non-ASCII UTF-8 passes through intact',            'Ünïcödé, ok' === $r['title'], $r['title'] );

$r = $md->parse( "# Bad \xFF\xFE bytes", 'x.md' );
check( 'invalid UTF-8 → WP_Error',                          is_wp_error( $r ) && 'dtpost_md_encoding' === $r->get_error_code() );

$r = $md->parse( "PK\x03\x04\x00\x00binary", 'x.md' );
check( 'NUL bytes (binary file) → WP_Error',                is_wp_error( $r ) && 'dtpost_md_binary' === $r->get_error_code() );

$r = $md->parse( "---\ntitle: Only Meta\n---\n\n   \n", 'x.md' );
check( 'front matter with no body → empty error',           is_wp_error( $r ) && 'dtpost_empty' === $r->get_error_code() );

$r = $md->parse( "", 'x.md' );
check( 'empty file → empty error',                          is_wp_error( $r ) && 'dtpost_empty' === $r->get_error_code() );

// ── 10. Whole-document round trip ───────────────────────────────────────────
$r      = $md->parse( file_get_contents( __DIR__ . '/fixtures/sample.md' ), 'sample.md' );
$blocks = DTPost_Blocks::serialize( $r['content'] );
check( 'fixture: title',                                   'DocxToPost Markdown Fixture' === $r['title'], $r['title'] );
check( 'fixture: every top-level element became a block',  ! str_contains( $blocks, '<!-- wp:html -->' ), $blocks );
check( 'fixture: block count in the expected range',       preg_match_all( '/<!-- wp:[a-z]+/', $blocks ) >= 12 );
check( 'fixture: relative image reported',                 1 === count( $r['warnings'] ) );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
