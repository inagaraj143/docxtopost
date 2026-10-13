<?php
/**
 * Word text box extraction.
 *
 *   php tests/test-textboxes.php
 *
 * The case this exists for: a customer's newsletter template imported as
 * "Document appears to be empty". It was not empty. Every word in it lived
 * inside a text box, which the body walk never reached.
 *
 * The regression that matters just as much is the opposite one. Word writes
 * the same shape twice inside <mc:AlternateContent>, a modern DrawingML copy
 * in <mc:Choice> and a legacy VML copy in <mc:Fallback>, so a naive "collect
 * every w:txbxContent" imports the whole document twice. The real newsletter
 * has 22 text boxes and 11 of them are duplicates.
 *
 * Fixtures are built here as XML strings rather than as .docx files, because
 * what is being tested is the XML walk and a binary fixture would hide it.
 *
 * @package DocxToPost
 */

// Minimal WordPress surface, same approach as the other suites here.
define( 'ABSPATH', true );
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_url( $s ) { return (string) $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
	function __( $s, $d = null ) { return $s; }
}

require_once __DIR__ . '/../includes/class-dtpost-parser.php';

$parser = new DTPost_Parser();
$method = ( new ReflectionClass( $parser ) )->getMethod( 'xml_to_html' );
$method->setAccessible( true );

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-54s %s%s\n", $label, $ok ? 'ok' : 'FAIL', $extra ? '  ' . $extra : '' );
	$ok ? $pass++ : $fail++;
}

/** Wraps body XML in a document element with the namespaces Word declares. */
function doc( string $body ): string {
	return '<?xml version="1.0"?>'
		. '<w:document '
		. 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
		. 'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" '
		. 'xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" '
		. 'xmlns:v="urn:schemas-microsoft-com:vml" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
		. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<w:body>' . $body . '</w:body></w:document>';
}

/** A paragraph with one text run. */
function para( string $text ): string {
	return '<w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p>';
}

/** A modern DrawingML text box containing the given paragraphs. */
function drawing_box( string $paras ): string {
	return '<w:p><w:r><w:drawing><wps:txbx><w:txbxContent>' . $paras
		. '</w:txbxContent></wps:txbx></w:drawing></w:r></w:p>';
}

/** A legacy VML text box. */
function vml_box( string $paras ): string {
	return '<w:p><w:r><w:pict><v:shape><v:textbox><w:txbxContent>' . $paras
		. '</w:txbxContent></v:textbox></v:shape></w:pict></w:r></w:p>';
}

/**
 * The real Word shape: the same content in both branches of AlternateContent.
 */
function alternate_box( string $paras ): string {
	return '<w:p><w:r><mc:AlternateContent>'
		. '<mc:Choice Requires="wps"><w:drawing><wps:txbx><w:txbxContent>' . $paras
		. '</w:txbxContent></wps:txbx></w:drawing></mc:Choice>'
		. '<mc:Fallback><w:pict><v:shape><v:textbox><w:txbxContent>' . $paras
		. '</w:txbxContent></v:textbox></v:shape></w:pict></mc:Fallback>'
		. '</mc:AlternateContent></w:r></w:p>';
}

$render = function ( string $body ) use ( $parser, $method ): string {
	return $method->invoke( $parser, doc( $body ), [] );
};
$text = function ( string $html ): string {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( $html ) ) );
};

echo "\nThe bug: text only inside text boxes\n\n";

$html = $render( drawing_box( para( 'Boxed heading' ) . para( 'Boxed body' ) ) );
check( 'a DrawingML text box is extracted', str_contains( $text( $html ), 'Boxed heading' ) );
check( 'every paragraph in it survives', str_contains( $text( $html ), 'Boxed body' ) );
check( 'paragraph boundaries are kept', substr_count( $html, '<p' ) >= 2, substr_count( $html, '<p' ) . ' paragraphs' );
check(
	'a document of only text boxes is not empty',
	'' !== trim( $render( drawing_box( para( 'Only this' ) ) ) )
);

$html = $render( vml_box( para( 'Legacy boxed' ) ) );
check( 'a legacy VML text box is extracted', str_contains( $text( $html ), 'Legacy boxed' ) );

echo "\nThe opposite regression: no duplication\n\n";

$html = $render( alternate_box( para( 'Said once' ) ) );
check(
	'AlternateContent yields the text once, not twice',
	1 === substr_count( $text( $html ), 'Said once' ),
	substr_count( $text( $html ), 'Said once' ) . ' occurrences'
);

$html = $render( alternate_box( para( 'Alpha' ) ) . alternate_box( para( 'Beta' ) ) );
check( 'two boxes each appear once', 1 === substr_count( $text( $html ), 'Alpha' ) && 1 === substr_count( $text( $html ), 'Beta' ) );

echo "\nOrdinary documents must be unaffected\n\n";

$html = $render( para( 'Plain one' ) . para( 'Plain two' ) );
check( 'plain paragraphs still convert', str_contains( $text( $html ), 'Plain one Plain two' ) );
check( 'no stray text box markup leaks in', ! str_contains( $html, 'txbx' ) );

