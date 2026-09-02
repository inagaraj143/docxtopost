<?php
/**
 * Admin page: what the Pro version adds.
 *
 * This page is informational. It describes Pro and links out to buy it; it
 * does not render a disabled copy of any Pro screen. The Plugin Directory
 * permits upselling (guideline 5) but not shipping functionality that is
 * present and locked until payment — a working-looking Bulk Import screen
 * that refused to run would be the latter.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

$count = dtpost_conversion_count();

$pillars = array(
	array(
		'title' => __( 'Bulk import', 'docxtowp' ),
		'lead'  => __( 'Drag in up to 100 .docx files and walk away.', 'docxtowp' ),
		'points' => array(
			__( 'One file per request, so shared hosting limits on upload count, post size and execution time never come into it.', 'docxtowp' ),
			__( 'A corrupt document fails its own row and nothing else.', 'docxtowp' ),
			__( 'Pause, resume, cancel, or retry only the failures.', 'docxtowp' ),
			__( 'Close the tab and come back — the queue lives in the database, not the page.', 'docxtowp' ),
		),
	),
	array(
		'title' => __( 'Drip publishing', 'docxtowp' ),
		'lead'  => __( 'Schedule a batch instead of publishing it all at once.', 'docxtowp' ),
		'points' => array(
			__( 'Set a start time and a spacing — every N hours, days, weeks or months — and every document gets its own slot.', 'docxtowp' ),
			__( 'Choose which weekdays are allowed. Weekends are excluded by default.', 'docxtowp' ),
			__( 'Override any single date by hand without disturbing the rest of the sequence.', 'docxtowp' ),
			__( 'Monthly spacing uses calendar arithmetic, so the 31st becomes the 28th in February rather than slipping into March.', 'docxtowp' ),
		),
	),
	array(
		'title' => __( 'SEO automation', 'docxtowp' ),
		'lead'  => __( 'Fill Yoast and Rank Math from templates, per document.', 'docxtowp' ),
		'points' => array(
			__( 'SEO title, meta description and focus keyphrase for both Yoast SEO and Rank Math.', 'docxtowp' ),
			__( 'Templates resolve {title}, {sitename}, {excerpt}, {filename}, {category} and {date} for each document.', 'docxtowp' ),
			__( 'Override any single row where the template does not fit.', 'docxtowp' ),
		),
	),
);

$also = array(
	__( 'One-click rollback of an entire import', 'docxtowp' ),
	__( 'Import history, with rollback from any past job', 'docxtowp' ),
	__( 'Duplicate handling — skip, import anyway, or update the existing post', 'docxtowp' ),
	__( 'Featured-image cascade that matches companion images to documents by filename', 'docxtowp' ),
	__( 'Per-row title, featured image and SEO overrides before anything is published', 'docxtowp' ),
	__( 'Activity log with re-import', 'docxtowp' ),
	__( 'Automatic updates, the same as a plugin from WordPress.org', 'docxtowp' ),
	__( 'Priority email support from the developer', 'docxtowp' ),
);
?>
<div class="wrap dtpost-upgrade-wrap">

	<h1><?php esc_html_e( 'DocxToPost Pro', 'docxtowp' ); ?></h1>
	<p class="dtpost-upgrade__standfirst">
		<?php esc_html_e( 'The free plugin is a complete importer for one document at a time. Pro is what you want once there is a folder of them.', 'docxtowp' ); ?>
	</p>

	<?php if ( $count >= 3 ) : ?>
	<p class="dtpost-upgrade__context">
		<?php
		printf(
			/* translators: %d: number of documents the user has converted */
			esc_html__( 'You have converted %d documents so far, one upload at a time.', 'docxtowp' ),
			(int) $count
		);
		?>
	</p>
	<?php endif; ?>

	<div class="dtpost-upgrade__pillars">
		<?php foreach ( $pillars as $pillar ) : ?>
		<section class="dtpost-upgrade__pillar">
			<h2><?php echo esc_html( $pillar['title'] ); ?></h2>
			<p class="dtpost-upgrade__lead"><?php echo esc_html( $pillar['lead'] ); ?></p>
			<ul>
				<?php foreach ( $pillar['points'] as $point ) : ?>
				<li><?php echo esc_html( $point ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php endforeach; ?>
	</div>

	<section class="dtpost-upgrade__also">
		<h2><?php esc_html_e( 'Also included', 'docxtowp' ); ?></h2>
		<ul>
			<?php foreach ( $also as $item ) : ?>
			<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="dtpost-upgrade__cta">
		<h2><?php esc_html_e( 'Everything here comes with Pro', 'docxtowp' ); ?></h2>
		<p><?php esc_html_e( 'Pricing, a full feature comparison and the changelog are all on the site.', 'docxtowp' ); ?></p>
		<a class="button button-primary button-hero" href="<?php echo esc_url( dtpost_pro_url( 'upgrade-page' ) ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'See pricing and features', 'docxtowp' ); ?>
		</a>
	</section>

	<p class="dtpost-upgrade__note">
		<?php esc_html_e( 'Posts you have already imported are ordinary WordPress posts. Nothing here changes them, and nothing stops working if you never upgrade.', 'docxtowp' ); ?>
	</p>

</div>
