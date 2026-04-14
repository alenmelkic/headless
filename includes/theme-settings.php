<?php
/**
 * Theme Settings — Admin UI + GraphQL
 *
 * Native WordPress admin page for managing logo, favicon, and analytics IDs.
 * Data stored in wp_options; exposed to WPGraphQL without ACF.
 *
 * Options:
 *   headless_theme_logo_id      — attachment ID (int)
 *   headless_theme_logo_dark_id — dark-mode logo attachment ID (int)
 *   headless_theme_logo_alt     — alt text (string)
 *   headless_theme_logo_width   — display width in px (int, default 120)
 *   headless_theme_favicon_id   — attachment ID (int)
 *   headless_theme_clarity_id — Microsoft Clarity project ID (string)
 *   headless_theme_ga_id      — Google Analytics 4 measurement ID (string)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Helper — resolve attachment ID → image data
// ---------------------------------------------------------------------------

/**
 * Resolves a media attachment ID to the shape used in REST + GraphQL responses.
 *
 * @param int $id Attachment post ID.
 * @return array{url:string,altText:string,width:int,height:int}|null
 */
function headless_theme_resolve_image( int $id ): ?array {
	if ( ! $id ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $id, 'full' );
	if ( ! $src ) {
		return null;
	}
	return [
		'url'     => $src[0],
		'width'   => (int) $src[1],
		'height'  => (int) $src[2],
		'altText' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
	];
}


// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Theme Settings', 'headless' ),
		__( 'Theme Settings', 'headless' ),
		'manage_options',
		'theme-settings',
		'headless_theme_settings_render',
		'dashicons-admin-appearance',
		60
	);
} );


// ---------------------------------------------------------------------------
// Register settings
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
	register_setting( 'headless_theme_logo_group',      'headless_theme_logo_id',      [ 'sanitize_callback' => 'absint' ] );
	register_setting( 'headless_theme_logo_group',      'headless_theme_logo_dark_id', [ 'sanitize_callback' => 'absint' ] );
	register_setting( 'headless_theme_logo_group',      'headless_theme_logo_alt',     [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'headless_theme_logo_group',      'headless_theme_logo_width',   [ 'sanitize_callback' => 'absint', 'default' => 120 ] );
	register_setting( 'headless_theme_logo_group',      'headless_theme_favicon_id',   [ 'sanitize_callback' => 'absint' ] );
	register_setting( 'headless_theme_analytics_group', 'headless_theme_clarity_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'headless_theme_analytics_group', 'headless_theme_ga_id',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'headless_theme_article_group',   'headless_theme_related_source', [
		'sanitize_callback' => function ( $value ) {
			return in_array( $value, [ 'category', 'tags' ], true ) ? $value : 'category';
		},
		'default' => 'category',
	] );
	register_setting( 'headless_theme_banner_group', 'headless_theme_banner_visibility', [
		'sanitize_callback' => function ( $value ) {
			if ( ! is_array( $value ) ) {
				return []; // no checkboxes checked
			}
			$allowed = [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ];
			return array_values( array_intersect( $value, $allowed ) );
		},
		'default' => [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ],
	] );
} );


// ---------------------------------------------------------------------------
// Enqueue WP media uploader on this screen only
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	if ( $hook === 'toplevel_page_theme-settings' ) {
		wp_enqueue_media();
	}
} );


// ---------------------------------------------------------------------------
// Page render
// ---------------------------------------------------------------------------

