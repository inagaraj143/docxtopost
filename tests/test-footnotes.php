<?php
/**
 * Word footnotes and endnotes: read, numbered, linked, and handed to core.
 *
 *     php tests/test-footnotes.php
 *
 * Until 1.5.0 a footnote's number vanished from the text and its note was never
 * read. Documents below go through the real DTPost_Parser. The second half checks
 * the markup handed to core's Footnotes block against what the block editor
 * itself writes, using core's own pattern for it.
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── Stubs ───────────────────────────────────────────────────────────────────
$GLOBALS['block_format']  = true;
$GLOBALS['core_footnote'] = true;
$GLOBALS['inserted_post'] = null;
$GLOBALS['uuid']          = 0;

function __( string $t, string $d = '' ): string { return $t; }
function esc_url( string $u ): string { return $u; }
function esc_attr( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8', false ); }
function esc_html( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8', false ); }
function wp_strip_all_tags( string $t ): string { return trim( strip_tags( $t ) ); }
function wp_specialchars_decode( string $t, int $q = ENT_NOQUOTES ): string { return htmlspecialchars_decode( $t, $q ); }
function wp_kses_post( string $html ): string { return $html; }
function get_option( string $k, $d = false ) { return $d; }
function wp_get_attachment_url( int $id ) { return ''; }
function wp_generate_password( int $n = 12 ): string { return str_repeat( 'x', $n ); }
function attachment_url_to_postid( string $url ): int { return 0; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( (string) $t ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function absint( $v ) { return abs( (int) $v ); }
function get_current_user_id() { return 1; }
function get_post_types( $args = array(), $output = 'names' ) { return array( 'post' => 'post' ); }
function is_object_in_taxonomy( $type, $tax ) { return false; }
function wp_insert_post( array $arr, bool $wp_error = false ) { $GLOBALS['inserted_post'] = $arr; return 7; }
function wp_set_object_terms( ...$a ) {}
function set_post_thumbnail( ...$a ) {}
function wp_generate_uuid4() { return sprintf( '00000000-0000-4000-8000-%012d', ++$GLOBALS['uuid'] ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_slash( $v ) { return addslashes( (string) $v ); }
function wp_unslash( $v ) { return stripslashes( (string) $v ); }
function registered_meta_key_exists( $type, $key, $sub = '' ) { return $GLOBALS['core_footnote'] && 'footnotes' === $key; }
function dtpost_use_block_format( string $type ): bool { return $GLOBALS['block_format']; }
function serialize_block_attributes( array $attrs ): string { return json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error( mixed $t ): bool { return $t instanceof WP_Error; }
class DTPost_Image { public function __call( string $n, array $a ): int { return 0; } }

$inc = dirname( __DIR__ ) . '/includes/';
require $inc . 'class-dtpost-title.php';
require $inc . 'class-dtpost-embeds.php';
require $inc . 'class-dtpost-details.php';
require $inc . 'class-dtpost-footnotes.php';
require $inc . 'class-dtpost-parser.php';
require $inc . 'class-dtpost-blocks.php';
require $inc . 'class-dtpost-publisher.php';

// ── Harness ─────────────────────────────────────────────────────────────────
$pass = 0;
$fail = 0;
function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-70s %s\n", $label, $ok ? 'ok' : 'FAIL' );
	if ( $ok ) {
		$pass++;
		return;
	}
	$fail++;
	if ( '' !== $extra ) {
		echo '      ', str_replace( "\n", "\n      ", $extra ), "\n";
	}
}

const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

function t( string $text ): string { return '<w:r><w:t xml:space="preserve">' . htmlspecialchars( $text, ENT_QUOTES ) . '</w:t></w:r>'; }
function fref( int $id ): string { return '<w:r><w:rPr><w:rStyle w:val="FootnoteReference"/><w:vertAlign w:val="superscript"/></w:rPr><w:footnoteReference w:id="' . $id . '"/></w:r>'; }
function eref( int $id ): string { return '<w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:endnoteReference w:id="' . $id . '"/></w:r>'; }
function p( string $runs, string $style = '' ): string { return '<w:p>' . ( $style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '' ) . $runs . '</w:p>'; }

/** A note as Word writes it: its own number, a space, the text. */
function note( string $kind, int $id, string $paras ): string {
	return '<w:' . $kind . ' w:id="' . $id . '">' . $paras . '</w:' . $kind . '>';
}
function note_p( string $kind, string $runs ): string {
	return '<w:p><w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:' . $kind . 'Ref/></w:r>' . t( ' ' ) . $runs . '</w:p>';
}
const SEPARATORS = '<w:footnote w:type="separator" w:id="-1"><w:p><w:r><w:separator/></w:r></w:p></w:footnote><w:footnote w:type="continuationSeparator" w:id="0"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>';

