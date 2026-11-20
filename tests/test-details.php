<?php
/**
 * What a document says about itself: Word properties and Markdown front matter.
 *
 *     php tests/test-details.php
 *
 * The same rules as DocxToWP Pro's DWP_Details, minus Pro's labelled SEO lines,
 * which Free has no fields for. Word files go through the real DTPost_Parser and
 * Markdown through the real DTPost_Markdown, so the input is what an upload
 * really becomes. The categories section is the part with a security edge: a
 * user who cannot create categories must not be able to make one by request.
 */

define( 'ABSPATH', __DIR__ . '/' );

// ── Stubs ───────────────────────────────────────────────────────────────────
$GLOBALS['can_manage']     = true;
$GLOBALS['terms']          = array(
	(object) array( 'term_id' => 3, 'name' => 'Travel', 'slug' => 'travel' ),
	(object) array( 'term_id' => 4, 'name' => 'Food &amp; Drink', 'slug' => 'food-drink' ),
);
$GLOBALS['inserted_terms'] = array();
$GLOBALS['inserted_post']  = null;
$GLOBALS['taxonomies']     = array( 'post' => array( 'category', 'post_tag' ), 'page' => array() );

function __( string $t, string $d = '' ): string { return $t; }
function _n( string $s, string $p, int $n, string $d = '' ): string { return 1 === $n ? $s : $p; }
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
function get_post_types( $args = array(), $output = 'names' ) { return array( 'post' => 'post', 'page' => 'page' ); }
function is_object_in_taxonomy( $type, $tax ) { return in_array( $tax, $GLOBALS['taxonomies'][ $type ] ?? array(), true ); }
function wp_insert_post( array $arr, bool $wp_error = false ) { $GLOBALS['inserted_post'] = $arr; return 7; }
function wp_set_object_terms( ...$a ) {}
function set_post_thumbnail( ...$a ) {}
function get_terms( array $args ) { return $GLOBALS['terms']; }
function get_taxonomy( string $t ) { return (object) array( 'cap' => (object) array( 'manage_terms' => 'manage_categories' ) ); }
function current_user_can( string $cap ): bool { return $GLOBALS['can_manage']; }
function term_exists( $name, $tax ) {
	foreach ( $GLOBALS['terms'] as $term ) {
		if ( 0 === strcasecmp( html_entity_decode( $term->name ), (string) $name ) ) {
			return array( 'term_id' => (string) $term->term_id );
		}
	}
	return null;
}
function wp_insert_term( $name, $tax ) {
	$GLOBALS['inserted_terms'][] = $name;
	return array( 'term_id' => 100 + count( $GLOBALS['inserted_terms'] ) );
}
class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
function is_wp_error( mixed $t ): bool { return $t instanceof WP_Error; }

// Image upload is not under test, and the documents here have no pictures.
class DTPost_Image { public function __call( string $n, array $a ): int { return 0; } }

$inc = dirname( __DIR__ ) . '/includes/';
require $inc . 'class-dtpost-title.php';
require $inc . 'class-dtpost-embeds.php';
require $inc . 'class-dtpost-footnotes.php';
require $inc . 'class-dtpost-details.php';
require $inc . 'class-dtpost-parser.php';
require $inc . 'lib/Parsedown.php';
require $inc . 'class-dtpost-markdown.php';
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

/**
 * @param array<int, string|array{0:string,1:string}> $paras A string is a body
 *     paragraph. An array is [style, text].
 * @param array{title?:string,keywords?:string}|null $core docProps/core.xml.
 */
