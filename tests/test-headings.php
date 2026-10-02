<?php
/**
 * Heading detection across the ways Word actually writes headings.
 *
 *     php tests/test-headings.php
 *
 * A customer reported (against Pro, but the free parser had the identical check) that a document "with genuine Heading styles applied
 * throughout" imported as plain paragraphs while the same content as
 * Markdown kept its H1/H2/H3. The parser matched only the style *ID*
 * ("Heading1"), and the ID is not stable across Word locales, copied styles
 * or custom styles. Each fixture below is a minimal .docx built here with
 * ZipArchive so the test needs no binary files and states exactly what it
 * is exercising.
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── Stubs ───────────────────────────────────────────────────────────────────
function __( string $t, string $d = '' ): string { return $t; }
function esc_url( string $u ): string { return $u; }
function esc_attr( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8', false ); }
function esc_html( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8', false ); }
function wp_strip_all_tags( string $t ): string { return trim( strip_tags( $t ) ); }
function sanitize_text_field( string $t ): string { return trim( $t ); }
function wp_specialchars_decode( string $t, int $q = ENT_NOQUOTES ): string { return htmlspecialchars_decode( $t, $q ); }
function get_option( string $k, $d = false ) { return $d; }
function wp_get_attachment_url( int $id ) { return ''; }
function wp_generate_password( int $n = 12 ): string { return str_repeat( 'x', $n ); }
class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error( mixed $t ): bool { return $t instanceof WP_Error; }

// The free parser always runs the image pass; with no images it only needs the class to exist.
class DTPost_Image { public function __call( string $n, array $a ): int { return 0; } }

require dirname( __DIR__ ) . '/includes/class-dtpost-title.php';
require dirname( __DIR__ ) . '/includes/class-dtpost-embeds.php';
require dirname( __DIR__ ) . '/includes/class-dtpost-parser.php';

// ── Fixture builder ─────────────────────────────────────────────────────────

const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

/**
 * @param array<int, array{0:string,1:string,2?:string}> $paras [styleId, text, outlineLvl?]
 * @param array<int, array{id:string,name?:string,outline?:string,basedOn?:string}> $styles
 */
function make_docx( array $paras, ?array $styles ): string {
	$body = '';
	foreach ( $paras as $p ) {
		[ $style, $text ] = $p;
		$pPr = '';
		if ( '' !== $style ) {
			$pPr .= '<w:pStyle w:val="' . htmlspecialchars( $style, ENT_QUOTES ) . '"/>';
		}
		if ( isset( $p[2] ) ) {
			$pPr .= '<w:outlineLvl w:val="' . $p[2] . '"/>';
		}
		$body .= '<w:p>' . ( '' !== $pPr ? "<w:pPr>{$pPr}</w:pPr>" : '' )
			. '<w:r><w:t>' . htmlspecialchars( $text, ENT_QUOTES ) . '</w:t></w:r></w:p>';
	}
	$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<w:document xmlns:w="' . W . '"><w:body>' . $body . '</w:body></w:document>';

	$styles_xml = null;
	if ( null !== $styles ) {
		$s = '';
		foreach ( $styles as $st ) {
			$s .= '<w:style w:type="paragraph" w:styleId="' . htmlspecialchars( $st['id'], ENT_QUOTES ) . '">';
			if ( isset( $st['name'] ) )    { $s .= '<w:name w:val="' . htmlspecialchars( $st['name'], ENT_QUOTES ) . '"/>'; }
			if ( isset( $st['basedOn'] ) ) { $s .= '<w:basedOn w:val="' . htmlspecialchars( $st['basedOn'], ENT_QUOTES ) . '"/>'; }
			if ( isset( $st['outline'] ) ) { $s .= '<w:pPr><w:outlineLvl w:val="' . $st['outline'] . '"/></w:pPr>'; }
			$s .= '</w:style>';
		}
		$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:styles xmlns:w="' . W . '">' . $s . '</w:styles>';
	}

	$path = tempnam( sys_get_temp_dir(), 'dwp-h' ) . '.docx';
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$zip->addFromString( 'word/document.xml', $document );
	if ( null !== $styles_xml ) {
		$zip->addFromString( 'word/styles.xml', $styles_xml );
	}
	$zip->close();
	return $path;
}

function tags( string $html ): string {
	preg_match_all( '/<(h[1-6]|p|li)\b/', $html, $m );
	return implode( ' ', $m[1] );
}