function docx( string $body, string $footnotes = '', string $endnotes = '', string $footnote_rels = '' ): string {
	$path = tempnam( sys_get_temp_dir(), 'dtp-f' ) . '.docx';
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . W . '" xmlns:r="' . R . '"><w:body>' . $body . '</w:body></w:document>' );
	if ( '' !== $footnotes ) {
		$zip->addFromString( 'word/footnotes.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:footnotes xmlns:w="' . W . '" xmlns:r="' . R . '">' . SEPARATORS . $footnotes . '</w:footnotes>' );
	}
	if ( '' !== $endnotes ) {
		$zip->addFromString( 'word/endnotes.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:endnotes xmlns:w="' . W . '" xmlns:r="' . R . '">' . $endnotes . '</w:endnotes>' );
	}
	if ( '' !== $footnote_rels ) {
		$zip->addFromString( 'word/_rels/footnotes.xml.rels', $footnote_rels );
	}
	$zip->close();
	return $path;
}
function parse( string $file, string $name = 'notes.docx' ): array {
	$r = ( new DTPost_Parser() )->parse( $file, $name );
	unlink( $file );
	return $r;
}

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/></Relationships>';

// ── Reading ─────────────────────────────────────────────────────────────────
echo "\nReading footnotes and endnotes\n\n";

$body = p( t( 'Garden Notes' ), 'Heading1' )
	. p( t( 'Lettuce grows fast' ) . fref( 2 ) . t( ' in spring.' ) )
	. p( t( 'Water early' ) . fref( 1 ) . t( ', not at noon' ) . eref( 1 ) . t( '.' ) );
$foot = note( 'footnote', 1, note_p( 'footnote', t( 'See ' ) . '<w:hyperlink r:id="rId1">' . t( 'the source' ) . '</w:hyperlink>' . t( '.' ) ) )
	. note( 'footnote', 2, note_p( 'footnote', '<w:r><w:rPr><w:b/></w:rPr><w:t>Bold</w:t></w:r>' . t( ' first line.' ) ) . p( t( 'Second line.' ) ) )
	. note( 'footnote', 3, note_p( 'footnote', t( 'Never referred to.' ) ) );
$end  = note( 'endnote', 1, note_p( 'endnote', t( 'An endnote.' ) ) );
$r    = parse( docx( $body, $foot, $end, $rels ) );
$c    = $r['content'];

