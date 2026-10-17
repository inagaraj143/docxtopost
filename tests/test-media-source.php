<?php
/**
 * Importing a document already in the Media Library.
 *
 *   php tests/test-media-source.php
 *
 * The property that matters most is the one a user would never think to
 * check until it went wrong: **the attachment is copied, never used in
 * place.** The import path deletes the file it is given when a parse fails,
 * which is right for an upload and would destroy somebody's media if it were
 * handed the library's own path. So the suite asserts the original survives
 * byte for byte, and that the copy is a different file.
 *
 * The rest are the server-side checks on an id that arrives from a browser:
 * it must be an attachment, the user must be able to read it, and it must be
 * a real .docx rather than anything with the right extension.
 *
 * @package DocxToPost
 */

define( 'ABSPATH', true );
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1024 * 1024 );
}

$GLOBALS['dtpost_opts']  = array( 'dtpost_max_upload_mb' => 10 );
$GLOBALS['dtpost_posts'] = array();   // id => WP_Post
$GLOBALS['dtpost_files'] = array();   // id => path
$GLOBALS['dtpost_can']   = true;

function get_option( $k, $d = false ) { return $GLOBALS['dtpost_opts'][ $k ] ?? $d; }
function __( $s, $d = null ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function size_format( $b, $d = 0 ) { return round( $b / 1048576, 1 ) . ' MB'; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9 ._-]/', '', (string) $n ); }
function get_post( $id ) { return $GLOBALS['dtpost_posts'][ $id ] ?? null; }
function get_attached_file( $id ) { return $GLOBALS['dtpost_files'][ $id ] ?? false; }
function current_user_can( $cap, $id = 0 ) { return $GLOBALS['dtpost_can']; }
function wp_attachment_is_image( $id ) { return in_array( $id, $GLOBALS['dtpost_images'] ?? array(), true ); }

class WP_Error {
	private string $c;
	private string $m;
	public function __construct( $c = '', $m = '' ) { $this->c = (string) $c; $this->m = (string) $m; }
	public function get_error_code() { return $this->c; }
	public function get_error_message() { return $this->m; }
}

class WP_Post {
	public int $ID;
	public string $post_type;
	public function __construct( int $id, string $type ) { $this->ID = $id; $this->post_type = $type; }
}

require_once __DIR__ . '/../includes/class-dtpost-media-source.php';

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-58s %s%s\n", $label, $ok ? 'ok' : 'FAIL', '' !== $extra ? '  ' . $extra : '' );
	$ok ? $pass++ : $fail++;
}

$dir = sys_get_temp_dir() . '/dtpost-media-' . uniqid();
mkdir( $dir );

/** A real, minimal .docx registered as attachment $id. */
function attach_docx( int $id, string $name = 'Report.docx' ): string {
	global $dir;
	$path = $dir . '/' . $name;
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types/>' );
	$zip->addFromString( 'word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="x"><w:body/></w:document>' );
	$zip->close();
	$GLOBALS['dtpost_posts'][ $id ] = new WP_Post( $id, 'attachment' );
	$GLOBALS['dtpost_files'][ $id ] = $path;
	return $path;
}

// ── The original is never touched ─────────────────────────────────────────

echo "\nThe library's own file is copied, never used in place\n\n";

$original = attach_docx( 10 );
$before   = md5_file( $original );
$dest     = $dir . '/temp-import.docx';

$got = DTPost_Media_Source::copy_to_temp( 10, $dest );

check( 'a real .docx in the library is accepted', ! is_wp_error( $got ),
	is_wp_error( $got ) ? $got->get_error_message() : '' );
check( 'its name comes back for the title fallback', ! is_wp_error( $got ) && 'Report.docx' === $got['name'] );
check( 'a copy exists at the temp path', is_file( $dest ) );
check( 'and it is a different file from the original', realpath( $dest ) !== realpath( $original ) );
check( 'with the same contents', md5_file( $dest ) === $before );

// What the import path does on a parse failure.
unlink( $dest );
check( 'deleting the copy, as a failed parse does, leaves the original',
	is_file( $original ) && md5_file( $original ) === $before );

// ── An id from a browser is checked, not trusted ─────────────────────────

echo "\nChecks on an id that arrived in a form post\n\n";

