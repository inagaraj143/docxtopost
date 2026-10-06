<?php
/**
 * Admin page: what bulk import does, and why it is not in the free plugin.
 *
 * Deliberately contains no bulk import interface, not even a disabled one.
 * Guideline 5 prohibits shipping functionality that is present but locked
 * until payment; it explicitly permits describing a paid feature. A drop zone
 * that refused to accept files would be the former. This page is prose.
 *
 * The menu label says "(Pro)" for the same reason: someone arriving here
 * should already know they are reading about something they do not have.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

$count = dtpost_conversion_count();

$capabilities = array(
	array(
		'title' => __( 'Up to 100 documents per job', 'docxtowp' ),
		'body'  => __( 'Drag in a folder of .docx or .md files. Drop images in alongside them and each one is matched to its document by filename, chapter-01.jpg becomes the featured image for chapter-01.docx.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Markdown notebooks arrive with their images', 'docxtowp' ),
		'body'  => __( 'Drop the image folder in with your .md files and every image referenced by a relative path is matched by its filename and placed in the post: a Joplin _resources folder, an Obsidian attachments folder or a Notion export, moved across in one run instead of image by image in the editor.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Built for shared hosting', 'docxtowp' ),
		'body'  => __( 'Files upload one per request rather than in a single large POST. A 100-file POST dies on max_file_uploads, post_max_size, max_execution_time or memory on most hosts; one file per request hits none of them.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Review everything before it publishes', 'docxtowp' ),
		'body'  => __( 'A table of every document with its detected title, featured image and SEO fields, each editable per row, and a toggle to leave any document out of the run.', 'docxtowp' ),
	),
	array(
		'title' => __( 'One broken file does not stop the job', 'docxtowp' ),
		'body'  => __( 'A corrupt document fails its own row and the rest carry on. When the run finishes, "Retry failed" re-queues only those.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Close the tab and come back', 'docxtowp' ),
		'body'  => __( 'The queue lives in the database, not the page. Pause, resume or cancel at any point, and reopening the screen offers to pick up where the job stopped.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Undo the whole thing', 'docxtowp' ),
		'body'  => __( 'One click trashes exactly the posts that job created: tracked by ID, not guessed from titles or dates. Every past job stays in the import history with the same rollback available.', 'docxtowp' ),
	),
	array(
		'title' => __( 'Decide what happens to duplicates', 'docxtowp' ),
		'body'  => __( 'Per job: skip documents whose title already exists, import them anyway, or update the existing post in place.', 'docxtowp' ),
	),
	array(
		'title' => __( 'SEO for every document at once', 'docxtowp' ),
		'body'  => __( 'Templates using {title}, {sitename}, {excerpt}, {filename}, {category} and {date} resolve per document into Yoast or Rank Math, with per-row overrides where the template does not fit.', 'docxtowp' ),
	),
);
?>
<div class="wrap dtpost-upgrade-wrap">

	<h1><?php esc_html_e( 'Bulk Import', 'docxtowp' ); ?></h1>

	<div class="dtpost-pro-banner">
		<span class="dtpost-pro-card__badge"><?php esc_html_e( 'Pro', 'docxtowp' ); ?></span>
		<p>
			<?php esc_html_e( 'Bulk import is part of DocxToPost Pro. This page describes what it does: the free plugin converts one document at a time, which it will keep doing whether or not you upgrade.', 'docxtowp' ); ?>
		</p>
	</div>

	<p class="dtpost-upgrade__standfirst">
		<?php esc_html_e( 'The free importer is built around one document: upload, check it, publish. Bulk import is built around the case where checking each one individually is the actual work.', 'docxtowp' ); ?>
	</p>

	<?php if ( $count >= 3 ) : ?>
	<p class="dtpost-upgrade__context">
		<?php
		printf(
			/* translators: %d: number of documents the user has converted */
			esc_html__( 'You have converted %d documents here, one upload at a time.', 'docxtowp' ),
			(int) $count
		);
		?>
	</p>
	<?php endif; ?>

	<div class="dtpost-upgrade__pillars dtpost-upgrade__pillars--two">
		<?php foreach ( $capabilities as $capability ) : ?>
		<section class="dtpost-upgrade__pillar">
			<h2><?php echo esc_html( $capability['title'] ); ?></h2>
			<p><?php echo esc_html( $capability['body'] ); ?></p>
		</section>
		<?php endforeach; ?>
	</div>

	<section class="dtpost-upgrade__also">
		<h2><?php esc_html_e( 'Publishing 100 posts at once is usually not what you want', 'docxtowp' ); ?></h2>
		<p style="margin:0 0 12px;color:#50575e;font-size:13px;line-height:1.6;">
			<?php esc_html_e( 'So Pro can schedule a batch instead of publishing it. Set a start time and a spacing (every few hours, days, weeks or months) pick which weekdays are allowed, and every document gets its own publish slot. Weekends are excluded by default, and any single date can be overridden by hand without disturbing the rest of the sequence.', 'docxtowp' ); ?>
		</p>
	</section>

	<section class="dtpost-upgrade__cta">
		<h2><?php esc_html_e( 'Scheduling is a Pro feature', 'docxtowp' ); ?></h2>
		<p><?php esc_html_e( 'Pricing, the full feature list and the changelog are on the site.', 'docxtowp' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( dtpost_pro_url( 'bulk-teaser' ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'See pricing and features', 'docxtowp' ); ?>
		</a>
		<p class="dtpost-upgrade__note" style="margin-top:16px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dtpost-upgrade' ) ); ?>">
				<?php esc_html_e( 'Or see everything else Pro adds', 'docxtowp' ); ?>
			</a>
		</p>
	</section>

</div>