function headless_theme_settings_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'logo';
	if ( ! in_array( $tab, [ 'logo', 'analytics', 'article', 'banner' ], true ) ) {
		$tab = 'logo';
	}

	$logo_id      = (int) get_option( 'headless_theme_logo_id',      0 );
	$logo_dark_id = (int) get_option( 'headless_theme_logo_dark_id', 0 );
	$logo_alt     = (string) get_option( 'headless_theme_logo_alt',     '' );
	$logo_width   = (int) get_option( 'headless_theme_logo_width',   120 );
	$favicon_id   = (int) get_option( 'headless_theme_favicon_id',   0 );
	$clarity_id      = (string) get_option( 'headless_theme_clarity_id', '' );
	$ga_id           = (string) get_option( 'headless_theme_ga_id',      '' );
	$related_source  = (string) get_option( 'headless_theme_related_source', 'category' );
	$banner_visibility = get_option( 'headless_theme_banner_visibility', [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ] );
	if ( ! is_array( $banner_visibility ) ) {
		$banner_visibility = [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ];
	}

	$logo_src      = $logo_id      ? wp_get_attachment_image_url( $logo_id,      'medium' )    : '';
	$logo_dark_src = $logo_dark_id ? wp_get_attachment_image_url( $logo_dark_id, 'medium' )    : '';
	$favicon_src   = $favicon_id   ? wp_get_attachment_image_url( $favicon_id,   'thumbnail' ) : '';

	$base = admin_url( 'admin.php?page=theme-settings' );
	?>
	<div class="wrap">

		<h1><?php esc_html_e( 'Theme Settings', 'headless' ); ?></h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:20px">
			<a href="<?php echo esc_url( $base . '&tab=logo' ); ?>"
			   class="nav-tab <?php echo $tab === 'logo' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Logo & Favicon', 'headless' ); ?>
			</a>
			<a href="<?php echo esc_url( $base . '&tab=analytics' ); ?>"
			   class="nav-tab <?php echo $tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Analytics', 'headless' ); ?>
			</a>
			<a href="<?php echo esc_url( $base . '&tab=article' ); ?>"
			   class="nav-tab <?php echo $tab === 'article' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Article', 'headless' ); ?>
			</a>
			<a href="<?php echo esc_url( $base . '&tab=banner' ); ?>"
			   class="nav-tab <?php echo $tab === 'banner' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Banner', 'headless' ); ?>
			</a>
		</nav>

		<?php if ( $tab === 'logo' ) : ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'headless_theme_logo_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo', 'headless' ); ?></th>
					<td>
						<?php headless_media_field( 'headless_theme_logo_id', $logo_id, $logo_src, 'Select Logo', 'max-width:200px;max-height:80px' ); ?>
						<p class="description"><?php esc_html_e( 'Used on light backgrounds (default theme).', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Logo (Dark Mode)', 'headless' ); ?></th>
					<td style="background:#1e1e1e;padding:12px;border-radius:4px">
						<?php headless_media_field( 'headless_theme_logo_dark_id', $logo_dark_id, $logo_dark_src, 'Select Dark Logo', 'max-width:200px;max-height:80px' ); ?>
						<p class="description" style="color:#aaa"><?php esc_html_e( 'Used on dark backgrounds. If empty, the default logo is used in both modes.', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_theme_logo_alt"><?php esc_html_e( 'Logo Alt Text', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text"
						       id="headless_theme_logo_alt"
						       name="headless_theme_logo_alt"
						       class="regular-text"
						       value="<?php echo esc_attr( $logo_alt ); ?>"
						       placeholder="<?php esc_attr_e( 'e.g. Company name logo', 'headless' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_theme_logo_width"><?php esc_html_e( 'Logo Width (px)', 'headless' ); ?></label>
					</th>
					<td>
						<input type="number"
						       id="headless_theme_logo_width"
						       name="headless_theme_logo_width"
						       class="small-text"
						       value="<?php echo esc_attr( $logo_width ); ?>"
						       min="40"
						       max="400"
						       step="1" />
						<p class="description"><?php esc_html_e( 'Controls the logo display width on the frontend. Height adjusts automatically. Default: 120.', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Favicon', 'headless' ); ?></th>
					<td>
						<?php headless_media_field( 'headless_theme_favicon_id', $favicon_id, $favicon_src, 'Select Favicon', 'max-width:64px;max-height:64px' ); ?>
						<p class="description"><?php esc_html_e( 'Recommended: 32×32 px (ICO or PNG).', 'headless' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'headless' ) ); ?>
		</form>

		<?php elseif ( $tab === 'analytics' ) : ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'headless_theme_analytics_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_theme_clarity_id"><?php esc_html_e( 'Microsoft Clarity Project ID', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text"
						       id="headless_theme_clarity_id"
						       name="headless_theme_clarity_id"
						       class="regular-text"
						       value="<?php echo esc_attr( $clarity_id ); ?>"
						       placeholder="abc123xyz0" />
						<p class="description"><?php esc_html_e( 'Found in Clarity → Settings → Overview.', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_theme_ga_id"><?php esc_html_e( 'Google Analytics 4 Measurement ID', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text"
						       id="headless_theme_ga_id"
						       name="headless_theme_ga_id"
						       class="regular-text"
						       value="<?php echo esc_attr( $ga_id ); ?>"
						       placeholder="G-XXXXXXXXXX" />
						<p class="description"><?php esc_html_e( 'Found in GA → Admin → Data Streams. Format: G-XXXXXXXXXX', 'headless' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'headless' ) ); ?>
		</form>

		<?php elseif ( $tab === 'article' ) : ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'headless_theme_article_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_theme_related_source"><?php esc_html_e( 'Related Posts Source', 'headless' ); ?></label>
					</th>
					<td>
						<select id="headless_theme_related_source"
						        name="headless_theme_related_source"
						        class="regular-text">
							<option value="category" <?php selected( $related_source, 'category' ); ?>>
								<?php esc_html_e( 'Category', 'headless' ); ?>
							</option>
							<option value="tags" <?php selected( $related_source, 'tags' ); ?>>
								<?php esc_html_e( 'Tags', 'headless' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose whether related posts at the bottom of articles are based on the same category or shared tags.', 'headless' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'headless' ) ); ?>
		</form>

		<?php elseif ( $tab === 'banner' ) : ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'headless_theme_banner_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Servisne Banner Visibility', 'headless' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Banner Visibility', 'headless' ); ?></span></legend>
							<?php
							$visibility_options = [
								'homepage'          => __( 'Homepage', 'headless' ),
								'categories'        => __( 'Category pages', 'headless' ),
								'single_posts'      => __( 'Single posts', 'headless' ),
								'single_pages'      => __( 'Single pages', 'headless' ),
								'servisne_listing'  => __( 'Servisne listing', 'headless' ),
								'obavijesti_listing' => __( 'Obavijesti listing', 'headless' ),
							];
							foreach ( $visibility_options as $key => $label ) :
							?>
								<label style="display:block;margin-bottom:6px">
									<input type="checkbox"
									       name="headless_theme_banner_visibility[]"
									       value="<?php echo esc_attr( $key ); ?>"
									       <?php checked( in_array( $key, $banner_visibility, true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description" style="margin-top:8px">
								<?php esc_html_e( 'Choose where the servisne info banner strip appears on the frontend.', 'headless' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save', 'headless' ) ); ?>
		</form>

		<?php endif; ?>

	</div>

	<script>
	(function () {
		document.querySelectorAll('.headless-media-select').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var fieldId = btn.dataset.field;
				var frame = wp.media({
					title:    btn.dataset.title,
					button:   { text: btn.dataset.use },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					document.getElementById(fieldId).value = att.id;
					var preview = document.getElementById(fieldId + '_preview');
					if (preview) { preview.src = att.url; preview.style.display = 'block'; }
					var removeBtn = document.getElementById(fieldId + '_remove');
					if (removeBtn) { removeBtn.style.display = ''; }
				});
				frame.open();
			});
		});

		document.querySelectorAll('.headless-media-remove').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var fieldId = btn.dataset.field;
				document.getElementById(fieldId).value = '';
				var preview = document.getElementById(fieldId + '_preview');
				if (preview) { preview.src = ''; preview.style.display = 'none'; }
				btn.style.display = 'none';
			});
		});
	}());
	</script>
	<?php
}


