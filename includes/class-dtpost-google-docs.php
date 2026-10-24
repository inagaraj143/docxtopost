<?php
/**
 * Importing a Google Doc from its share link.
 *
 * ── What this class does, and what it deliberately does not ───────────────
 *
 * It turns a string somebody pasted into a readable .docx in the temp
 * directory, or into an error that says what to do about it. That is all. A
 * Google Doc exports as a Word file, so nothing here converts anything:
 * DTPost_Parser imports the download exactly as it imports an upload.
 *
 * The document has to be shared as "Anyone with the link", which the upload
 * screen says before anyone pastes. Choosing private documents from Google
 * Drive needs a Google sign-in and is a DocxToWP Pro feature.
 *
 * ── The one outbound request this plugin makes ────────────────────────────
 *
 * Only when someone imports from a Google Docs link, and only to
 * docs.google.com, for the document they named. readme.txt lists it under
 * "External services", as the plugin directory requires.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Google_Docs {

	/**
	 * Where a document is fetched from.
	 *
	 * A constant for a security reason: the URL requested is built here from
	 * an id that matched ID_PATTERN, and the string the user pasted is never
	 * fetched. Fetching a user-supplied URL from wp-admin would be server-side
	 * request forgery, someone pastes http://169.254.169.254/ and the site
	 * reads its host's cloud metadata.
	 */
	const EXPORT_URL = 'https://docs.google.com/document/d/%s/export?format=docx';

	/** A Drive file id: 25 to 60 URL-safe characters, nothing else. */
	const ID_PATTERN = '/^[A-Za-z0-9_-]{25,60}$/';

	/** Long enough for Google to generate a large document on demand. */
	const TIMEOUT = 30;

	/** Below this many bytes, nothing real came back. */
	const MIN_BYTES = 2048;

	/**
	 * The file id in a Google Docs URL.
	 *
	 * Accepts every shape Google hands out: the address bar, the Share
	 * dialog's ?usp=sharing, the multi-account /u/0/ form and the old
	 * /open?id= style. A Sheet, a Slides deck or a Drive file is named as
	 * such rather than failing generically.
	 *
	 * @param string $url Whatever was in the box.
	 * @return string|WP_Error The id, or why this is not a Google Doc link.
	 */
	public static function file_id( string $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			return new WP_Error( 'dtpost_gdocs_empty', __( 'Paste the link from the address bar of a Google Doc.', 'docxtowp' ) );
		}

		if ( 1 === preg_match( self::ID_PATTERN, $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = (string) ( $parts['path'] ?? '' );

		if ( '' === $host ) {
			return new WP_Error( 'dtpost_gdocs_notlink', __( 'That does not look like a link. Paste the address of a Google Doc, the one in your browser\'s address bar when the document is open.', 'docxtowp' ) );
		}

		if ( 'docs.google.com' === $host && str_starts_with( $path, '/spreadsheets/' ) ) {
			return new WP_Error( 'dtpost_gdocs_sheet', __( 'That is a Google Sheet. This imports Google Docs documents; a spreadsheet has no document to convert.', 'docxtowp' ) );
		}

		if ( 'docs.google.com' === $host && str_starts_with( $path, '/presentation/' ) ) {
			return new WP_Error( 'dtpost_gdocs_slides', __( 'That is a Google Slides presentation. This imports Google Docs documents.', 'docxtowp' ) );
		}

		if ( 'drive.google.com' === $host ) {
			return new WP_Error( 'dtpost_gdocs_drive', __( 'That is a file in Google Drive rather than a Google Doc. If it is a .docx, download it and use the Upload a file tab instead.', 'docxtowp' ) );
		}

		if ( 'docs.google.com' !== $host ) {
			return new WP_Error( 'dtpost_gdocs_nothost', __( 'That link is not on docs.google.com. Open the document in Google Docs and copy the address from your browser.', 'docxtowp' ) );
		}

		if ( preg_match( '#^/document/(?:u/\d+/)?d/([A-Za-z0-9_-]+)#', $path, $m ) ) {
			return self::check_id( $m[1] );
		}

		if ( str_starts_with( $path, '/open' ) ) {
			parse_str( (string) ( $parts['query'] ?? '' ), $query );
			if ( ! empty( $query['id'] ) ) {
				return self::check_id( (string) $query['id'] );
			}
		}

		return new WP_Error( 'dtpost_gdocs_noid', __( 'No document id was found in that link. Open the document in Google Docs and copy the address from your browser.', 'docxtowp' ) );
	}

	/** An id is only an id if it matches the allowlist. */
	private static function check_id( string $id ) {
		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			return new WP_Error( 'dtpost_gdocs_badid', __( 'The document id in that link does not look right. Copy the address again from the open document.', 'docxtowp' ) );
		}
		return $id;
	}

	/**
	 * Downloads a shared Google Doc as .docx.
	 *
	 * @param string $file_id A value already returned by file_id().
	 * @param string $dest    Absolute path to write to.
	 * @return array{name:string}|WP_Error The document's own name, for the title fallback.
	 */
	public static function fetch( string $file_id, string $dest ) {
		if ( 1 !== preg_match( self::ID_PATTERN, $file_id ) ) {
			return new WP_Error( 'dtpost_gdocs_badid', __( 'Invalid document id.', 'docxtowp' ) );
		}

		$response = wp_safe_remote_get(
			sprintf( self::EXPORT_URL, $file_id ),
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'dtpost_gdocs_unreachable', __( 'This site could not reach Google. Some hosts block outgoing connections; ask your host to allow requests to docs.google.com, or download the document as .docx and upload it instead.', 'docxtowp' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 429 === $code ) {
			return new WP_Error( 'dtpost_gdocs_ratelimited', __( 'Google is temporarily refusing requests from this site. Wait a few minutes and try again.', 'docxtowp' ) );
		}

		// "Not found" and "not shared" look identical from outside, by design
		// on Google's part, so the message covers both.
		if ( 404 === $code || 410 === $code ) {
			return self::not_shared();
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'dtpost_gdocs_http',
				sprintf(
					/* translators: %d: HTTP status code returned by Google */
					__( 'Google returned an unexpected response (%d). Try again, or download the document as .docx and upload it instead.', 'docxtowp' ),
					$code
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

		// An unshared document comes back as 200 with an HTML sign-in page,
		// not as an error, so the body is checked rather than the status.
		if ( str_contains( $type, 'text/html' ) || strlen( $body ) < self::MIN_BYTES || 'PK' !== substr( $body, 0, 2 ) ) {
			return self::not_shared();
		}

		$max = (int) get_option( 'dtpost_max_upload_mb', 10 ) * MB_IN_BYTES;
		if ( strlen( $body ) > $max ) {
			return new WP_Error(
				'dtpost_gdocs_toolarge',
				sprintf(
					/* translators: 1: document size, 2: the site's configured limit */
					__( 'That document is %1$s, which is over this site\'s %2$s limit.', 'docxtowp' ),
					size_format( strlen( $body ) ),
					size_format( $max )
				)
			);
		}

		if ( false === file_put_contents( $dest, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'dtpost_gdocs_write', __( 'The document downloaded but could not be saved. Check the uploads directory is writable.', 'docxtowp' ) );
		}

		return array( 'name' => self::filename( $response, $file_id ) );
	}

	private static function not_shared(): WP_Error {
		return new WP_Error(
			'dtpost_gdocs_notshared',
			__( 'Google would not hand over that document. Either the link is wrong, or the document is not shared. In Google Docs click Share, then under General access choose "Anyone with the link", and try again. View access is all the plugin needs.', 'docxtowp' )
		);
	}

	/** The document's own name, from the download headers. */
	private static function filename( $response, string $file_id ): string {
		$disposition = (string) wp_remote_retrieve_header( $response, 'content-disposition' );

		if ( preg_match( "/filename\*\s*=\s*UTF-8''([^;]+)/i", $disposition, $m ) ) {
			$name = rawurldecode( trim( $m[1] ) );
		} elseif ( preg_match( '/filename\s*=\s*"?([^";]+)"?/i', $disposition, $m ) ) {
			$name = trim( $m[1] );
		} else {
			$name = '';
		}

		$name = sanitize_file_name( $name );

		return '' !== $name ? $name : 'google-doc-' . substr( $file_id, 0, 8 ) . '.docx';
	}

	/**
	 * Checks a downloaded file is a real .docx: a zip with word/document.xml.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_file( string $path ) {
		if ( ! is_readable( $path ) || (int) filesize( $path ) < 1 ) {
			return new WP_Error( 'dtpost_gdocs_unreadable', __( 'The downloaded file could not be read.', 'docxtowp' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'dtpost_no_zip', __( 'ZipArchive PHP extension is not available.', 'docxtowp' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'dtpost_gdocs_notdocx', __( 'What came back from Google was not a Word document. The document may not be shared.', 'docxtowp' ) );
		}

		$has_document = false !== $zip->locateName( 'word/document.xml' );
		$zip->close();

		if ( ! $has_document ) {
			return new WP_Error( 'dtpost_gdocs_notdocx', __( 'What came back from Google was not a Word document. The document may not be shared.', 'docxtowp' ) );
		}

		return true;
	}
}