$cases = array(
	'no id at all'                     => array( 0, 'dtpost_media_none' ),
	'an id for nothing'                => array( 999, 'dtpost_media_missing' ),
);
foreach ( $cases as $label => $spec ) {
	$got = DTPost_Media_Source::copy_to_temp( $spec[0], $dir . '/x.docx' );
	check( $label . ' is refused', is_wp_error( $got ) && $spec[1] === $got->get_error_code(),
		is_wp_error( $got ) ? $got->get_error_code() : 'accepted' );
}

// A post, not an attachment: somebody editing the id in the form.
$GLOBALS['dtpost_posts'][20] = new WP_Post( 20, 'post' );
$got = DTPost_Media_Source::copy_to_temp( 20, $dir . '/x.docx' );
check( 'an ordinary post id is refused', is_wp_error( $got ) && 'dtpost_media_missing' === $got->get_error_code() );

attach_docx( 30, 'Private.docx' );
$GLOBALS['dtpost_can'] = false;
$got = DTPost_Media_Source::copy_to_temp( 30, $dir . '/x.docx' );
check( 'an attachment the user cannot read is refused',
	is_wp_error( $got ) && 'dtpost_media_denied' === $got->get_error_code() );
check( 'and nothing was copied', ! is_file( $dir . '/x.docx' ) );
$GLOBALS['dtpost_can'] = true;

// ── It must really be a Word document ─────────────────────────────────────

echo "\nOnly real Word documents\n\n";

$GLOBALS['dtpost_posts'][40] = new WP_Post( 40, 'attachment' );
$GLOBALS['dtpost_files'][40] = $dir . '/photo.jpg';
file_put_contents( $dir . '/photo.jpg', str_repeat( 'x', 100 ) );
$got = DTPost_Media_Source::copy_to_temp( 40, $dir . '/x.docx' );
check( 'an image is refused', is_wp_error( $got ) && 'dtpost_media_notdocx' === $got->get_error_code() );

$GLOBALS['dtpost_posts'][41] = new WP_Post( 41, 'attachment' );
$GLOBALS['dtpost_files'][41] = $dir . '/fake.docx';
file_put_contents( $dir . '/fake.docx', 'not a zip at all' );
$got = DTPost_Media_Source::copy_to_temp( 41, $dir . '/x.docx' );
check( 'a renamed non-zip is refused', is_wp_error( $got ) && 'dtpost_media_corrupt' === $got->get_error_code() );

$GLOBALS['dtpost_posts'][42] = new WP_Post( 42, 'attachment' );
$GLOBALS['dtpost_files'][42] = $dir . '/plain.docx';
$zip = new ZipArchive();
$zip->open( $dir . '/plain.docx', ZipArchive::CREATE | ZipArchive::OVERWRITE );
$zip->addFromString( 'readme.txt', 'x' );
$zip->close();
$got = DTPost_Media_Source::copy_to_temp( 42, $dir . '/x.docx' );
check( 'a zip with no word/document.xml is refused', is_wp_error( $got ) && 'dtpost_media_notdocx' === $got->get_error_code() );

$GLOBALS['dtpost_posts'][43] = new WP_Post( 43, 'attachment' );
$GLOBALS['dtpost_files'][43] = $dir . '/gone.docx';
$got = DTPost_Media_Source::copy_to_temp( 43, $dir . '/x.docx' );
check( 'a library entry whose file has gone is refused', is_wp_error( $got ) && 'dtpost_media_gone' === $got->get_error_code() );

attach_docx( 44, 'Big.docx' );
$GLOBALS['dtpost_opts']['dtpost_max_upload_mb'] = 0;
$got = DTPost_Media_Source::copy_to_temp( 44, $dir . '/x.docx' );
check( 'a document over the site limit is refused', is_wp_error( $got ) && 'dtpost_media_toolarge' === $got->get_error_code() );
$GLOBALS['dtpost_opts']['dtpost_max_upload_mb'] = 10;

// ── A featured image from the library ────────────────────────────────────

echo "\nA featured image chosen from the Media Library\n\n";

$GLOBALS['dtpost_posts'][50] = new WP_Post( 50, 'attachment' );
$GLOBALS['dtpost_images']    = array( 50 );

