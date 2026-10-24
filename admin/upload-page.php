<?php
/**
 * Admin page: Upload .docx or Markdown + featured image.
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
		<p><?php esc_html_e( 'Convert DOCX or Markdown to a post, page or custom post type, no copy-paste', 'docxtowp' ); ?></p>

		<?php dtpost_header_actions( 'header-upload' ); ?>
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
					<?php
					/*
					 * Where the document comes from: an upload, or a .docx
					 * already in the Media Library. Upload is first and
					 * selected, because it is what almost every import is.
					 * Switching clears the other, so only one is submitted.
					 */
					?>
					<div class="dtpost-source-tabs" id="dtpost-doc-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Where the document comes from', 'docxtowp' ); ?>">
						<button type="button" class="dtpost-source-tab is-active" id="dtpost-tab-file"
							role="tab" aria-selected="true" aria-controls="dtpost-source-file" data-source="file">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
							<?php esc_html_e( 'Upload a file', 'docxtowp' ); ?>
						</button>
						<button type="button" class="dtpost-source-tab" id="dtpost-tab-google"
							role="tab" aria-selected="false" aria-controls="dtpost-source-google" data-source="google">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
							<?php esc_html_e( 'Google Docs link', 'docxtowp' ); ?>
						</button>
						<button type="button" class="dtpost-source-tab" id="dtpost-tab-media"
							role="tab" aria-selected="false" aria-controls="dtpost-source-media" data-source="media">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
							<?php esc_html_e( 'Media Library', 'docxtowp' ); ?>
						</button>
					</div>

					<?php
					/*
					 * Both panels share one grid cell and stay laid out; only
					 * the active one is visible. The cell is always as tall as
					 * the taller panel, so switching tabs moves nothing below.
					 */
					?>
					<div class="dtpost-source-stack">
						<div class="dtpost-source-panel" id="dtpost-source-file" role="tabpanel" aria-labelledby="dtpost-tab-file">
							<div class="dtpost-dropzone dtpost-source-box" id="dtpost-docx-dropzone">
								<div class="dtpost-dropzone__icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
								</div>
								<p class="dtpost-dropzone__label"><?php esc_html_e( 'Drop your .docx or .md file here', 'docxtowp' ); ?></p>
								<p class="dtpost-dropzone__sub"><?php printf( esc_html__( 'or click to browse: max %s MB', 'docxtowp' ), esc_html( $max_mb ) ); ?></p>
								<?php
								// The field keeps its "docx" name: admin-v2.js and the AJAX
								// handler both refer to it, and a browser holding the old
								// script would otherwise post a field the server ignores.
								?>
								<input type="file" name="docx_file" id="docx_file" accept=".docx,.md,.markdown" class="dtpost-dropzone__input">
								<p class="dtpost-dropzone__filename" id="dtpost-docx-name"></p>
							</div>
						</div>

						<?php
						/*
						 * A shared Google Doc, by its link (1.4.0). The plugin
						 * downloads it as .docx from docs.google.com and imports
						 * it like an upload; that download is the only request
						 * this plugin ever makes, and only when this is used.
						 */
						?>
						<div class="dtpost-source-panel" id="dtpost-source-google" role="tabpanel" aria-labelledby="dtpost-tab-google" hidden>
							<div class="dtpost-source-box dtpost-media-panel dtpost-google-panel">
								<div class="dtpost-media-panel__icon">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
								</div>
								<label class="dtpost-media-panel__label" for="dtpost_google_link"><?php esc_html_e( 'Paste a Google Docs link', 'docxtowp' ); ?></label>
								<input type="url" name="dtpost_google_link" id="dtpost_google_link" class="dtpost-google-input"
									placeholder="https://docs.google.com/document/d/…" autocomplete="off" spellcheck="false">
								<p class="dtpost-media-panel__hint">
									<?php
									printf(
										/* translators: 1: Share, 2: General access, 3: Anyone with the link, all Google Docs menu labels */
										esc_html__( 'The document has to be shared: %1$s → %2$s → %3$s. View access is enough, and you can restrict it again after importing.', 'docxtowp' ),
										'<strong>' . esc_html__( 'Share', 'docxtowp' ) . '</strong>',
										'<strong>' . esc_html__( 'General access', 'docxtowp' ) . '</strong>',
										'<strong>' . esc_html__( 'Anyone with the link', 'docxtowp' ) . '</strong>'
									);
									?>
								</p>
								<p class="dtpost-media-panel__hint">
									<?php
									printf(
										/* translators: %s: link to DocxToWP Pro */
										esc_html__( 'Private documents straight from Google Drive, no sharing needed, and many at once: %s', 'docxtowp' ),
										'<a href="' . esc_url( dtpost_pro_url( 'google-tab' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'DocxToWP Pro', 'docxtowp' ) . '</a>'
									);
									?>
								</p>
							</div>
						</div>

						<div class="dtpost-source-panel" id="dtpost-source-media" role="tabpanel" aria-labelledby="dtpost-tab-media" hidden>
							<div class="dtpost-source-box dtpost-media-panel">
								<div class="dtpost-media-panel__icon">
									<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
								</div>
								<p class="dtpost-media-panel__label"><?php esc_html_e( 'Choose a document already on this site', 'docxtowp' ); ?></p>
								<button type="button" class="dtpost-btn dtpost-btn-secondary" id="dtpost-media-pick">
									<?php esc_html_e( 'Open Media Library', 'docxtowp' ); ?>
								</button>
								<input type="hidden" name="dtpost_media_id" id="dtpost_media_id" value="">
								<p class="dtpost-media-chosen is-empty" id="dtpost-media-chosen">
									<span class="dtpost-media-chosen__name" id="dtpost-media-chosen-name"></span>
									<button type="button" class="dtpost-media-chosen__clear" id="dtpost-media-clear"><?php esc_html_e( 'Remove', 'docxtowp' ); ?></button>
								</p>
								<p class="dtpost-media-panel__hint">
									<?php esc_html_e( 'Word documents (.docx) in your Media Library. Nothing is changed or removed: the file is copied for the import and the original stays where it is.', 'docxtowp' ); ?>
								</p>
							</div>
						</div>
					</div>

					<?php
					/*
					 * Featured image: upload one, or use one already on the
					 * site. The tabs share the heading's rule. A library image
					 * is used as it is; nothing deletes a featured image.
					 */
					?>
					<div class="dtpost-section-title dtpost-section-title--tabs" style="margin-top:20px">
						<span class="dtpost-section-title__text">
							<?php esc_html_e( 'Featured Image', 'docxtowp' ); ?>
							<span style="font-weight:400;color:#8c8f94;font-size:12px"><?php esc_html_e( 'optional', 'docxtowp' ); ?></span>
						</span>
						<div class="dtpost-source-tabs dtpost-source-tabs--inline" id="dtpost-img-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Where the featured image comes from', 'docxtowp' ); ?>">
							<button type="button" class="dtpost-source-tab is-active" id="dtpost-imgtab-upload"
								role="tab" aria-selected="true" aria-controls="dtpost-img-upload" data-image-source="upload">
								<?php esc_html_e( 'Upload', 'docxtowp' ); ?>
							</button>
							<button type="button" class="dtpost-source-tab" id="dtpost-imgtab-media"
								role="tab" aria-selected="false" aria-controls="dtpost-img-media" data-image-source="media">
								<?php esc_html_e( 'Media Library', 'docxtowp' ); ?>
							</button>
						</div>
					</div>

					<div class="dtpost-source-stack">
						<div class="dtpost-source-panel" id="dtpost-img-upload" role="tabpanel" aria-labelledby="dtpost-imgtab-upload">
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
						</div>

						<div class="dtpost-source-panel" id="dtpost-img-media" role="tabpanel" aria-labelledby="dtpost-imgtab-media" hidden>
							<div class="dtpost-img-upload-area">
								<button type="button" class="dtpost-img-thumb dtpost-img-thumb--pick" id="dtpost-img-media-thumb"
									aria-label="<?php esc_attr_e( 'Choose a featured image from the Media Library', 'docxtowp' ); ?>">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
								</button>
								<div class="dtpost-img-info">
									<button type="button" class="dtpost-btn dtpost-btn-secondary" id="dtpost-img-media-pick">
										<?php esc_html_e( 'Choose from Media Library', 'docxtowp' ); ?>
									</button>
									<span class="dtpost-img-media-meta">
										<span id="dtpost-img-media-name"><?php esc_html_e( 'An image already on this site.', 'docxtowp' ); ?></span>
										<button type="button" class="dtpost-media-chosen__clear" id="dtpost-img-media-clear" hidden><?php esc_html_e( 'Remove', 'docxtowp' ); ?></button>
									</span>
								</div>
							</div>
							<input type="hidden" name="dtpost_featured_media_id" id="dtpost_featured_media_id" value="">
						</div>
					</div>

					<?php
					/*
					 * Advertising, not a control. See the function: the
					 * checkbox has no name, so nothing can submit it, and
					 * there is no layout importer in this plugin for anything
					 * to call if it did.
					 */
					dtpost_render_layout_promo( 'upload', 'layout-upload' );
					?>

					<!-- Error -->
					<div id="dtpost-upload-error" class="dtpost-upload-error" style="display:none"></div>
					<div id="dtpost-multi-files" class="dtpost-multi-files" role="status" style="display:none"></div>

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

		</div>

		<div class="dtpost-col-sidebar">
			<div class="dtpost-how-it-works">
				<h3><?php esc_html_e( 'How it works', 'docxtowp' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Upload your .docx or Markdown file and an optional featured image.', 'docxtowp' ); ?></li>
					<li><?php esc_html_e( 'Review parsed content: headings, paragraphs, bold, lists, tables, code and images all preserved.', 'docxtowp' ); ?></li>
					<li><?php esc_html_e( 'Choose your post type, category, tags and author, then publish or save as draft.', 'docxtowp' ); ?></li>
				</ol>
			</div>

			<?php dtpost_render_pro_card( 'upload-sidebar' ); ?>
		</div>
	</div>

</div>
</div>
