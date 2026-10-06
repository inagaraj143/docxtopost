<?php
/**
 * Admin page: what the Pro version adds.
 *
 * This page is informational. It describes Pro and links out to buy it; it
 * does not render a disabled copy of any Pro screen. The Plugin Directory
 * permits upselling (guideline 5) but not shipping functionality that is
 * present and locked until payment, a working-looking Bulk Import screen
 * that refused to run would be the latter.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

$count = dtpost_conversion_count();

$pillars = array(
	array(
		'title' => __( 'Bulk import', 'docxtowp' ),
		'lead'  => __( 'Drag in up to 100 .docx and .md files and walk away.', 'docxtowp' ),
		'points' => array(
			__( 'One file per request, so shared hosting limits on upload count, post size and execution time never come into it.', 'docxtowp' ),
			__( 'A corrupt document fails its own row and nothing else.', 'docxtowp' ),
			__( 'Pause, resume, cancel, or retry only the failures.', 'docxtowp' ),
			__( 'Close the tab and come back, the queue lives in the database, not the page.', 'docxtowp' ),
		),
	),
	array(
		'title' => __( 'A whole notebook, images and all', 'docxtowp' ),
		'lead'  => __( 'Markdown files and the folder their images live in, together.', 'docxtowp' ),
		'points' => array(
			__( 'Drop the image folder in with the notes and every image referenced by a relative path is matched by its filename and placed in the post.', 'docxtowp' ),
			__( 'A Joplin _resources folder, an Obsidian attachments folder or a Notion export all work the same way.', 'docxtowp' ),
			__( 'The first image in a note becomes its featured image unless you pick another.', 'docxtowp' ),
			__( 'Anything that cannot be matched is named on that row rather than dropped in silence.', 'docxtowp' ),
		),
	),
	array(
		'title' => __( 'Review before anything is created', 'docxtowp' ),
		'lead'  => __( 'A table of every document, editable, before a single post exists.', 'docxtowp' ),
		'points' => array(
			__( 'Detected title, featured image and SEO fields for each file, all editable per row.', 'docxtowp' ),
			__( 'Leave any document out of the run with one toggle.', 'docxtowp' ),
			__( 'Nothing is written to your site until you start the import.', 'docxtowp' ),
		),
	),
	array(
		'title' => __( 'Drip publishing', 'docxtowp' ),
		'lead'  => __( 'Schedule a batch instead of publishing it all at once.', 'docxtowp' ),
		'points' => array(
			__( 'Set a start time and a spacing (every N hours, days, weeks or months), and every document gets its own slot.', 'docxtowp' ),
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
	/*
	 * Shipped in Pro 1.3.0 on 25 September 2026, so this is written in the
	 * present tense.
	 *
	 * It was labelled "coming in Pro 1.3.0" until then, and anything added
	 * here before it is downloadable should be labelled the same way: this
	 * plugin ships frozen to WordPress.org and sits on people's sites until
	 * they update, so a premature claim cannot be corrected for weeks. Label
	 * with a version rather than a date, a version stays true whenever it
	 * lands.
	 */
	array(
		'title' => __( 'Smart image optimization', 'docxtowp' ),
		'lead'  => __( 'Smaller images, without a second plugin.', 'docxtowp' ),
		'points' => array(
			__( 'JPEG and PNG images from your documents are converted to WebP as they are imported.', 'docxtowp' ),
			__( 'Only when the WebP is genuinely smaller: photographs shrink a lot, while flat logos and diagrams keep their original format.', 'docxtowp' ),
			__( 'Transparency is preserved, and your original images are never deleted or overwritten.', 'docxtowp' ),
			__( 'Each import reports how many images were converted and how much it saved.', 'docxtowp' ),
		),
	),
);

$also = array(
	__( 'One-click rollback of an entire import', 'docxtowp' ),
	__( 'Import history, with rollback from any past job', 'docxtowp' ),
	__( 'Duplicate handling: skip, import anyway, or update the existing post', 'docxtowp' ),
	__( 'Featured-image cascade that matches companion images to documents by filename', 'docxtowp' ),
	__( 'Any public post type, with the whole run going to the type you choose', 'docxtowp' ),
	__( 'Job-wide author, categories and tags, set once for the batch', 'docxtowp' ),
	__( 'Activity log with re-import', 'docxtowp' ),
	__( 'Automatic updates, the same as a plugin from WordPress.org', 'docxtowp' ),
	__( 'Email support from the developer', 'docxtowp' ),
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

	<?php
	/*
	 * The "I already paid" path, above the pitch rather than below it.
	 *
	 * This exists because of a real support thread. Someone bought Pro, came
	 * to this screen looking for somewhere to type their licence key, found a
	 * feature list and a buy button, and wrote in. Twice.
	 *
	 * The misunderstanding is reasonable and it is ours to fix: Pro is a
	 * separate plugin, not a key that unlocks this one. Nothing on this screen
	 * said so, and the purchase email said "download" of something they
	 * believed they had already installed. So a buyer who lands here sees
	 * nothing addressed to them at all.
	 *
	 * It sits above the pillars on purpose. Someone who has already paid is
	 * done being sold to, and making them scroll past five sections of pitch
	 * to find the answer is how the second email gets written.
	 */
	?>
	<div class="dtpost-upgrade__bought">
		<h2><?php esc_html_e( 'Already bought Pro? Start here', 'docxtowp' ); ?></h2>

		<p>
			<strong><?php esc_html_e( 'Pro is a separate plugin, not a key that unlocks this one.', 'docxtowp' ); ?></strong>
			<?php esc_html_e( 'There is no licence field on this screen because there is nothing here for a key to activate. You install DocxToWP Pro alongside this plugin, and the licence box is inside it.', 'docxtowp' ); ?>
		</p>

		<ol class="dtpost-upgrade__steps">
			<li>
				<?php
				printf(
					/* translators: %s: link to the download page */
					esc_html__( 'Download the Pro .zip from the link in your purchase email, or from %s.', 'docxtowp' ),
					'<a href="https://docxtowp.com/download" target="_blank" rel="noopener noreferrer">docxtowp.com/download</a>'
				);
				?>
				<?php esc_html_e( 'It is a different file from the free plugin, even though the download page looks familiar.', 'docxtowp' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Deactivate DocxToPost (this plugin). Both use the same admin menu, so running them together hides one of them.', 'docxtowp' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Go to Plugins → Add New → Upload Plugin, choose the .zip, then Install Now and Activate.', 'docxtowp' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'Open DocxToWP Pro → Licence, paste the key from your Dodo Payments email, and click Activate.', 'docxtowp' ); ?>
			</li>
		</ol>

		<p class="dtpost-upgrade__bought-note">
			<?php esc_html_e( 'Your imported posts are ordinary WordPress posts. Swapping plugins does not touch them.', 'docxtowp' ); ?>
		</p>

		<p>
			<a class="button" href="https://docxtowp.com/docs/activate-pro" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Full activation guide', 'docxtowp' ); ?>
			</a>
			<a class="button" href="mailto:support@docxtowp.com?subject=<?php echo rawurlencode( 'Activating DocxToWP Pro' ); ?>">
				<?php esc_html_e( 'Email support', 'docxtowp' ); ?>
			</a>
		</p>
	</div>

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
