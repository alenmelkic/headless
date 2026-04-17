<?php
/**
 * Marketing — Admin UI + REST API
 *
 * Top-level "Marketing" admin page with two tabs:
 *
 *   1. Glavni banner — hero banner below the header
 *      - Desktop / Tablet / Mobile images (attachment IDs)
 *      - Shared alt text & link
 *      - Disable date/time (banner auto-hides after this)
 *      - Page visibility checkboxes
 *
 *   2. Mali banneri — repeater of small banners (JSON array in wp_options)
 *      Each entry: image ID, alt text, link
 *
 * REST endpoint:
 *   GET /wp-json/headless/v1/marketing
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Marketing', 'headless' ),
		__( 'Marketing', 'headless' ),
		'manage_options',
		'headless-marketing',
		'headless_marketing_render',
		'dashicons-megaphone',
		58
	);
} );


// ---------------------------------------------------------------------------
// Register settings
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
	// Glavni banner
	$int_fields = [
		'headless_mkt_banner_desktop_id',
		'headless_mkt_banner_tablet_id',
		'headless_mkt_banner_mobile_id',
	];
	foreach ( $int_fields as $field ) {
		register_setting( 'headless_mkt_banner_group', $field, [ 'sanitize_callback' => 'absint' ] );
	}

	register_setting( 'headless_mkt_banner_group', 'headless_mkt_banner_alt', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'headless_mkt_banner_group', 'headless_mkt_banner_link', [
		'sanitize_callback' => 'esc_url_raw',
	] );
	register_setting( 'headless_mkt_banner_group', 'headless_mkt_banner_disable_at', [
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'headless_mkt_banner_group', 'headless_mkt_banner_visibility', [
		'sanitize_callback' => function ( $value ) {
			if ( ! is_array( $value ) ) {
				return [];
			}
			$allowed = [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ];
			return array_values( array_intersect( $value, $allowed ) );
		},
		'default' => [ 'homepage' ],
	] );

	// Mali banneri — stored as JSON
	register_setting( 'headless_mkt_small_group', 'headless_mkt_small_banners', [
		'sanitize_callback' => 'headless_mkt_sanitize_small_banners',
	] );
} );

/**
 * Sanitize the mali banneri repeater data.
 */
function headless_mkt_sanitize_small_banners( $value ): string {
	if ( ! is_array( $value ) ) {
		return '[]';
	}

	$clean = [];
	foreach ( $value as $item ) {
		if ( empty( $item['image_id'] ) ) {
			continue;
		}
		$clean[] = [
			'image_id' => absint( $item['image_id'] ?? 0 ),
			'alt'      => sanitize_text_field( $item['alt'] ?? '' ),
			'link'     => esc_url_raw( $item['link'] ?? '' ),
		];
	}

	return wp_json_encode( $clean );
}


// ---------------------------------------------------------------------------
// Enqueue WP media uploader on this screen only
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	if ( $hook === 'toplevel_page_headless-marketing' ) {
		wp_enqueue_media();
	}
} );


// ---------------------------------------------------------------------------
// Page render
// ---------------------------------------------------------------------------

function headless_marketing_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'glavni';
	if ( ! in_array( $tab, [ 'glavni', 'mali' ], true ) ) {
		$tab = 'glavni';
	}

	$base = admin_url( 'admin.php?page=headless-marketing' );
	?>
	<div class="wrap">

		<h1><?php esc_html_e( 'Marketing', 'headless' ); ?></h1>

		<nav class="nav-tab-wrapper" style="margin-bottom:20px">
			<a href="<?php echo esc_url( $base . '&tab=glavni' ); ?>"
			   class="nav-tab <?php echo $tab === 'glavni' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Glavni banner', 'headless' ); ?>
			</a>
			<a href="<?php echo esc_url( $base . '&tab=mali' ); ?>"
			   class="nav-tab <?php echo $tab === 'mali' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Mali banneri', 'headless' ); ?>
			</a>
		</nav>

		<?php
		if ( $tab === 'glavni' ) {
			headless_mkt_render_glavni();
		} else {
			headless_mkt_render_mali();
		}
		?>

	</div>

	<?php headless_mkt_media_script(); ?>
	<?php
}


