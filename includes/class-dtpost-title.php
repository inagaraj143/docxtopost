<?php
/**
 * Turns a filename into a post title.
 *
 * Used only when a document has no heading to take a title from — a .docx
 * with no Heading 1 or 2, a Markdown file with no `# Heading` and no front
 * matter title. A document that *has* a heading keeps that heading's
 * capitalisation exactly as the author wrote it; nothing here touches it.
 *
 * ── Why this is a setting ─────────────────────────────────────────────────
 *
 * Until 1.2.2 this was `ucwords()`, which capitalises every word:
 *
 *     annual-report-for-the-board.docx  ->  "Annual Report For The Board"
 *
 * That is not any house style. US title case (AP, Chicago) lowercases short
 * prepositions and articles; UK usage generally prefers sentence case. A Pro
 * customer reported it as wrong for UK grammar, and he was right — but so
 * would an American have been, for a different reason. Rather than pick a
 * nationality, the mode is a setting with a sensible default.
 *
 * ── Sentence case never lowercases anything ───────────────────────────────
 *
 * The obvious implementation — lowercase everything, then capitalise the
 * first letter — destroys information that was in the filename:
 *
 *     meeting-with-John-Smith.docx  ->  "Meeting with john smith"
 *     q3-results-NHS.docx           ->  "Q3 results nhs"
 *
 * So SENTENCE only ever *adds* a capital to the first word and leaves every
 * other word exactly as typed. For the common all-lowercase hyphenated
 * filename that produces true sentence case; for a filename carrying proper
 * nouns or an acronym, those survive. It cannot make a title worse than the
 * filename it was given.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Title {

	/** First word capitalised, everything else exactly as written. Default. */
	const SENTENCE = 'sentence';

	/** Title Case, with short function words left lowercase. */
	const TITLE = 'title';

	/** Separators become spaces and nothing else changes. */
	const RAW = 'raw';

	/**
	 * Words that stay lowercase inside a title, unless they are first or last.
	 *
	 * The short list shared by AP and Chicago: articles, coordinating
	 * conjunctions, and prepositions of three letters or fewer. Longer
	 * prepositions ("through", "between") are capitalised by both.
	 */
	private const MINOR_WORDS = array(
		'a', 'an', 'the',
		'and', 'as', 'but', 'for', 'if', 'nor', 'or', 'so', 'yet',
		'at', 'by', 'in', 'of', 'off', 'on', 'per', 'to', 'up', 'via', 'vs',
	);

	/** @return string[] The three modes, for validating a stored setting. */
	public static function modes(): array {
		return array( self::SENTENCE, self::TITLE, self::RAW );
	}

	/**
	 * The stored preference, falling back to sentence case.
	 *
	 * Read here rather than passed in from four call sites, so the option
	 * name is written down once.
	 */
	public static function mode(): string {
		$mode = (string) get_option( 'dtpost_filename_title_case', self::SENTENCE );
		return in_array( $mode, self::modes(), true ) ? $mode : self::SENTENCE;
	}

	/**
	 * Converts a filename — with or without its extension — to a title.
	 *
	 * @param string      $filename `annual-report.docx` or `annual-report`.
	 * @param string|null $mode     One of the class constants. Null reads the setting.
	 */
	public static function from_filename( string $filename, ?string $mode = null ): string {
		$base = (string) pathinfo( $filename, PATHINFO_FILENAME );

		// Separators to spaces, then collapse runs. A filename like
		// "report_-_final.docx" should not become "Report   final".
		$base = str_replace( array( '-', '_', '.' ), ' ', $base );
		$base = trim( (string) preg_replace( '/\s+/u', ' ', $base ) );

		if ( '' === $base ) {
			return '';
		}

		$mode = null === $mode ? self::mode() : $mode;

		if ( self::RAW === $mode ) {
			return $base;
		}

		$words = explode( ' ', $base );

		if ( self::TITLE === $mode ) {
			$last = count( $words ) - 1;
			foreach ( $words as $i => $word ) {
				// An acronym the author typed in capitals stays as it is.
				if ( self::is_shout( $word ) ) {
					continue;
				}
				$lower = self::lower( $word );
				$words[ $i ] = ( $i > 0 && $i < $last && in_array( $lower, self::MINOR_WORDS, true ) )
					? $lower
					: self::upper_first( $lower );
			}
			return implode( ' ', $words );
		}

		// SENTENCE: capitalise the first word, touch nothing else.
		if ( ! self::is_shout( $words[0] ) ) {
			$words[0] = self::upper_first( $words[0] );
		}

		return implode( ' ', $words );
	}

	/**
	 * Whether a word is an acronym the author capitalised on purpose.
	 *
	 * Two or more characters with no lowercase letter in them: NHS, Q3, API,
	 * ISO9001. A single letter is excluded, so "a" is not mistaken for one.
	 */
	private static function is_shout( string $word ): bool {
		if ( mb_strlen( $word ) < 2 ) {
			return false;
		}
		return $word === self::upper( $word ) && $word !== self::lower( $word );
	}

	// ── Case helpers ────────────────────────────────────────────────────────
	//
	// mb_* where available. Without it, ucfirst() on a multibyte first letter
	// corrupts the character rather than capitalising it, and a filename in
	// any non-ASCII language would arrive as mojibake.

	private static function upper_first( string $word ): string {
		if ( '' === $word ) {
			return $word;
		}
		if ( function_exists( 'mb_substr' ) ) {
			return self::upper( mb_substr( $word, 0, 1 ) ) . mb_substr( $word, 1 );
		}
		return ucfirst( $word );
	}

	private static function upper( string $text ): string {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
	}

	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
