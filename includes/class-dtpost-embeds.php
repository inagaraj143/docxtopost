<?php
/**
 * Video URLs and iframes become WordPress embeds.
 *
 * ── Why a block and not an iframe ─────────────────────────────────────────
 *
 * The obvious implementation is to keep the author's `<iframe>`. It cannot
 * work: `<iframe>` is not in WordPress's allowed post tags, and
 * DTPost_Publisher runs every import through wp_kses_post(), so the whole
 * element is stripped before it reaches the database. Tested, not assumed,
 * an iframe comes out of wp_kses_post() as an empty string.
 *
 * A `core/embed` block comment survives that pass untouched, renders in both
 * the block editor and the classic editor, and lets WordPress resolve the
 * provider through oEmbed at display time. So a recognised iframe is
 * *converted* to its canonical URL rather than preserved, which is also the
 * safer direction: nothing an author pasted reaches the page as raw HTML.
 *
 * ── Why a paragraph has to contain nothing else ───────────────────────────
 *
 * Only a paragraph whose entire content is one supported URL is converted. A
 * link inside a sentence stays a link, because turning "see this video" into
 * a 16:9 player mid-paragraph destroys the author's text. That is also what
 * someone pasting a YouTube URL on its own line in Word means by it.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Embeds {

	/** Settings key. On by default; a video that stays a link is a worse default. */
	public const OPT = 'dtpost_convert_embeds';

	/**
	 * Providers, as pattern => canonical URL template.
	 *
	 * Deliberately a fixed list rather than "anything that looks like a video".
	 * WordPress will happily oEmbed a provider it knows, but an unrecognised
	 * host pasted into a document should stay a link rather than becoming a
	 * silent request to a third party from every visitor's browser.
	 *
	 * `%s` is the captured id.
	 */
	private const PROVIDERS = array(
		// youtu.be/ID
		'~^https?://(?:www\.)?youtu\.be/([A-Za-z0-9_-]{6,})~i'                              => array( 'youtube', 'https://www.youtube.com/watch?v=%s' ),
		// youtube.com/watch?v=ID
		'~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:[^&]*&)*v=([A-Za-z0-9_-]{6,})~i'    => array( 'youtube', 'https://www.youtube.com/watch?v=%s' ),
		// youtube.com/shorts/ID
		'~^https?://(?:www\.|m\.)?youtube\.com/shorts/([A-Za-z0-9_-]{6,})~i'                 => array( 'youtube', 'https://www.youtube.com/watch?v=%s' ),
		// youtube.com/embed/ID and the nocookie variant, which is what an
		// iframe copied out of YouTube's share dialog actually contains.
		'~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,})~i'        => array( 'youtube', 'https://www.youtube.com/watch?v=%s' ),
		// vimeo.com/ID
		'~^https?://(?:www\.)?vimeo\.com/(?:video/)?([0-9]{6,})~i'                           => array( 'vimeo', 'https://vimeo.com/%s' ),
		// player.vimeo.com/video/ID
		'~^https?://player\.vimeo\.com/video/([0-9]{6,})~i'                                  => array( 'vimeo', 'https://vimeo.com/%s' ),
	);

	public static function enabled(): bool {
		return (bool) get_option( self::OPT, 1 );
	}

	/**
	 * The canonical embeddable URL for a supported provider.
	 *
	 * @return array{0:string,1:string}|null [canonical url, provider slug], or null.
	 */
	public static function match( string $url ): ?array {
		$url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $url ) {
			return null;
		}

		foreach ( self::PROVIDERS as $pattern => $spec ) {
			if ( preg_match( $pattern, $url, $m ) ) {
				return array( sprintf( $spec[1], $m[1] ), $spec[0] );
			}
		}

		return null;
	}

	/**
	 * Rewrites qualifying paragraphs and iframes as embed blocks.
	 *
	 * Runs on the assembled HTML, after the parser has finished, so it is the
	 * same whichever document type produced it.
	 */
	public static function apply( string $html ): string {
		if ( '' === trim( $html ) || ! self::enabled() ) {
			return $html;
		}

		// ── Iframes ──────────────────────────────────────────────────────
		//
		// A recognised provider becomes an embed. Anything else is dropped:
		// wp_kses_post() would delete the tag anyway, and leaving it here
		// only means the paragraph around it survives as a blank gap.
		$html = preg_replace_callback(
			'~<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*>.*?</iframe>~is',
			static function ( array $m ): string {
				$found = self::match( $m[1] );
				return $found ? self::block( $found[0], $found[1] ) : '';
			},
			$html
		);

		// ── Paragraphs that are nothing but a link ───────────────────────
		$html = preg_replace_callback(
			'~<p\b[^>]*>(.*?)</p>~is',
			static function ( array $m ): string {
				$inner = trim( $m[1] );

				// An <a> wrapping the URL is the usual case: Word turns a
				// pasted link into a hyperlink whose text is the URL.
				if ( preg_match( '~^<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>$~is', $inner, $a ) ) {
					$text = trim( wp_strip_all_tags( $a[2] ) );
					$href = $a[1];

					// Only when the visible text is the link itself. "Watch
					// the demo" linking to YouTube is a sentence the author
					// wrote, not a bare URL they expected to become a player.
					$same = '' === $text
						|| rtrim( $text, '/' ) === rtrim( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ), '/' );

					$found = $same ? self::match( $href ) : null;
					return $found ? self::block( $found[0], $found[1] ) : $m[0];
				}

				$text  = trim( wp_strip_all_tags( $inner ) );
				$found = ( $text === $inner ) ? self::match( $text ) : null;

				return $found ? self::block( $found[0], $found[1] ) : $m[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * One core/embed block.
	 *
	 * The URL sits on its own line inside the wrapper because that is where
	 * WordPress looks for it when resolving the embed on the front end.
	 */
	private static function block( string $url, string $provider ): string {
		$esc = esc_url( $url );

		$attrs = wp_json_encode(
			array(
				'url'              => $url,
				'type'             => 'video',
				'providerNameSlug' => $provider,
				'responsive'       => true,
				'className'        => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
			)
		);

		return "\n<!-- wp:embed {$attrs} -->\n"
			. '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider )
			. ' wp-block-embed-' . esc_attr( $provider )
			. ' wp-embed-aspect-16-9 wp-has-aspect-ratio">'
			. '<div class="wp-block-embed__wrapper">' . "\n"
			. $esc . "\n"
			. '</div></figure>' . "\n"
			. "<!-- /wp:embed -->\n";
	}
}