function docx( array $paras, ?array $core = null ): string {
	$body = '';
	foreach ( $paras as $p ) {
		if ( is_array( $p ) ) {
			$body .= '<w:p><w:pPr><w:pStyle w:val="' . $p[0] . '"/></w:pPr><w:r><w:t>' . htmlspecialchars( $p[1], ENT_QUOTES ) . '</w:t></w:r></w:p>';
		} else {
			$body .= '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars( $p, ENT_QUOTES ) . '</w:t></w:r></w:p>';
		}
	}

	$path = tempnam( sys_get_temp_dir(), 'dtp-d' ) . '.docx';
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$zip->addFromString( 'word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . W . '"><w:body>' . $body . '</w:body></w:document>' );
	if ( null !== $core ) {
		$zip->addFromString(
			'docProps/core.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. ( isset( $core['title'] ) ? '<dc:title>' . htmlspecialchars( $core['title'], ENT_QUOTES ) . '</dc:title>' : '' )
			. ( isset( $core['keywords'] ) ? '<cp:keywords>' . htmlspecialchars( $core['keywords'], ENT_QUOTES ) . '</cp:keywords>' : '' )
			. '</cp:coreProperties>'
		);
	}
	$zip->close();
	return $path;
}

function word( array $paras, ?array $core = null, string $name = 'my-draft.docx' ): array {
	$file = docx( $paras, $core );
	$r    = ( new DTPost_Parser() )->parse( $file, $name );
	unlink( $file );
	return $r;
}

// ── Word properties ─────────────────────────────────────────────────────────
echo "\nWord properties\n\n";

$r = word( array( 'No heading here.' ), array( 'title' => 'A Proper Title', 'keywords' => 'garden; lettuce, Slugs ;garden' ) );
check( 'Title is used when no heading opens the document', 'A Proper Title' === $r['title'], $r['title'] );
check( 'Keywords become tags: either separator, no repeats', array( 'garden', 'lettuce', 'Slugs' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );
check( 'the body is untouched', str_contains( $r['content'], 'No heading here.' ), $r['content'] );

$r = word( array( array( 'Heading1', 'The Heading' ), 'Body.' ), array( 'title' => 'A Proper Title' ) );
check( 'a heading that opens the document still wins over Title', 'The Heading' === $r['title'], $r['title'] );

foreach ( array( 'Document1', 'Untitled', 'Microsoft Word - report.doc', 'old-draft.docx', 'Word Document', '   ' ) as $junk ) {
	$r = word( array( 'No heading here.' ), array( 'title' => $junk ), 'my-draft.docx' );
	check( "'$junk' is not a title: the file name is used", 'My draft' === $r['title'], $r['title'] );
}

$r = word( array( 'No heading here.' ), null, 'my-draft.docx' );
check( 'no properties at all: the file name, as before', 'My draft' === $r['title'] && array() === $r['details']['tags'], $r['title'] );
check( 'and blank details', DTPost_Details::blank() === $r['details'], json_encode( $r['details'] ) );

$broken = docx( array( 'Body.' ) );
$zip    = new ZipArchive();
$zip->open( $broken );
$zip->addFromString( 'docProps/core.xml', '<not really xml' );
$zip->close();
$r = ( new DTPost_Parser() )->parse( $broken, 'my-draft.docx' );
unlink( $broken );
check( 'unreadable properties do not stop the import', 'My draft' === $r['title'] && str_contains( $r['content'], 'Body.' ), $r['title'] );

$r = word( array( 'SEO meta description: stays put.', 'URL slug: /stays-too/', 'Body.' ) );
check( 'labelled SEO lines are Pro only: Free leaves them in the post', str_contains( $r['content'], 'SEO meta description:' ) && str_contains( $r['content'], 'URL slug:' ) && '' === $r['details']['slug'], $r['content'] );

// ── Front matter ────────────────────────────────────────────────────────────
echo "\nFront matter\n\n";

$md = new DTPost_Markdown();

$r = $md->parse( "---\ntitle: Rome\nslug: my-trip-to-rome\nexcerpt: \"Five days, one suitcase.\"\ntags: [travel, \"city breaks\", #italy]\ncategories: [Travel, Italy]\nauthor: Someone\ndate: 2026-01-01\n---\n\nBody.\n", 'x.md' );
check( 'title, as before', 'Rome' === $r['title'], $r['title'] );
check( 'slug', 'my-trip-to-rome' === $r['details']['slug'], $r['details']['slug'] );
check( 'excerpt, quotes gone', 'Five days, one suitcase.' === $r['details']['excerpt'], $r['details']['excerpt'] );
check( 'inline tags, with a # and a quoted name', array( 'travel', 'city breaks', 'italy' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );
check( 'inline categories', array( 'Travel', 'Italy' ) === $r['details']['categories'], json_encode( $r['details']['categories'] ) );
check( 'other keys ignored, and the block is out of the body', '<p>Body.</p>' === $r['content'], $r['content'] );
check( 'summary names what was found', array( 'URL slug', 'excerpt', 'tags', 'categories' ) === DTPost_Details::summary( $r['details'] ), implode( ', ', DTPost_Details::summary( $r['details'] ) ) );

$joplin = "---\ntitle: Setting up the workshop\nupdated: 2026-09-10 14:22:00Z\ncreated: 2026-08-02 09:15:00Z\ntags:\n  - workshop\n  - setup\n---\n\nBody.\n";
$r      = $md->parse( $joplin, 'x.md' );
check( 'Joplin\'s export: tags as an indented list', array( 'workshop', 'setup' ) === $r['details']['tags'], json_encode( $r['details'] ) );

$r = $md->parse( "---\ntags:\n- one\n- 'two'\ncategory: Notes\n---\n\nBody.\n", 'x.md' );
check( 'list at the same indent as its key, quoted item', array( 'one', 'two' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );
check( 'category, singular', array( 'Notes' ) === $r['details']['categories'], json_encode( $r['details']['categories'] ) );

$r = $md->parse( "---\ntags: one, two ,three\n---\n\nBody.\n", 'x.md' );
check( 'plain comma-separated', array( 'one', 'two', 'three' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );

$r = $md->parse( "---\ntags: #one #two\n---\n\nBody.\n", 'x.md' );
check( 'Obsidian\'s #one #two', array( 'one', 'two' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );

$r = $md->parse( "---\nexcerpt: >\n  Folded onto\n  two lines.\nslug: /a/b/last-part/\n---\n\nBody.\n", 'x.md' );
check( 'folded excerpt joined with a space', 'Folded onto two lines.' === $r['details']['excerpt'], $r['details']['excerpt'] );
check( 'slug written as a path', 'last-part' === $r['details']['slug'], $r['details']['slug'] );

$r = $md->parse( "---\ntags: [\"a, b\"]\nslug: null\nexcerpt: ~\ncategories:\n---\n\nBody.\n", 'x.md' );
check( 'a comma cannot survive in a tag', array( 'a b' ) === $r['details']['tags'], json_encode( $r['details']['tags'] ) );
check( 'null, ~ and nothing at all read as empty', '' === $r['details']['slug'] && '' === $r['details']['excerpt'] && array() === $r['details']['categories'], json_encode( $r['details'] ) );

$r = $md->parse( "---\ntitle: T\n  slug: indented-so-not-top-level\nnested:\n  tags: [not, these]\n---\n\nBody.\n", 'x.md' );
check( 'only top-level keys count', '' === $r['details']['slug'] && array() === $r['details']['tags'], json_encode( $r['details'] ) );

$r = $md->parse( "---\nexcerpt: <script>alert(1)</script>Safe\ntags: [\"<b>x</b>\"]\n---\n\nBody.\n", 'x.md' );
check( 'markup in values is stripped', ! str_contains( json_encode( $r['details'] ), '<' ), json_encode( $r['details'] ) );

$r = $md->parse( "# Just a heading\n\nBody.\n", 'x.md' );
check( 'no front matter: blank details', DTPost_Details::blank() === $r['details'], json_encode( $r['details'] ) );

// ── Categories ──────────────────────────────────────────────────────────────
echo "\nCategories\n\n";

$m = DTPost_Details::match_categories( array( 'travel', 'FOOD & DRINK', 'food-drink', 'Italy' ) );
check( 'matched by name in any case, entities decoded, or by slug; no repeats', array( 3, 4 ) === $m['ids'], json_encode( $m ) );
check( 'the rest are reported as missing', array( 'Italy' ) === $m['missing'], json_encode( $m ) );
check( 'no names: nothing to do', array( 'ids' => array(), 'missing' => array() ) === DTPost_Details::match_categories( array() ) );

$GLOBALS['can_manage'] = false;
check( 'an Author creates nothing', array() === DTPost_Details::create_categories( array( 'Italy' ) ) && array() === $GLOBALS['inserted_terms'] );

$GLOBALS['can_manage'] = true;
$ids = DTPost_Details::create_categories( array( 'Italy', 'TRAVEL', '  ', '<b>Bold</b>' ) );
check( 'an Editor creates what is missing', array( 'Italy', 'Bold' ) === $GLOBALS['inserted_terms'], json_encode( $GLOBALS['inserted_terms'] ) );
check( 'and gets the id of one that existed after all', array( 101, 3, 102 ) === $ids, json_encode( $ids ) );

// ── Publishing ──────────────────────────────────────────────────────────────
echo "\nPublishing\n\n";

$publish = static function ( array $data ): array {
	$GLOBALS['inserted_post']  = null;
	$GLOBALS['inserted_terms'] = array();
	( new DTPost_Publisher() )->publish( $data + array( 'title' => 'T', 'content' => '<p>a</p>', 'post_type' => 'post' ) );
	return (array) $GLOBALS['inserted_post'];
};

$p = $publish( array( 'category' => array( 3 ), 'new_categories' => array( 'Italy' ) ) );
check( 'a ticked new category is created and added to the post', array( 3, 101 ) === $p['post_category'] && array( 'Italy' ) === $GLOBALS['inserted_terms'], json_encode( $p['post_category'] ?? null ) );

$p = $publish( array( 'category' => array( 3 ) ) );
check( 'none ticked: nothing created', array( 3 ) === $p['post_category'] && array() === $GLOBALS['inserted_terms'] );

$GLOBALS['can_manage'] = false;
$p = $publish( array( 'category' => array( 3 ), 'new_categories' => array( 'Forged' ) ) );
check( 'an Author sending one in a forged request creates nothing', array( 3 ) === $p['post_category'] && array() === $GLOBALS['inserted_terms'] );
$GLOBALS['can_manage'] = true;

$p = $publish( array( 'post_type' => 'page', 'new_categories' => array( 'Italy' ) ) );
check( 'a page has no categories, so none is created for it', array() === $GLOBALS['inserted_terms'] && ! isset( $p['post_category'] ) );

$p = $publish( array( 'slug' => 'my-trip-to-rome', 'excerpt' => 'Five days.' ) );
check( 'slug and excerpt reach the post', 'my-trip-to-rome' === $p['post_name'] && 'Five days.' === $p['post_excerpt'], json_encode( $p ) );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