check( 'title is clean', 'Garden Notes' === $r['title'], $r['title'] );
check( 'first reference in the text is number 1, whatever Word called it', str_contains( $c, 'Lettuce grows fast<sup class="fn"><a href="#fn-1" id="fnref-1">1</a></sup> in spring.' ), $c );
check( 'second is 2', str_contains( $c, 'Water early<sup class="fn"><a href="#fn-2" id="fnref-2">2</a></sup>' ), $c );
check( 'an endnote follows on as 3, in one list', str_contains( $c, 'not at noon<sup class="fn"><a href="#fn-3" id="fnref-3">3</a></sup>' ), $c );
check( 'the list is at the end', 1 === preg_match( '~<ol class="footnotes">.*</ol>$~s', $c ), $c );
preg_match_all( '~<li id="fn-(\d+)">(.*?)</li>~s', $c, $li );
check( 'three entries, in reference order', array( '1', '2', '3' ) === $li[1], json_encode( $li[1] ) );
check( 'note 1 is Word\'s footnote 2, bold kept, two paragraphs on two lines', str_starts_with( $li[2][0], '<strong>Bold</strong> first line.<br>Second line.' ), $li[2][0] );
check( 'a link in a note uses the note part\'s own relationships', str_contains( $li[2][1], '<a href="https://example.test/source">the source</a>' ), $li[2][1] );
check( 'each entry links back to its reference', str_contains( $li[2][0], '<a href="#fnref-1"' ) && str_contains( $li[2][2], '<a href="#fnref-3"' ) );
check( 'a note nothing refers to is not listed', ! str_contains( $c, 'Never referred to' ) );
check( 'Word\'s separator lines are not notes', 3 === count( $li[1] ) );
check( 'no placeholder left behind', ! str_contains( $c, DTPost_Footnotes::ATTR ) );

$r = parse( docx( p( t( 'A Heading' ) . fref( 1 ), 'Heading1' ) . p( t( 'Text' ) . fref( 2 ) . t( '.' ) ), note( 'footnote', 1, note_p( 'footnote', t( 'On the title.' ) ) ) . note( 'footnote', 2, note_p( 'footnote', t( 'In the text.' ) ) ) ) );
check( 'a reference in the title heading leaves the title clean', 'A Heading' === $r['title'], $r['title'] );
check( 'and numbering starts at 1 in what is left', str_contains( $r['content'], 'Text<sup class="fn"><a href="#fn-1" id="fnref-1">1</a></sup>.' ) && ! str_contains( $r['content'], 'On the title.' ), $r['content'] );

$r = parse( docx( p( t( 'Text' ) . fref( 9 ) . t( '.' ) ), note( 'footnote', 1, note_p( 'footnote', t( 'x' ) ) ) ) );
check( 'a reference to a note that is not there disappears cleanly', 'Text.' === wp_strip_all_tags( $r['content'] ) && ! str_contains( $r['content'], '<ol' ), $r['content'] );

$r = parse( docx( p( t( 'Plain text.' ) ) ) );
check( 'a document without notes is exactly as before', '<p>Plain text.</p>' === $r['content'], $r['content'] );

$r = parse( docx( p( t( 'Text' ) . fref( 1 ) ) . p( t( 'Again' ) . fref( 1 ) ), note( 'footnote', 1, note_p( 'footnote', t( 'Once.' ) ) ) ) );
check( 'the same note twice: one entry, no repeated id', 1 === substr_count( $r['content'], '<li ' ) && 1 === substr_count( $r['content'], 'id="fnref-1"' ) && 2 === substr_count( $r['content'], 'href="#fn-1"' ), $r['content'] );

$r = parse( docx( p( t( 'Body' ) . fref( 1 ) ), note( 'footnote', 1, note_p( 'footnote', '<w:r><w:footnoteReference w:id="1"/></w:r>' . t( 'Inside.' ) ) ) ) );
check( 'a reference inside a note itself is ignored', 1 === substr_count( $r['content'], '<li ' ) && ! str_contains( $r['content'], DTPost_Footnotes::ATTR ) && str_contains( $r['content'], 'Inside.' ), $r['content'] );

// ── Core's Footnotes block ──────────────────────────────────────────────────
echo "\nHanded to core's Footnotes block\n\n";

$html = '<p>One<sup class="fn"><a href="#fn-1" id="fnref-1">1</a></sup> and two<sup class="fn"><a href="#fn-2" id="fnref-2">2</a></sup>.</p>'
	. "\n" . '<ol class="footnotes"><li id="fn-1">First &amp; <em>best</em>. <a href="#fnref-1" aria-label="Back to reference 1">&#8617;&#65038;</a></li><li id="fn-2">Second. <a href="#fnref-2" aria-label="Back to reference 2">&#8617;&#65038;</a></li><li id="fn-3">Orphan. <a href="#fnref-3">&#8617;</a></li></ol>';