// ---------------------------------------------------------------------------
// Media field helper (used by page render above)
// ---------------------------------------------------------------------------

function headless_media_field( string $field_id, int $current_id, string $current_src, string $title, string $img_style ): void {
	?>
	<input type="hidden"
	       id="<?php echo esc_attr( $field_id ); ?>"
	       name="<?php echo esc_attr( $field_id ); ?>"
	       value="<?php echo esc_attr( $current_id ?: '' ); ?>" />
	<div style="margin-bottom:8px">
		<img id="<?php echo esc_attr( $field_id . '_preview' ); ?>"
		     src="<?php echo esc_url( $current_src ); ?>"
		     style="<?php echo esc_attr( $img_style ); ?>;display:<?php echo $current_src ? 'block' : 'none'; ?>;border:1px solid #ddd;padding:4px;background:#fff" />
	</div>
	<button type="button"
	        class="button headless-media-select"
	        data-field="<?php echo esc_attr( $field_id ); ?>"
	        data-title="<?php echo esc_attr( $title ); ?>"
	        data-use="<?php esc_attr_e( 'Use this image', 'headless' ); ?>">
		<?php echo esc_html( $title ); ?>
	</button>
	<button type="button"
	        id="<?php echo esc_attr( $field_id . '_remove' ); ?>"
	        class="button headless-media-remove"
	        data-field="<?php echo esc_attr( $field_id ); ?>"
	        style="margin-left:4px<?php echo ! $current_id ? ';display:none' : ''; ?>">
		<?php esc_html_e( 'Remove', 'headless' ); ?>
	</button>
	<?php
}