// ---------------------------------------------------------------------------
// Tab: Glavni banner
// ---------------------------------------------------------------------------

function headless_mkt_render_glavni(): void {
	$desktop_id = (int) get_option( 'headless_mkt_banner_desktop_id', 0 );
	$tablet_id  = (int) get_option( 'headless_mkt_banner_tablet_id',  0 );
	$mobile_id  = (int) get_option( 'headless_mkt_banner_mobile_id',  0 );
	$alt        = (string) get_option( 'headless_mkt_banner_alt',        '' );
	$link       = (string) get_option( 'headless_mkt_banner_link',       '' );
	$disable_at = (string) get_option( 'headless_mkt_banner_disable_at', '' );
	$visibility = get_option( 'headless_mkt_banner_visibility', [ 'homepage' ] );
	if ( ! is_array( $visibility ) ) {
		$visibility = [ 'homepage' ];
	}

	$desktop_src = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'medium_large' ) : '';
	$tablet_src  = $tablet_id  ? wp_get_attachment_image_url( $tablet_id,  'medium_large' ) : '';
	$mobile_src  = $mobile_id  ? wp_get_attachment_image_url( $mobile_id,  'medium' )       : '';

	// Check if banner is currently expired
	$is_expired = false;
	if ( $disable_at ) {
		$disable_time = strtotime( $disable_at );
		if ( $disable_time && $disable_time < time() ) {
			$is_expired = true;
		}
	}
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'headless_mkt_banner_group' ); ?>

		<?php if ( $is_expired ) : ?>
		<div class="notice notice-warning inline" style="margin:0 0 16px">
			<p><strong><?php esc_html_e( 'Ovaj banner je istekao i više nije vidljiv na web stranici.', 'headless' ); ?></strong></p>
		</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Slike bannera', 'headless' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Postavite zasebne slike optimizirane za svaku veličinu ekrana. Frontend će prikazati odgovarajuću sliku prema uređaju posjetitelja.', 'headless' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Desktop banner', 'headless' ); ?></th>
				<td>
					<?php headless_media_field( 'headless_mkt_banner_desktop_id', $desktop_id, $desktop_src, 'Odaberi desktop banner', 'max-width:400px;max-height:120px' ); ?>
					<p class="description"><?php esc_html_e( 'Preporučeno: 1280×120 px.', 'headless' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tablet banner', 'headless' ); ?></th>
				<td>
					<?php headless_media_field( 'headless_mkt_banner_tablet_id', $tablet_id, $tablet_src, 'Odaberi tablet banner', 'max-width:300px;max-height:120px' ); ?>
					<p class="description"><?php esc_html_e( 'Preporučeno: 1024×120 px.', 'headless' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mobitel banner', 'headless' ); ?></th>
				<td>
					<?php headless_media_field( 'headless_mkt_banner_mobile_id', $mobile_id, $mobile_src, 'Odaberi mobitel banner', 'max-width:200px;max-height:120px' ); ?>
					<p class="description"><?php esc_html_e( 'Preporučeno: 480×200 px.', 'headless' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Postavke bannera', 'headless' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="headless_mkt_banner_alt"><?php esc_html_e( 'Alt tekst', 'headless' ); ?></label>
				</th>
				<td>
					<input type="text"
					       id="headless_mkt_banner_alt"
					       name="headless_mkt_banner_alt"
					       class="regular-text"
					       value="<?php echo esc_attr( $alt ); ?>"
					       placeholder="<?php esc_attr_e( 'Opišite banner za pristupačnost', 'headless' ); ?>" />
					<p class="description"><?php esc_html_e( 'Zajednički alt tekst za sve tri veličine bannera.', 'headless' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="headless_mkt_banner_link"><?php esc_html_e( 'Link', 'headless' ); ?></label>
				</th>
				<td>
					<input type="url"
					       id="headless_mkt_banner_link"
					       name="headless_mkt_banner_link"
					       class="regular-text"
					       value="<?php echo esc_attr( $link ); ?>"
					       placeholder="https://" />
					<p class="description"><?php esc_html_e( 'Odredište na koje banner vodi kada se klikne. Zajedničko za sve veličine.', 'headless' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="headless_mkt_banner_disable_at"><?php esc_html_e( 'Deaktiviraj nakon', 'headless' ); ?></label>
				</th>
				<td>
					<input type="datetime-local"
					       id="headless_mkt_banner_disable_at"
					       name="headless_mkt_banner_disable_at"
					       class="regular-text"
					       value="<?php echo esc_attr( $disable_at ); ?>" />
					<p class="description"><?php esc_html_e( 'Banner će se automatski prestati prikazivati nakon ovog datuma i vremena. Ostavite prazno za neograničeno prikazivanje.', 'headless' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Vidljivost na stranicama', 'headless' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Vidljivost bannera', 'headless' ); ?></span></legend>
						<?php
						$visibility_options = [
							'homepage'           => __( 'Početna stranica', 'headless' ),
							'categories'         => __( 'Stranice kategorija', 'headless' ),
							'single_posts'       => __( 'Pojedinačni članci', 'headless' ),
							'single_pages'       => __( 'Pojedinačne stranice', 'headless' ),
							'servisne_listing'   => __( 'Lista servisnih informacija', 'headless' ),
							'obavijesti_listing' => __( 'Lista obavijesti o smrti', 'headless' ),
						];
						foreach ( $visibility_options as $key => $label ) :
						?>
							<label style="display:block;margin-bottom:6px">
								<input type="checkbox"
								       name="headless_mkt_banner_visibility[]"
								       value="<?php echo esc_attr( $key ); ?>"
								       <?php checked( in_array( $key, $visibility, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description" style="margin-top:8px">
							<?php esc_html_e( 'Odaberite na kojim stranicama se prikazuje ovaj marketing banner.', 'headless' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Spremi', 'headless' ) ); ?>
	</form>
	<?php
}


// ---------------------------------------------------------------------------
// Tab: Mali banneri
// ---------------------------------------------------------------------------

function headless_mkt_render_mali(): void {
	$raw     = get_option( 'headless_mkt_small_banners', '[]' );
	$banners = json_decode( is_string( $raw ) ? $raw : '[]', true );
	if ( ! is_array( $banners ) ) {
		$banners = [];
	}
	?>
	<form method="post" action="options.php" id="headless-mali-form">
		<?php settings_fields( 'headless_mkt_small_group' ); ?>

		<p class="description"><?php esc_html_e( 'Dodajte koliko god malih bannera trebate. Svaki zahtijeva sliku, alt tekst i link.', 'headless' ); ?></p>

		<div id="headless-mali-list">
			<?php
			foreach ( $banners as $i => $banner ) {
				headless_mkt_mali_row( $i, $banner );
			}
			?>
		</div>

		<p style="margin-top:16px">
			<button type="button" class="button" id="headless-mali-add">
				+ <?php esc_html_e( 'Dodaj banner', 'headless' ); ?>
			</button>
		</p>

		<?php submit_button( __( 'Spremi', 'headless' ) ); ?>
	</form>

	<!-- Row template (hidden, cloned by JS) -->
	<script type="text/html" id="tmpl-headless-mali-row">
		<?php headless_mkt_mali_row( '__INDEX__', [ 'image_id' => 0, 'alt' => '', 'link' => '' ] ); ?>
	</script>

	<script>
	(function () {
		var list  = document.getElementById('headless-mali-list');
		var addBtn = document.getElementById('headless-mali-add');
		var tmpl  = document.getElementById('tmpl-headless-mali-row').innerHTML;

		function getNextIndex() {
			var rows = list.querySelectorAll('.headless-mali-row');
			return rows.length;
		}

		addBtn.addEventListener('click', function () {
			var idx  = getNextIndex();
			var html = tmpl.replace(/__INDEX__/g, idx);
			var div  = document.createElement('div');
			div.innerHTML = html;
			var row = div.firstElementChild;
			list.appendChild(row);
			bindRow(row);
		});

		function bindRow(row) {
			// Select image
			row.querySelector('.headless-mali-select').addEventListener('click', function (e) {
				e.preventDefault();
				var btn = this;
				var frame = wp.media({
					title:    'Odaberi sliku bannera',
					button:   { text: 'Koristi ovu sliku' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					row.querySelector('.headless-mali-image-id').value = att.id;
					var preview = row.querySelector('.headless-mali-preview');
					preview.src = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
					preview.style.display = 'block';
					row.querySelector('.headless-mali-remove-img').style.display = '';
				});
				frame.open();
			});

			// Remove image
			row.querySelector('.headless-mali-remove-img').addEventListener('click', function (e) {
				e.preventDefault();
				row.querySelector('.headless-mali-image-id').value = '';
				row.querySelector('.headless-mali-preview').src = '';
				row.querySelector('.headless-mali-preview').style.display = 'none';
				this.style.display = 'none';
			});

			// Remove row
			row.querySelector('.headless-mali-remove-row').addEventListener('click', function (e) {
				e.preventDefault();
				if (confirm('<?php echo esc_js( __( 'Ukloniti ovaj banner?', 'headless' ) ); ?>')) {
					row.remove();
					reindex();
				}
			});
		}

		function reindex() {
			list.querySelectorAll('.headless-mali-row').forEach(function (row, idx) {
				row.querySelectorAll('[name]').forEach(function (input) {
					input.name = input.name.replace(/headless_mkt_small_banners\[\d+\]/, 'headless_mkt_small_banners[' + idx + ']');
				});
			});
		}

		// Bind existing rows
		list.querySelectorAll('.headless-mali-row').forEach(bindRow);
	}());
	</script>
	<?php
}

/**
 * Renders a single mali banner repeater row.
 */
function headless_mkt_mali_row( $index, array $banner ): void {
	$image_id = (int) ( $banner['image_id'] ?? 0 );
	$alt      = (string) ( $banner['alt'] ?? '' );
	$link     = (string) ( $banner['link'] ?? '' );
	$img_src  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	$name     = "headless_mkt_small_banners[{$index}]";
	?>
	<div class="headless-mali-row" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin-bottom:12px;display:flex;gap:20px;align-items:flex-start">
		<!-- Image -->
		<div style="flex:0 0 180px">
			<input type="hidden" class="headless-mali-image-id"
			       name="<?php echo esc_attr( $name ); ?>[image_id]"
			       value="<?php echo esc_attr( $image_id ?: '' ); ?>" />
			<img class="headless-mali-preview"
			     src="<?php echo esc_url( $img_src ); ?>"
			     style="max-width:160px;max-height:80px;display:<?php echo $img_src ? 'block' : 'none'; ?>;border:1px solid #ddd;padding:4px;background:#fff;margin-bottom:8px" />
			<button type="button" class="button button-small headless-mali-select">
				<?php esc_html_e( 'Odaberi sliku', 'headless' ); ?>
			</button>
			<button type="button" class="button button-small headless-mali-remove-img"
			        style="margin-left:4px<?php echo ! $image_id ? ';display:none' : ''; ?>">
				<?php esc_html_e( 'Ukloni', 'headless' ); ?>
			</button>
		</div>

		<!-- Fields -->
		<div style="flex:1">
			<p style="margin:0 0 8px">
				<label style="display:block;margin-bottom:4px;font-weight:600"><?php esc_html_e( 'Alt tekst', 'headless' ); ?></label>
				<input type="text"
				       name="<?php echo esc_attr( $name ); ?>[alt]"
				       class="regular-text"
				       value="<?php echo esc_attr( $alt ); ?>"
				       placeholder="<?php esc_attr_e( 'Opis bannera', 'headless' ); ?>" />
			</p>
			<p style="margin:0">
				<label style="display:block;margin-bottom:4px;font-weight:600"><?php esc_html_e( 'Link', 'headless' ); ?></label>
				<input type="url"
				       name="<?php echo esc_attr( $name ); ?>[link]"
				       class="regular-text"
				       value="<?php echo esc_attr( $link ); ?>"
				       placeholder="https://" />
			</p>
		</div>

		<!-- Remove row -->
		<div style="flex:0 0 auto;align-self:center">
			<button type="button" class="button button-link-delete headless-mali-remove-row">
				<?php esc_html_e( 'Obriši', 'headless' ); ?>
			</button>
		</div>
	</div>
	<?php
}


// ---------------------------------------------------------------------------
// Shared media picker script (reuses headless_media_field from theme-settings)
// ---------------------------------------------------------------------------

function headless_mkt_media_script(): void {
	?>
	<script>
	(function () {
		document.querySelectorAll('.headless-media-select').forEach(function (btn) {
			// Skip if already bound (theme-settings binds its own)
			if (btn.dataset.mktBound) return;
			btn.dataset.mktBound = '1';
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
			if (btn.dataset.mktBound) return;
			btn.dataset.mktBound = '1';
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
// REST API — GET /wp-json/headless/v1/marketing
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
	register_rest_route( 'headless/v1', '/marketing', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'headless_get_marketing',
		'permission_callback' => '__return_true',
	] );
} );

/**
 * Returns marketing banner data for the frontend.
 */
function headless_get_marketing(): WP_REST_Response {
	// Glavni banner
	$disable_at  = (string) get_option( 'headless_mkt_banner_disable_at', '' );
	$is_active   = true;
	if ( $disable_at ) {
		$disable_time = strtotime( $disable_at );
		if ( $disable_time && $disable_time < time() ) {
			$is_active = false;
		}
	}

	$glavni = [
		'active'     => $is_active,
		'desktop'    => headless_theme_resolve_image( (int) get_option( 'headless_mkt_banner_desktop_id', 0 ) ),
		'tablet'     => headless_theme_resolve_image( (int) get_option( 'headless_mkt_banner_tablet_id',  0 ) ),
		'mobile'     => headless_theme_resolve_image( (int) get_option( 'headless_mkt_banner_mobile_id',  0 ) ),
		'alt'        => (string) get_option( 'headless_mkt_banner_alt',  '' ),
		'link'       => (string) get_option( 'headless_mkt_banner_link', '' ),
		'disableAt'  => $disable_at,
		'visibility' => (array) get_option( 'headless_mkt_banner_visibility', [ 'homepage' ] ),
	];

	// Mali banneri
	$raw     = get_option( 'headless_mkt_small_banners', '[]' );
	$banners = json_decode( is_string( $raw ) ? $raw : '[]', true );
	if ( ! is_array( $banners ) ) {
		$banners = [];
	}

	$mali = [];
	foreach ( $banners as $b ) {
		$image = headless_theme_resolve_image( (int) ( $b['image_id'] ?? 0 ) );
		if ( ! $image ) {
			continue;
		}
		$mali[] = [
			'image' => $image,
			'alt'   => (string) ( $b['alt'] ?? '' ),
			'link'  => (string) ( $b['link'] ?? '' ),
		];
	}

	return rest_ensure_response( [
		'glavniBanner' => $glavni,
		'maliBanneri'  => $mali,
	] );
}