// ── Harness ─────────────────────────────────────────────────────────────────
$fail = 0; $pass = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $fail, $pass;
	printf( "  %-66s %s\n", $label, $ok ? 'ok' : 'FAIL' );
	if ( $ok ) { $pass++; } else { $fail++; if ( '' !== $detail ) { echo "      $detail\n"; } }
}

$parser = new DTPost_Parser();

// 1. English Word — the only case the old parser handled.
$f = make_docx(
	[ [ 'Heading1', 'Title' ], [ 'Heading2', 'Section' ], [ 'Normal', 'Body.' ] ],
	[ [ 'id' => 'Heading1', 'name' => 'heading 1' ], [ 'id' => 'Heading2', 'name' => 'heading 2' ], [ 'id' => 'Normal', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'en.docx' );
check( 'English Word: Heading1/Heading2 → h1 title, h2, p',      'Title' === $r['title'] && 'h2 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 2. German Word — localised style IDs, canonical English names.
$f = make_docx(
	[ [ 'berschrift1', 'Titel' ], [ 'berschrift2', 'Abschnitt' ], [ 'berschrift3', 'Unterabschnitt' ], [ 'Standard', 'Text.' ] ],
	[ [ 'id' => 'berschrift1', 'name' => 'heading 1' ], [ 'id' => 'berschrift2', 'name' => 'heading 2' ], [ 'id' => 'berschrift3', 'name' => 'heading 3' ], [ 'id' => 'Standard', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'de.docx' );
check( 'German Word: berschrift1..3 → h1 title, h2, h3, p',      'Titel' === $r['title'] && 'h2 h3 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 3. French Word.
$f = make_docx(
	[ [ 'Titre1', 'Le titre' ], [ 'Titre2', 'La section' ] ],
	[ [ 'id' => 'Titre1', 'name' => 'heading 1' ], [ 'id' => 'Titre2', 'name' => 'heading 2' ] ]
);
$r = $parser->parse( $f, 'fr.docx' );
check( 'French Word: Titre1/Titre2 → h1 title, h2',               'Le titre' === $r['title'] && 'h2' === tags( $r['content'] ), tags( $r['content'] ) );

// 4. Copied-style ID: Word writes "Heading11" when a heading style is pasted in.
$f = make_docx(
	[ [ 'Heading11', 'Pasted' ], [ 'Heading21', 'Pasted section' ] ],
	[ [ 'id' => 'Heading11', 'name' => 'heading 1' ], [ 'id' => 'Heading21', 'name' => 'heading 2' ] ]
);
$r = $parser->parse( $f, 'copied.docx' );
check( 'Copied styles: Heading11/Heading21 → h1, h2 (by name)',    'Pasted' === $r['title'] && 'h2' === tags( $r['content'] ), tags( $r['content'] ) );

// 5. Custom style based on a heading, nothing else to go on.
$f = make_docx(
	[ [ 'ChapterTitle', 'Chapter One' ], [ 'Kicker', 'Kicker line' ], [ 'Normal', 'Body.' ] ],
	[
		[ 'id' => 'Heading1', 'name' => 'heading 1' ],
		[ 'id' => 'ChapterTitle', 'name' => 'Chapter title', 'basedOn' => 'Heading1' ],
		[ 'id' => 'Kicker', 'name' => 'Kicker', 'basedOn' => 'ChapterTitle' ],
		[ 'id' => 'Normal', 'name' => 'Normal' ],
	]
);
$r = $parser->parse( $f, 'custom.docx' );
check( 'Custom style basedOn Heading1 → h1; two levels deep too',  'Chapter One' === $r['title'] && 'h1 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 6. Style with an outline level and an unrelated name.
$f = make_docx(
	[ [ 'MySection', 'Third-level' ], [ 'Normal', 'Body.' ] ],
	[ [ 'id' => 'MySection', 'name' => 'My Section', 'outline' => '2' ], [ 'id' => 'Normal', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'outline.docx' );
check( 'Style with outlineLvl 2 → h3',                              'h3 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 7. TOC Heading: based on Heading 1 but opts out with outlineLvl 9.
$f = make_docx(
	[ [ 'TOCHeading', 'Contents' ], [ 'Heading1', 'Real title' ] ],
	[ [ 'id' => 'Heading1', 'name' => 'heading 1' ], [ 'id' => 'TOCHeading', 'name' => 'TOC Heading', 'basedOn' => 'Heading1', 'outline' => '9' ] ]
);
$r = $parser->parse( $f, 'toc.docx' );
check( 'TOC Heading (basedOn Heading1, outlineLvl 9) stays a paragraph', 'Real title' === $r['title'] && 'p' === tags( $r['content'] ), $r['title'] . ' | ' . tags( $r['content'] ) );

// 8. Outline level set directly on the paragraph, no style at all.
//
// A Heading 1 leads so the outline-level paragraph is not itself taken as
// the title and removed — this fixture is about heading *detection*.
$f = make_docx(
	[ [ 'Heading1', 'Doc title' ], [ '', 'Direct level', '1' ], [ '', 'Body.' ] ],
	[ [ 'id' => 'Heading1', 'name' => 'heading 1' ], [ 'id' => 'Normal', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'direct.docx' );
check( 'Paragraph-level outlineLvl 1 (no pStyle) → h2',            'h2 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 9. No styles.xml at all — the pre-1.3.2 behaviour must survive.
$f = make_docx( [ [ 'Heading3', 'Old way' ], [ 'Normal', 'Body.' ] ], null );
$r = $parser->parse( $f, 'nostyles.docx' );
check( 'No styles.xml: Heading3 ID still → h3',                     'h3 p' === tags( $r['content'] ), tags( $r['content'] ) );

// 10. Things that must NOT become headings.
$f = make_docx(
	[ [ 'Normal', 'Body.' ], [ 'Quote', 'Quoted.' ], [ 'Heading7', 'Not a level' ], [ 'Subtitle', 'Sub' ] ],
	[ [ 'id' => 'Normal', 'name' => 'Normal' ], [ 'id' => 'Quote', 'name' => 'Quote' ], [ 'id' => 'Heading7', 'name' => 'heading 7' ], [ 'id' => 'Subtitle', 'name' => 'Subtitle' ] ]
);
$r = $parser->parse( $f, 'body.docx' );
check( 'Normal / Quote / heading 7 / Subtitle → no headings',      ! preg_match( '/<h[1-6]/', $r['content'] ), tags( $r['content'] ) );

// 11. Malformed styles.xml must not break the import.
$path = make_docx( [ [ 'Heading1', 'Survives' ] ], [] );
$zip  = new ZipArchive(); $zip->open( $path ); $zip->addFromString( 'word/styles.xml', '<w:styles xmlns:w="' . W . '"><w:style w:type="paragraph"' ); $zip->close();
$r = $parser->parse( $path, 'broken.docx' );
check( 'Broken styles.xml: falls back to the ID, no error',         ! is_wp_error( $r ) && 'Survives' === $r['title'], is_wp_error( $r ) ? $r->get_error_message() : $r['title'] );

// ── The title heading must not also appear in the body ──────────────────────
//
// Reported against Pro by a customer, and the free parser had the identical
// bug: the title is taken from the first h1 OR h2, but only an h1 was ever
// removed, so a document whose top heading was Heading 2 kept it as the
// first line of the post, directly under the identical post title.

$f = make_docx(
	[ [ 'Heading2', 'Quarterly update' ], [ 'Normal', 'Body.' ], [ 'Heading2', 'A later section' ] ],
	[ [ 'id' => 'Heading2', 'name' => 'heading 2' ], [ 'id' => 'Normal', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'q.docx' );
check( 'H2 title: taken as the title',                              'Quarterly update' === $r['title'], $r['title'] );
check( 'H2 title: removed from the body (the reported bug)',        ! str_contains( $r['content'], 'Quarterly update' ), $r['content'] );
check( 'H2 title: later headings of the same level are kept',       str_contains( $r['content'], 'A later section' ), $r['content'] );

$f = make_docx(
	[ [ 'Heading1', 'The title' ], [ 'Heading2', 'First section' ], [ 'Normal', 'Body.' ] ],
	[ [ 'id' => 'Heading1', 'name' => 'heading 1' ], [ 'id' => 'Heading2', 'name' => 'heading 2' ], [ 'id' => 'Normal', 'name' => 'Normal' ] ]
);
$r = $parser->parse( $f, 'h1.docx' );
check( 'H1 title: removed, and the H2 below it stays',              'The title' === $r['title'] && ! str_contains( $r['content'], 'The title' ) && str_contains( $r['content'], 'First section' ), $r['title'] . ' | ' . tags( $r['content'] ) );

$f = make_docx( [ [ 'Normal', 'Just a paragraph.' ] ], [ [ 'id' => 'Normal', 'name' => 'Normal' ] ] );
$r = $parser->parse( $f, 'weekly-notes.docx' );
check( 'filename title: sentence case, body left alone',            'Weekly notes' === $r['title'] && str_contains( $r['content'], 'Just a paragraph.' ), $r['title'] . ' | ' . $r['content'] );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
