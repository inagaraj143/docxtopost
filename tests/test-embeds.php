<?php
/**
 * Video URLs and iframes become WordPress embeds.
 *
 *     php tests/test-embeds.php
 *
 * The cases that matter are the ones where nothing should happen: a link
 * inside a sentence, an unrecognised provider, a URL that merely looks like
 * one. Converting those would silently rewrite an author's text, which is a
 * worse failure than not converting at all.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['options'] = array( 'dtpost_convert_embeds' => 1 );

function __( $t, $d = '' ) { return $t; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
function esc_url( $u ) { return str_replace( array( '"', '<', '>' ), '', (string) $u ); }
function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }

require dirname( __DIR__ ) . "/includes/class-dtpost-embeds.php";

$fail = 0; $pass = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $fail, $pass;
	printf( "  %-62s %s\n", $label, $ok ? 'ok' : 'FAIL' );
	if ( $ok ) { $pass++; } else { $fail++; if ( '' !== $detail ) { echo "      $detail\n"; } }
}

/** Did this input become an embed, and for which canonical URL? */
function embedded( string $html ): string {
	$out = DTPost_Embeds::apply( $html );
	if ( ! str_contains( $out, 'wp:embed' ) ) { return ''; }
	return preg_match( '~"url":"([^"]+)"~', $out, $m ) ? stripslashes( $m[1] ) : 'embed-without-url';
}

echo "\nYouTube, in the shapes Word actually produces\n";

check( 'watch?v= link on its own line',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">https://www.youtube.com/watch?v=dQw4w9WgXcQ</a></p>' ),
	embedded( '<p><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">https://www.youtube.com/watch?v=dQw4w9WgXcQ</a></p>' ) );

check( 'bare watch?v= with no link',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>' ) );

check( 'youtu.be short link',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p>https://youtu.be/dQw4w9WgXcQ</p>' ) );

check( 'YouTube Shorts',
	'https://www.youtube.com/watch?v=abc123XYZ_-' === embedded( '<p>https://www.youtube.com/shorts/abc123XYZ_-</p>' ) );

check( 'mobile m.youtube.com',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p>https://m.youtube.com/watch?v=dQw4w9WgXcQ</p>' ) );

check( 'extra query params before v=',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p>https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ</p>' ) );

echo "\nVimeo\n";

check( 'vimeo.com/ID',
	'https://vimeo.com/123456789' === embedded( '<p>https://vimeo.com/123456789</p>' ) );

check( 'player.vimeo.com/video/ID',
	'https://vimeo.com/123456789' === embedded( '<p>https://player.vimeo.com/video/123456789</p>' ) );

echo "\nIframes — converted, never kept\n";

$iframe = '<p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315"></iframe></p>';
check( 'YouTube iframe becomes an embed',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( $iframe ) );

check( 'no <iframe> survives in the output',
	! str_contains( DTPost_Embeds::apply( $iframe ), '<iframe' ),
	DTPost_Embeds::apply( $iframe ) );

check( 'nocookie iframe is recognised',
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ' === embedded( '<p><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe></p>' ) );

$evil = '<p><iframe src="https://evil.example.com/tracker"></iframe></p>';
check( 'unknown provider iframe is dropped entirely',
	! str_contains( DTPost_Embeds::apply( $evil ), 'evil.example.com' )
	&& ! str_contains( DTPost_Embeds::apply( $evil ), '<iframe' ),
	DTPost_Embeds::apply( $evil ) );

echo "\nWhat must NOT be converted\n";

$sentence = '<p>We explain it in <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">this short video</a>, which is worth watching.</p>';
check( 'a link inside a sentence stays a link',
	'' === embedded( $sentence ) && str_contains( DTPost_Embeds::apply( $sentence ), 'this short video' ) );

check( 'link whose text differs from the href is left alone',
	'' === embedded( '<p><a href="https://youtu.be/dQw4w9WgXcQ">Watch the demo</a></p>' ) );

check( 'an unsupported provider stays a link',
	'' === embedded( '<p>https://example.com/video.mp4</p>' ) );

check( 'a plain paragraph is untouched',
	'' === embedded( '<p>Just some ordinary text.</p>' ) );

check( 'a URL with other text in the paragraph is untouched',
	'' === embedded( '<p>Source: https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>' ) );

check( 'a lookalike host is not matched',
	'' === embedded( '<p>https://notyoutube.com/watch?v=dQw4w9WgXcQ</p>' ) );

echo "\nThe setting, and the surrounding document\n";

$GLOBALS['options']['dtpost_convert_embeds'] = 0;
check( 'turned off, nothing is converted',
	'' === embedded( '<p>https://youtu.be/dQw4w9WgXcQ</p>' ) );
$GLOBALS['options']['dtpost_convert_embeds'] = 1;

$doc = '<h2>Heading</h2><p>Intro text.</p><p>https://youtu.be/dQw4w9WgXcQ</p><p>Closing text.</p>';
$out = DTPost_Embeds::apply( $doc );
check( 'the rest of the document is preserved',
	str_contains( $out, '<h2>Heading</h2>' )
	&& str_contains( $out, 'Intro text.' )
	&& str_contains( $out, 'Closing text.' )
	&& str_contains( $out, 'wp:embed' ), $out );

$two = '<p>https://youtu.be/aaaaaaaaaaa</p><p>https://vimeo.com/987654321</p>';
check( 'two videos in one document both convert',
	2 === substr_count( DTPost_Embeds::apply( $two ), 'wp:embed {' ) );

check( 'the block carries the provider slug',
	str_contains( DTPost_Embeds::apply( '<p>https://vimeo.com/123456789</p>' ), 'is-provider-vimeo' ) );

check( 'the URL sits on its own line inside the wrapper',
	(bool) preg_match( '~wp-block-embed__wrapper">\nhttps://vimeo\.com/123456789\n~', DTPost_Embeds::apply( '<p>https://vimeo.com/123456789</p>' ) ) );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