check( 'an image attachment is used as it stands', 50 === DTPost_Media_Source::featured_image( 50 ) );

$got = DTPost_Media_Source::featured_image( 10 );   // the .docx from earlier
check( 'a document is refused as a featured image',
	is_wp_error( $got ) && 'dtpost_media_img_notimage' === $got->get_error_code() );

$got = DTPost_Media_Source::featured_image( 20 );   // an ordinary post
check( 'a post id is refused', is_wp_error( $got ) && 'dtpost_media_img_missing' === $got->get_error_code() );

check( 'nothing at all is refused', is_wp_error( DTPost_Media_Source::featured_image( 0 ) ) );

$GLOBALS['dtpost_can'] = false;
$got = DTPost_Media_Source::featured_image( 50 );
check( 'an image the user cannot read is refused', is_wp_error( $got ) && 'dtpost_media_img_denied' === $got->get_error_code() );
$GLOBALS['dtpost_can'] = true;

// ── Wiring ────────────────────────────────────────────────────────────────

echo "\nWired into the free importer\n\n";

$plugin = dirname( __DIR__ );
$main   = (string) file_get_contents( $plugin . '/docxtopost.php' );
$upload = (string) file_get_contents( $plugin . '/admin/upload-page.php' );
$js     = (string) file_get_contents( $plugin . '/assets/admin-v2.js' );
$css    = (string) file_get_contents( $plugin . '/assets/admin.css' );

check( 'the plugin loads the class', str_contains( $main, 'includes/class-dtpost-media-source.php' ) );
check( 'the document has Upload and Media Library tabs',
	str_contains( $upload, 'data-source="file"' ) && str_contains( $upload, 'data-source="media"' ) );
check( 'upload is the default', (bool) preg_match( '/id="dtpost-source-media"[^>]*hidden/', $upload ) );
check( 'there is no Google Docs tab in the free plugin', ! str_contains( $upload, 'google' ) );
check( 'the featured image has the same two tabs',
	str_contains( $upload, 'data-image-source="upload"' ) && str_contains( $upload, 'data-image-source="media"' ) );
check( 'the handler copies a library document rather than reading it in place',
	str_contains( $main, 'DTPost_Media_Source::copy_to_temp($media_id, $temp_file)' ) );
check( 'a failed parse deletes the copy, never the attachment',
	(bool) preg_match( '/copy_to_temp\(\$media_id, \$temp_file\).*?\/\/ The copy, never the attachment\.\s*wp_delete_file\(\$temp_file\)/s', $main ) );
check( 'the original upload path is untouched inside the else',
	(bool) preg_match( '/\} else \{\s*\/\/ Validate file was actually uploaded\./s', $main ) );
check( 'a library featured image is checked before parsing',
	strpos( $main, 'DTPost_Media_Source::featured_image($featured_media_id)' ) < strpos( $main, 'DTPost_Media_Source::copy_to_temp(' ) );
check( 'and used only when nothing was uploaded',
	str_contains( $main, "} elseif (\$featured_media_id > 0) {" ) );
check( 'wp.media is loaded on the upload screen only',
	str_contains( $main, "if ('docxtowp' === \$page) {\n\t\twp_enqueue_media();" ) );
check( 'the document picker filters to the type the server accepts',
	str_contains( $main, "'docx_mime' => DTPost_Media_Source::MIME" ) && str_contains( $js, 'library: { type: DTPOST.docx_mime }' ) );
check( 'only the visible source of each is submitted',
	str_contains( $js, "fd.delete('docx_file')" ) && str_contains( $js, "fd.delete('dtpost_media_id')" )
	&& str_contains( $js, "fd.delete('featured_image')" ) && str_contains( $js, "fd.delete('dtpost_featured_media_id')" ) );
check( 'switching panels does not shift the layout',
	(bool) preg_match( '/\.dtpost-source-stack > \.dtpost-source-panel\[hidden\] \{[^}]*display: flex;[^}]*visibility: hidden/s', $css ) );
check( 'the tabs are a real tablist', str_contains( $upload, 'role="tablist"' ) && str_contains( $js, "setAttribute('aria-selected'" ) );

array_map( 'unlink', glob( $dir . '/*' ) ?: array() );
rmdir( $dir );

printf( "\n  %d passed, %d failed.\n\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