$core = DTPost_Footnotes::to_core( $html );
$id1  = $core['notes'][0]['id'] ?? '';

check( 'references become exactly what the editor writes', str_contains( $core['content'], '<sup data-fn="' . $id1 . '" class="fn"><a href="#' . $id1 . '" id="' . $id1 . '-link">1</a></sup>' ), $core['content'] );
check( 'core\'s own excerpt pattern finds and removes every one', '<p>One and two.</p>' === trim( preg_replace( '_<sup data-fn="[^"]+" class="[^"]+">\s*<a href="[^"]+" id="[^"]+">\d+</a>\s*</sup>_', '', $core['content'] ) ), $core['content'] );
check( 'the list is gone from the content', ! str_contains( $core['content'], '<ol' ) && ! str_contains( $core['content'], '<li' ) );
check( 'notes in order, formatting kept, our back link gone', 'First &amp; <em>best</em>.' === $core['notes'][0]['content'] && 'Second.' === $core['notes'][1]['content'], json_encode( $core['notes'] ) );
check( 'a note whose reference was deleted on the preview is kept, last', 3 === count( $core['notes'] ) && 'Orphan.' === $core['notes'][2]['content'] );
check( 'every note has its own id', 3 === count( array_unique( array_column( $core['notes'], 'id' ) ) ) );
check( 'no list: nothing to do', null === DTPost_Footnotes::to_core( '<p>Just text.</p><ol><li>An ordinary list</li></ol>' ) );

// ── Publishing ──────────────────────────────────────────────────────────────
echo "\nPublishing\n\n";

$publish = static function ( string $content ): array {
	$GLOBALS['inserted_post'] = null;
	( new DTPost_Publisher() )->publish( array( 'title' => 'T', 'content' => $content, 'post_type' => 'post' ) );
	return (array) $GLOBALS['inserted_post'];
};

$p = $publish( $html );
check( 'blocks: the Footnotes block is added at the end', str_ends_with( rtrim( $p['post_content'] ), '<!-- wp:footnotes /-->' ), substr( $p['post_content'], -200 ) );
check( 'blocks: the references are in a paragraph block, intact', str_contains( $p['post_content'], '<!-- wp:paragraph -->' ) && 2 === substr_count( $p['post_content'], 'data-fn="' ), $p['post_content'] );
$json  = wp_unslash( $p['meta_input']['footnotes'] ?? '' );
$saved = json_decode( $json, true );
check( 'blocks: the notes go in with the save, as JSON that survives unslashing', is_array( $saved ) && 3 === count( $saved ) && 'First &amp; <em>best</em>.' === $saved[0]['content'], $json );
check( 'blocks: the meta ids match the references', str_contains( $p['post_content'], 'data-fn="' . $saved[0]['id'] . '"' ) );

$GLOBALS['core_footnote'] = false;
$p = $publish( $html );
check( 'no Footnotes block for this type: the linked list stays', str_contains( $p['post_content'], 'id="fn-1"' ) && str_contains( $p['post_content'], 'href="#fn-1"' ) && ! isset( $p['meta_input'] ), $p['post_content'] );
$GLOBALS['core_footnote'] = true;

$GLOBALS['block_format'] = false;
$p = $publish( $html );
check( 'classic editor: the linked list stays, untouched', $html === $p['post_content'] && ! isset( $p['meta_input'] ) );
$GLOBALS['block_format'] = true;

$p = $publish( '<p>No notes here.</p>' );
check( 'no notes: no Footnotes block and no meta', ! str_contains( $p['post_content'], 'footnotes' ) && ! isset( $p['meta_input'] ) );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
