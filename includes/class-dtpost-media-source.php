<?php
/**
 * Importing a document that is already in the Media Library.
 *
 * WordPress accepts .docx uploads by default, so plenty of sites already have
 * documents sitting in the Media Library: dropped there by a client, carried
 * over by a migration, or uploaded by somebody who assumed that was how you
 * got a Word file onto a site. Asking them to download the file off their own
 * site and upload it again is a strange thing to ask.
 *
 * Nothing here converts anything. It only decides which file is handed to
 * DTPost_Parser.
 *
 * ── The file is copied, not used where it lies ────────────────────────────
 *
 * The import owns the file it is given, and deletes it when a parse fails.
 * That is right for an upload and would destroy somebody's media if it were
 * handed the library's own path. So the attachment is copied into the temp
 * directory and the import is given the copy; the original is never opened
 * for writing and never deleted.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Media_Source {

	/** What the picker offers and what this will accept. */
	const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

	/**
	 * Copies an attachment into the temp directory, ready to import.
	 *
	 * Every check is on the server, because the attachment id arrives from the
	 * browser and a browser can send any number.
	 *
	 * @param int    $attachment_id The id the picker returned.
	 * @param string $dest          Absolute path in the temp directory.
	 * @return array{name:string}|WP_Error The original filename, for the title fallback.
	 */
	public static function copy_to_temp( int $attachment_id, string $dest ) {
		if ( $attachment_id < 1 ) {
			return new WP_Error( 'dtpost_media_none', __( 'No document was chosen.', 'docxtowp' ) );
		}

		$post = get_post( $attachment_id );

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'dtpost_media_missing',
				__( 'That item is not in the Media Library any more. Choose another.', 'docxtowp' )
			);
		}

		// The picker already only shows what this user can see; this makes
		// sure of it, since the id is a number in a form post.
		if ( ! current_user_can( 'read_post', $attachment_id ) ) {
			return new WP_Error(
				'dtpost_media_denied',
				__( 'You do not have permission to use that file.', 'docxtowp' )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'dtpost_media_gone',
				__( 'That file is listed in the Media Library but is not on the server any more.', 'docxtowp' )
			);
		}

		if ( 'docx' !== strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error(
				'dtpost_media_notdocx',
				__( 'That is not a Word document. Choose a .docx file, or use the upload tab for Markdown.', 'docxtowp' )
			);
		}

		$size = (int) filesize( $path );
		$max  = (int) get_option( 'dtpost_max_upload_mb', 10 ) * MB_IN_BYTES;

		if ( $size < 1 ) {
			return new WP_Error( 'dtpost_media_empty', __( 'That file is empty.', 'docxtowp' ) );
		}

		if ( $size > $max ) {
			return new WP_Error(
				'dtpost_media_toolarge',
				sprintf(
					/* translators: 1: file size, 2: the site's configured limit */
					__( 'That document is %1$s, which is over this site\'s %2$s limit.', 'docxtowp' ),
					size_format( $size ),
					size_format( $max )
				)
			);
		}

		// The real test: a .docx is a zip with word/document.xml in it. An
		// extension proves nothing.
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'dtpost_no_zip', __( 'ZipArchive PHP extension is not available.', 'docxtowp' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error(
				'dtpost_media_corrupt',
				__( 'That file could not be read. It may be corrupted, or not really a .docx.', 'docxtowp' )
			);
		}
		$has_document = false !== $zip->locateName( 'word/document.xml' );
		$zip->close();

		if ( ! $has_document ) {
			return new WP_Error(
				'dtpost_media_notdocx',
				__( 'That is not a valid Word document (.docx).', 'docxtowp' )
			);
		}

		// Copy, never move, and never hand the original path onward.
		if ( ! @copy( $path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error(
				'dtpost_media_copy',
				__( 'The document could not be copied for import. Check the uploads directory is writable.', 'docxtowp' )
			);
		}

		return array( 'name' => sanitize_file_name( basename( $path ) ) );
	}

	/**
	 * A featured image chosen from the Media Library, checked.
	 *
	 * Nothing is copied: a featured image is only referenced by id, and
	 * nothing deletes one after an import.
	 *
	 * @return int|WP_Error The attachment id to use.
	 */
	public static function featured_image( int $attachment_id ) {
		$post = $attachment_id > 0 ? get_post( $attachment_id ) : null;

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'dtpost_media_img_missing',
				__( 'That featured image is not in the Media Library any more. Choose another, or leave it empty.', 'docxtowp' )
			);
		}

		if ( ! current_user_can( 'read_post', $attachment_id ) ) {
			return new WP_Error(
				'dtpost_media_img_denied',
				__( 'You do not have permission to use that image.', 'docxtowp' )
			);
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'dtpost_media_img_notimage',
				__( 'The featured image has to be an image.', 'docxtowp' )
			);
		}

		return $attachment_id;
	}
}
