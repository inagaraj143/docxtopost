<?php
/**
 * Admin page: Upload .docx + featured image.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

$max_mb      = (int) get_option( 'dtpost_max_upload_mb', 10 );
$has_session = (bool) get_transient( 'dtpost_session_' . get_current_user_id() );
?>
<div class="wrap">
<div class="dtpost-upload-wrap">

	<div class="dtpost-page-title">
		<h1><?php esc_html_e( 'DocxToPost', 'docxtowp' ); ?></h1>
		<p><?php esc_html_e( 'Convert DOCX to a post, page or custom post type — no copy-paste', 'docxtowp' ); ?></p>
	</div>

	<?php if ( $has_session ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'You have a document in progress.', 'docxtowp' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dtpost-preview' ) ); ?>"><strong><?php esc_html_e( 'Continue editing →', 'docxtowp' ); ?></strong></a>
		</p>
	</div>
	<?php endif; ?>

	<div class="dtpost-layout">
		<div class="dtpost-col-main">
			<form id="dtpost-upload-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'dtpost_nonce', 'nonce' ); ?>

				<div class="dtpost-upload-panel">
					<p class="dtpost-section-title"><?php esc_html_e( 'Word Document (.docx)', 'docxtowp' ); ?></p>

					<div class="dtpost-dropzone" id="dtpost-docx-dropzone">
						<div class="dtpost-dropzone__icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
						</div>
						<p class="dtpost-dropzone__label"><?php esc_html_e( 'Drop your .docx file here', 'docxtowp' ); ?></p>
						<p class="dtpost-dropzone__sub"><?php printf( esc_html__( 'or click to browse — max %s MB', 'docxtowp' ), esc_html( $max_mb ) ); ?></p>
						<input type="file" name="docx_file" id="docx_file" accept=".docx" class="dtpost-dropzone__input">
						<p class="dtpost-dropzone__filename" id="dtpost-docx-name"></p>
					</div>

					<p class="dtpost-section-title" style="margin-top:20px"><?php esc_html_e( 'Featured Image', 'docxtowp' ); ?> <span style="font-weight:400;color:#8c8f94;font-size:12px"><?php esc_html_e( 'optional', 'docxtowp' ); ?></span></p>

					<div class="dtpost-img-upload-area">
						<div class="dtpost-img-thumb" id="dtpost-img-thumb-inner">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
							<input type="file" name="featured_image" id="featured_image" accept="image/jpeg,image/png,image/gif,image/webp">
						</div>
						<div class="dtpost-img-info">
							<strong><?php esc_html_e( 'Click to select', 'docxtowp' ); ?></strong>
							<?php esc_html_e( 'JPEG, PNG, GIF or WebP', 'docxtowp' ); ?><br>
							<?php esc_html_e( 'Will be set as the post\'s featured image.', 'docxtowp' ); ?>
						</div>
					</div>

					<!-- Error -->
					<div id="dtpost-upload-error" class="dtpost-upload-error" style="display:none"></div>

					<!-- Progress -->
					<div class="dtpost-upload-progress" id="dtpost-progress" style="display:none">
						<div class="dtpost-prog-bar"><div class="dtpost-prog-fill" id="dtpost-prog-fill"></div></div>
						<p class="dtpost-prog-label" id="dtpost-prog-label"></p>
					</div>

					<div style="margin-top:20px">
						<button type="submit" class="dtpost-btn dtpost-btn-primary" id="dtpost-upload-btn" style="padding:10px 24px;font-size:15px;font-weight:600">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.5-1 1-4 2-5 2-5"></path><path d="M12 15v5s1 .5 4 1 5 2 5 2"></path></svg>
							<?php esc_html_e( 'Convert &amp; Preview', 'docxtowp' ); ?>
						</button>
					</div>
				</div>
			</form>

			<?php
			// Below the upload card, not above it. Someone who came here to
			// convert a document gets to do that first.
			dtpost_render_lifetime_notice();
			?>
		</div>

		<div class="dtpost-col-sidebar">
			<div class="dtpost-how-it-works">
				<h3><?php esc_html_e( 'How it works', 'docxtowp' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Upload your .docx file and an optional featured image.', 'docxtowp' ); ?></li>
					<li><?php esc_html_e( 'Review parsed content — headings, paragraphs, bold, lists and embedded images all preserved.', 'docxtowp' ); ?></li>
					<li><?php esc_html_e( 'Choose your post type, category, tags and author, then publish or save as draft.', 'docxtowp' ); ?></li>
				</ol>
			</div>

			<?php dtpost_render_pro_card( 'upload-sidebar' ); ?>
		</div>
	</div>

</div>
</div>