$body = '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>A heading</w:t></w:r></w:p>';
check( 'headings still become headings', str_contains( $render( $body ), '<h1' ) );

$mixed = para( 'In the flow' ) . drawing_box( para( 'In a box' ) );
$out   = $text( $render( $mixed ) );
check( 'flow text comes before box text', strpos( $out, 'In the flow' ) < strpos( $out, 'In a box' ), $out );

echo "\nEdge cases that must not crash\n\n";

check( 'an empty text box adds nothing', '' === trim( $render( drawing_box( '' ) ) ) );
check( 'a text box of blank paragraphs adds nothing', '' === trim( $render( drawing_box( '<w:p/><w:p/>' ) ) ) );
check( 'a genuinely empty body stays empty', '' === trim( $render( '' ) ) );
check(
	'AlternateContent with no Choice falls back',
	str_contains(
		$text( $render( '<w:p><w:r><mc:AlternateContent><mc:Fallback><w:pict><v:shape><v:textbox><w:txbxContent>' . para( 'Fallback only' ) . '</w:txbxContent></v:textbox></v:shape></w:pict></mc:Fallback></mc:AlternateContent></w:r></w:p>' ) ),
		'Fallback only'
	)
);
check(
	'a nested text box is not emitted twice',
	1 === substr_count( $text( $render( drawing_box( para( 'Outer' ) . drawing_box( para( 'Inner' ) ) ) ) ), 'Inner' )
);

echo "\nImages used as a shape background\n\n";

// Give the parser a relationship map, as parse() does from document.xml.rels.
$rids = ( new ReflectionClass( $parser ) )->getProperty( 'image_rids' );
$rids->setAccessible( true );
$rids->setValue( $parser, [ 'rId5' => 'image1.jpeg', 'rId7' => 'image3.jpeg', 'rId6' => 'image2.jpeg' ] );

/** The real shape-fill structure: wps:wsp > wps:spPr > a:blipFill > a:blip. */
function shape_image( string $rid, string $extra = '' ): string {
	return '<w:p><w:r><mc:AlternateContent>'
		. '<mc:Choice Requires="wps"><w:drawing><wp:anchor><a:graphic><a:graphicData>'
		. '<wps:wsp><wps:spPr><a:blipFill><a:blip r:embed="' . $rid . '"/></a:blipFill></wps:spPr>'
		. $extra
		. '</wps:wsp></a:graphicData></a:graphic></wp:anchor></w:drawing></mc:Choice>'
		// The Fallback carries the SAME picture under a DIFFERENT relationship,
		// pointing at a duplicate copy in the package. Reading it would add a
		// second attachment for one visible image.
		. '<mc:Fallback><w:pict><v:shape><v:fill r:id="rId6"/></v:shape></w:pict></mc:Fallback>'
		. '</mc:AlternateContent></w:r></w:p>';
}

$imgs = function ( string $html ): array {
	preg_match_all( '/data-docx-img="([^"]+)"/', $html, $m );
	return $m[1];
};

$out = $render( shape_image( 'rId5' ) );
check( 'a shape background image is extracted', [ 'image1.jpeg' ] === $imgs( $out ), implode( ',', $imgs( $out ) ) );
check( 'the Fallback copy is not also extracted', 1 === count( $imgs( $out ) ), count( $imgs( $out ) ) . ' images' );

$out = $render( shape_image( 'rId5' ) . shape_image( 'rId7' ) );
check( 'two shapes give two distinct images', [ 'image1.jpeg', 'image3.jpeg' ] === $imgs( $out ), implode( ',', $imgs( $out ) ) );

// A shape that has both a background picture and a text box in it.
$both = shape_image( 'rId5', '<wps:txbx><w:txbxContent>' . para( 'Caption text' ) . '</w:txbxContent></wps:txbx>' );
$out  = $render( $both );
check( 'image and text in one shape both survive', [ 'image1.jpeg' ] === $imgs( $out ) && str_contains( $text( $out ), 'Caption text' ), implode( ',', $imgs( $out ) ) );

check(
	'an unknown relationship id is ignored safely',
	[] === $imgs( $render( shape_image( 'rId999' ) ) )
);
check(
	'a shape with no picture adds no image',
	[] === $imgs( $render( '<w:p><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:drawing><wps:wsp><wps:spPr/></wps:wsp></w:drawing></mc:Choice></mc:AlternateContent></w:r></w:p>' ) )
);

echo "\nOrdinary images must be unaffected\n\n";

$inline = '<w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill>'
	. '<a:blip r:embed="rId5"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
check( 'an inline pic:pic image still extracts', [ 'image1.jpeg' ] === $imgs( $render( $inline ) ) );
check( 'text around it is kept', str_contains( $text( $render( para( 'Before' ) . $inline ) ), 'Before' ) );

printf( "\n  %d passed, %d failed.\n\n", $pass, $fail );
exit( $fail === 0 ? 0 : 1 );