// ---------------------------------------------------------------------------
// GraphQL — ThemeSettings type + root query field (WPGraphQL required)
// ---------------------------------------------------------------------------

add_action( 'graphql_register_types', function () {

	register_graphql_object_type( 'ThemeSettingsImage', [
		'description' => __( 'An image field in Theme Settings.', 'headless' ),
		'fields'      => [
			'url'     => [ 'type' => 'String', 'description' => __( 'Full-size image URL.', 'headless' ) ],
			'altText' => [ 'type' => 'String', 'description' => __( 'Alt text stored on the attachment.', 'headless' ) ],
			'width'   => [ 'type' => 'Int',    'description' => __( 'Width in pixels.', 'headless' ) ],
			'height'  => [ 'type' => 'Int',    'description' => __( 'Height in pixels.', 'headless' ) ],
		],
	] );

	register_graphql_object_type( 'ThemeSettings', [
		'description' => __( 'Theme-wide settings: logo, favicon, and analytics IDs.', 'headless' ),
		'fields'      => [
			'logo'      => [ 'type' => 'ThemeSettingsImage', 'description' => __( 'Site logo (light mode).',               'headless' ) ],
			'logoDark'  => [ 'type' => 'ThemeSettingsImage', 'description' => __( 'Site logo (dark mode).',                'headless' ) ],
			'logoAlt'   => [ 'type' => 'String',             'description' => __( 'Logo alt text override.',               'headless' ) ],
			'logoWidth' => [ 'type' => 'Int',                'description' => __( 'Logo display width in pixels.',         'headless' ) ],
			'favicon'   => [ 'type' => 'ThemeSettingsImage', 'description' => __( 'Site favicon.',                         'headless' ) ],
			'clarityId' => [ 'type' => 'String',             'description' => __( 'Microsoft Clarity project ID.',         'headless' ) ],
			'gaId'           => [ 'type' => 'String',             'description' => __( 'Google Analytics 4 measurement ID.',    'headless' ) ],
			'relatedSource'     => [ 'type' => 'String',              'description' => __( 'Related posts source: "category" or "tags".', 'headless' ) ],
			'bannerVisibility'  => [ 'type' => [ 'list_of' => 'String' ], 'description' => __( 'Page types where the servisne banner is visible.', 'headless' ) ],
		],
	] );

	register_graphql_field( 'RootQuery', 'themeSettings', [
		'type'        => 'ThemeSettings',
		'description' => __( 'Theme-wide settings (logo, favicon, analytics IDs).', 'headless' ),
		'resolve'     => function (): array {
			return [
				'logo'      => headless_theme_resolve_image( (int) get_option( 'headless_theme_logo_id',      0 ) ),
				'logoDark'  => headless_theme_resolve_image( (int) get_option( 'headless_theme_logo_dark_id', 0 ) ),
				'logoAlt'   => (string) get_option( 'headless_theme_logo_alt',   '' ),
				'logoWidth' => (int) get_option( 'headless_theme_logo_width', 120 ),
				'favicon'   => headless_theme_resolve_image( (int) get_option( 'headless_theme_favicon_id', 0 ) ),
				'clarityId' => (string) get_option( 'headless_theme_clarity_id', '' ),
				'gaId'           => (string) get_option( 'headless_theme_ga_id',           '' ),
				'relatedSource'     => (string) get_option( 'headless_theme_related_source', 'category' ),
				'bannerVisibility'  => (array) get_option( 'headless_theme_banner_visibility', [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ] ),
			];
		},
	] );

} );
