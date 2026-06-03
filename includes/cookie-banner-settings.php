<?php
/**
 * Cookie Banner Settings
 *
 * Registers the Cookie Banner admin settings page and exposes
 * the settings via WPGraphQL as `cookieBannerSettings`.
 *
 * @package Headless
 */

// Prefix: headless_ (matches existing theme convention — see functions.php, settings.php, theme-settings.php)
defined( 'ABSPATH' ) || exit;

define( 'HEADLESS_COOKIE_BANNER_OPTION', 'headless_cookie_banner_settings' );
define( 'HEADLESS_COOKIE_BANNER_GROUP',  'headless_cookie_banner_group' );


// ---------------------------------------------------------------------------
// Defaults (Bosnian)
// ---------------------------------------------------------------------------

function headless_cookie_banner_defaults(): array {
	return [
		'heading'     => 'Koristimo kolačiće',
		'description' => 'Koristimo kolačiće kako bismo poboljšali vaše iskustvo na našoj stranici. '
		               . 'Možete odabrati koje kategorije kolačića prihvatate.',
		'acceptLabel' => 'Prihvati sve',
		'rejectLabel' => 'Odbij sve',
		'manageLabel' => 'Upravljaj postavkama',
		'saveLabel'   => 'Spremi postavke',
		'embedPlaceholder' => [
			'text'         => 'Ovaj sadržaj je blokiran zbog vaših postavki kolačića.',
			'acceptButton' => 'Prihvati marketing kolačiće i prikaži sadržaj',
			'manageLink'   => 'Upravljaj postavkama',
		],
		'categories'  => [
			[
				'key'         => 'necessary',
				'label'       => 'Neophodni',
				'description' => 'Neophodni kolačići su potrebni za osnovno funkcioniranje stranice. '
				               . 'Ne mogu se isključiti.',
			],
			[
				'key'         => 'analytics',
				'label'       => 'Analitika',
				'description' => 'Pomažu nam razumjeti kako posjetitelji koriste stranicu '
				               . '(Google Analytics, Microsoft Clarity).',
			],
			[
				'key'         => 'marketing',
				'label'       => 'Marketing',
				'description' => 'Koriste se za prikazivanje relevantnih oglasa '
				               . '(Google AdSense).',
			],
			[
				'key'         => 'preferences',
				'label'       => 'Preference',
				'description' => 'Pamte vaše postavke poput jezika i regiona.',
			],
		],
	];
}


// ---------------------------------------------------------------------------
// Sanitize
// ---------------------------------------------------------------------------

function headless_cookie_banner_sanitize( $input ): string {
	if ( ! is_string( $input ) || '' === $input ) {
		return '';
	}

	$data = json_decode( $input, true );

	if ( ! is_array( $data ) ) {
		add_settings_error(
			HEADLESS_COOKIE_BANNER_OPTION,
			'invalid_json',
			__( 'Cookie Banner: invalid JSON — settings were not saved.', 'headless' )
		);
		return (string) get_option( HEADLESS_COOKIE_BANNER_OPTION, '' );
	}

	$allowed_keys = [ 'heading', 'description', 'acceptLabel', 'rejectLabel', 'manageLabel', 'saveLabel', 'embedPlaceholder', 'categories' ];
	$clean        = array_intersect_key( $data, array_flip( $allowed_keys ) );

	// Sanitize scalar values.
	foreach ( [ 'heading', 'description', 'acceptLabel', 'rejectLabel', 'manageLabel', 'saveLabel' ] as $key ) {
		if ( isset( $clean[ $key ] ) ) {
			$clean[ $key ] = sanitize_text_field( $clean[ $key ] );
		}
	}

	// Sanitize embedPlaceholder.
	if ( isset( $clean['embedPlaceholder'] ) && is_array( $clean['embedPlaceholder'] ) ) {
		$clean['embedPlaceholder'] = [
			'text'         => sanitize_text_field( $clean['embedPlaceholder']['text'] ?? '' ),
			'acceptButton' => sanitize_text_field( $clean['embedPlaceholder']['acceptButton'] ?? '' ),
			'manageLink'   => sanitize_text_field( $clean['embedPlaceholder']['manageLink'] ?? '' ),
		];
	}

	// Sanitize categories.
	if ( isset( $clean['categories'] ) && is_array( $clean['categories'] ) ) {
		$allowed_cat_keys = [ 'necessary', 'analytics', 'marketing', 'preferences' ];
		$sanitized_cats   = [];
		foreach ( $clean['categories'] as $cat ) {
			if ( is_array( $cat ) && isset( $cat['key'] ) && in_array( $cat['key'], $allowed_cat_keys, true ) ) {
				$sanitized_cats[] = [
					'key'         => sanitize_text_field( $cat['key'] ),
					'label'       => sanitize_text_field( $cat['label'] ?? '' ),
					'description' => sanitize_text_field( $cat['description'] ?? '' ),
				];
			}
		}
		$clean['categories'] = $sanitized_cats;
	}

	return (string) wp_json_encode( $clean, JSON_UNESCAPED_UNICODE );
}


// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Cookie Banner', 'headless' ),
		__( 'Cookie Banner', 'headless' ),
		'manage_options',
		'headless-cookie-banner',
		'headless_cookie_banner_render_page'
	);
} );


// ---------------------------------------------------------------------------
// Register setting
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
	register_setting(
		HEADLESS_COOKIE_BANNER_GROUP,
		HEADLESS_COOKIE_BANNER_OPTION,
		[
			'type'              => 'string',
			'sanitize_callback' => 'headless_cookie_banner_sanitize',
			'default'           => '',
		]
	);
} );


// ---------------------------------------------------------------------------
// Settings page render
// ---------------------------------------------------------------------------

function headless_cookie_banner_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$stored   = get_option( HEADLESS_COOKIE_BANNER_OPTION, '' );
	$data     = $stored ? json_decode( $stored, true ) : [];
	$defaults = headless_cookie_banner_defaults();
	$values   = is_array( $data ) ? array_merge( $defaults, array_filter( $data ) ) : $defaults;

	// Merge categories by key so partially stored data keeps defaults for missing keys.
	$default_cats = array_column( $defaults['categories'], null, 'key' );
	$stored_cats  = isset( $values['categories'] ) && is_array( $values['categories'] )
		? array_column( $values['categories'], null, 'key' )
		: [];
	$merged_cats  = [];
	foreach ( [ 'necessary', 'analytics', 'marketing', 'preferences' ] as $cat_key ) {
		$merged_cats[ $cat_key ] = array_merge(
			$default_cats[ $cat_key ] ?? [ 'key' => $cat_key, 'label' => '', 'description' => '' ],
			$stored_cats[ $cat_key ] ?? []
		);
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cookie Banner Settings', 'headless' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Manage the cookie consent banner copy displayed on the frontend. All text defaults to Bosnian.', 'headless' ); ?>
		</p>

		<form method="post" action="options.php" id="headless-cookie-banner-form">
			<?php settings_fields( HEADLESS_COOKIE_BANNER_GROUP ); ?>

			<!-- Hidden field that holds the assembled JSON (populated by JS on submit) -->
			<input type="hidden"
			       id="headless_cookie_banner_json"
			       name="<?php echo esc_attr( HEADLESS_COOKIE_BANNER_OPTION ); ?>"
			       value="<?php echo esc_attr( $stored ); ?>" />

			<h2><?php esc_html_e( 'Banner Copy', 'headless' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cb_heading"><?php esc_html_e( 'Heading', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_heading" class="regular-text"
						       value="<?php echo esc_attr( $values['heading'] ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['heading'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_description"><?php esc_html_e( 'Description', 'headless' ); ?></label>
					</th>
					<td>
						<textarea id="cb_description" class="large-text" rows="3"
						          placeholder="<?php echo esc_attr( $defaults['description'] ); ?>"><?php echo esc_textarea( $values['description'] ); ?></textarea>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Button Labels', 'headless' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cb_acceptLabel"><?php esc_html_e( 'Accept All', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_acceptLabel" class="regular-text"
						       value="<?php echo esc_attr( $values['acceptLabel'] ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['acceptLabel'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_rejectLabel"><?php esc_html_e( 'Reject All', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_rejectLabel" class="regular-text"
						       value="<?php echo esc_attr( $values['rejectLabel'] ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['rejectLabel'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_manageLabel"><?php esc_html_e( 'Manage Settings', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_manageLabel" class="regular-text"
						       value="<?php echo esc_attr( $values['manageLabel'] ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['manageLabel'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_saveLabel"><?php esc_html_e( 'Save Settings', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_saveLabel" class="regular-text"
						       value="<?php echo esc_attr( $values['saveLabel'] ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['saveLabel'] ); ?>" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Cookie Categories', 'headless' ); ?></h2>

			<?php foreach ( [ 'necessary', 'analytics', 'marketing', 'preferences' ] as $cat_key ) :
				$cat = $merged_cats[ $cat_key ];
			?>
			<fieldset style="border:1px solid #c3c4c7;padding:12px 16px;margin-bottom:16px;border-radius:4px;max-width:700px">
				<legend style="font-weight:600;padding:0 6px">
					<?php echo esc_html( ucfirst( $cat_key ) ); ?>
					<?php if ( $cat_key === 'necessary' ) : ?>
						<span style="color:#00a32a;font-size:12px;margin-left:4px"><?php esc_html_e( '(always on)', 'headless' ); ?></span>
					<?php endif; ?>
				</legend>
				<table class="form-table" role="presentation" style="margin-top:0">
					<tr>
						<th scope="row">
							<label for="cb_cat_<?php echo esc_attr( $cat_key ); ?>_label"><?php esc_html_e( 'Label', 'headless' ); ?></label>
						</th>
						<td>
							<input type="text"
							       id="cb_cat_<?php echo esc_attr( $cat_key ); ?>_label"
							       class="regular-text"
							       value="<?php echo esc_attr( $cat['label'] ); ?>"
							       placeholder="<?php echo esc_attr( $default_cats[ $cat_key ]['label'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cb_cat_<?php echo esc_attr( $cat_key ); ?>_description"><?php esc_html_e( 'Description', 'headless' ); ?></label>
						</th>
						<td>
							<textarea id="cb_cat_<?php echo esc_attr( $cat_key ); ?>_description"
							          class="large-text" rows="2"
							          placeholder="<?php echo esc_attr( $default_cats[ $cat_key ]['description'] ); ?>"><?php echo esc_textarea( $cat['description'] ); ?></textarea>
						</td>
					</tr>
				</table>
			</fieldset>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Embed Placeholders', 'headless' ); ?></h2>
			<p class="description" style="margin-bottom:12px">
				<?php esc_html_e( 'Prikazuje se umjesto YouTube, Facebook i SoundCloud embeda dok korisnik ne prihvati marketing kolačiće.', 'headless' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cb_ep_text"><?php esc_html_e( 'Tekst blokiranja', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_ep_text" class="large-text"
						       value="<?php echo esc_attr( $values['embedPlaceholder']['text'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['embedPlaceholder']['text'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_ep_acceptButton"><?php esc_html_e( 'Gumb "Prihvati"', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_ep_acceptButton" class="regular-text"
						       value="<?php echo esc_attr( $values['embedPlaceholder']['acceptButton'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['embedPlaceholder']['acceptButton'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cb_ep_manageLink"><?php esc_html_e( 'Link "Upravljaj"', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="cb_ep_manageLink" class="regular-text"
						       value="<?php echo esc_attr( $values['embedPlaceholder']['manageLink'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $defaults['embedPlaceholder']['manageLink'] ); ?>" />
					</td>
				</tr>
			</table>

			<p class="submit" style="display:flex;gap:8px;align-items:center">
				<?php submit_button( __( 'Save Settings', 'headless' ), 'primary', 'submit', false ); ?>
				<button type="button" class="button" id="headless-cb-reset">
					<?php esc_html_e( 'Reset to Defaults', 'headless' ); ?>
				</button>
			</p>
		</form>

		<!-- Read-only reference: cookies & services per category.
		     Not editable — the mapping is hardcoded in Next.js consent logic.
		     Update this table when adding/removing a third-party service. -->
		<div style="margin-top:30px;max-width:900px">
			<h2><?php esc_html_e( 'Kolačići i servisi po kategoriji', 'headless' ); ?></h2>
			<p class="description" style="margin-bottom:12px">
				<?php esc_html_e( 'Referentna tabela — prikazuje koji servisi i kolačići pripadaju svakoj kategoriji. Ova tabela je informativna i ne može se uređivati ovdje. Mapiranje je definirano u kodu (Next.js).', 'headless' ); ?>
			</p>
			<table class="widefat striped" style="border-collapse:collapse">
				<thead>
					<tr>
						<th style="padding:10px 12px;font-weight:600"><?php esc_html_e( 'Kategorija', 'headless' ); ?></th>
						<th style="padding:10px 12px;font-weight:600"><?php esc_html_e( 'Servis', 'headless' ); ?></th>
						<th style="padding:10px 12px;font-weight:600"><?php esc_html_e( 'Način blokiranja', 'headless' ); ?></th>
						<th style="padding:10px 12px;font-weight:600"><?php esc_html_e( 'Kolačići', 'headless' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td style="padding:8px 12px"><strong>Neophodni</strong></td>
						<td style="padding:8px 12px">Cookie Consent</td>
						<td style="padding:8px 12px">Uvijek aktivan</td>
						<td style="padding:8px 12px"><code>cookie_consent</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px" rowspan="2"><strong>Analitika</strong></td>
						<td style="padding:8px 12px">Google Analytics 4</td>
						<td style="padding:8px 12px">GTM Consent Mode</td>
						<td style="padding:8px 12px"><code>_ga</code>, <code>_ga_*</code>, <code>_gid</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px">Microsoft Clarity</td>
						<td style="padding:8px 12px">ClarityLoader.tsx</td>
						<td style="padding:8px 12px"><code>_clck</code>, <code>_clsk</code>, <code>MUID</code>, <code>ANONCHK</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px" rowspan="4"><strong>Marketing</strong></td>
						<td style="padding:8px 12px">Google AdSense</td>
						<td style="padding:8px 12px">GTM Consent Mode</td>
						<td style="padding:8px 12px"><code>IDE</code>, <code>DSID</code>, <code>test_cookie</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px">YouTube embedi</td>
						<td style="padding:8px 12px">ConsentGatedEmbed</td>
						<td style="padding:8px 12px"><code>VISITOR_INFO1_LIVE</code>, <code>YSC</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px">Facebook embedi</td>
						<td style="padding:8px 12px">ConsentGatedEmbed</td>
						<td style="padding:8px 12px"><code>fr</code>, <code>datr</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px">SoundCloud embedi</td>
						<td style="padding:8px 12px">ConsentGatedEmbed</td>
						<td style="padding:8px 12px"><code>sc_at</code></td>
					</tr>
					<tr>
						<td style="padding:8px 12px"><strong>Preference</strong></td>
						<td style="padding:8px 12px">Tema (dark/light)</td>
						<td style="padding:8px 12px">localStorage</td>
						<td style="padding:8px 12px"><em>localStorage</em> (<code>theme</code>)</td>
					</tr>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px">
				<?php esc_html_e( 'Kada dodate ili uklonite servis, ažurirajte ovu tabelu u includes/cookie-banner-settings.php i odgovarajući kod u Next.js.', 'headless' ); ?>
			</p>
		</div>
	</div>

	<script>
	(function () {
		var form        = document.getElementById('headless-cookie-banner-form');
		var hiddenField = document.getElementById('headless_cookie_banner_json');
		var categories  = ['necessary', 'analytics', 'marketing', 'preferences'];

		// Assemble all UI fields into a JSON string on form submit.
		form.addEventListener('submit', function () {
			var data = {
				heading:     document.getElementById('cb_heading').value,
				description: document.getElementById('cb_description').value,
				acceptLabel: document.getElementById('cb_acceptLabel').value,
				rejectLabel: document.getElementById('cb_rejectLabel').value,
				manageLabel: document.getElementById('cb_manageLabel').value,
				saveLabel:   document.getElementById('cb_saveLabel').value,
				embedPlaceholder: {
					text:         document.getElementById('cb_ep_text').value,
					acceptButton: document.getElementById('cb_ep_acceptButton').value,
					manageLink:   document.getElementById('cb_ep_manageLink').value
				},
				categories:  categories.map(function (key) {
					return {
						key:         key,
						label:       document.getElementById('cb_cat_' + key + '_label').value,
						description: document.getElementById('cb_cat_' + key + '_description').value
					};
				})
			};
			hiddenField.value = JSON.stringify(data);
		});

		// Reset to defaults (client-side only — no server round-trip).
		var defaults = <?php echo wp_json_encode( headless_cookie_banner_defaults(), JSON_UNESCAPED_UNICODE ); ?>;

		document.getElementById('headless-cb-reset').addEventListener('click', function () {
			document.getElementById('cb_heading').value     = defaults.heading;
			document.getElementById('cb_description').value = defaults.description;
			document.getElementById('cb_acceptLabel').value = defaults.acceptLabel;
			document.getElementById('cb_rejectLabel').value = defaults.rejectLabel;
			document.getElementById('cb_manageLabel').value = defaults.manageLabel;
			document.getElementById('cb_saveLabel').value   = defaults.saveLabel;
			document.getElementById('cb_ep_text').value         = defaults.embedPlaceholder.text;
			document.getElementById('cb_ep_acceptButton').value = defaults.embedPlaceholder.acceptButton;
			document.getElementById('cb_ep_manageLink').value   = defaults.embedPlaceholder.manageLink;
			defaults.categories.forEach(function (cat) {
				document.getElementById('cb_cat_' + cat.key + '_label').value       = cat.label;
				document.getElementById('cb_cat_' + cat.key + '_description').value = cat.description;
			});
		});
	}());
	</script>
	<?php
}


// ---------------------------------------------------------------------------
// WPGraphQL — CookieBannerSettings type + root query field
// ---------------------------------------------------------------------------
// WPGraphQL for ACF is NOT required — this uses a native resolver.

add_action( 'graphql_register_types', function () {

	register_graphql_object_type( 'CookieCategoryItem', [
		'description' => __( 'A single cookie category (label + description).', 'headless' ),
		'fields'      => [
			'key'         => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Category key.', 'headless' ) ],
			'label'       => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Category label.', 'headless' ) ],
			'description' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Category description.', 'headless' ) ],
		],
	] );

	register_graphql_object_type( 'EmbedPlaceholderSettings', [
		'description' => __( 'Embed placeholder copy shown when marketing consent is not granted.', 'headless' ),
		'fields'      => [
			'text'         => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Blocked content message.', 'headless' ) ],
			'acceptButton' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Accept marketing cookies button label.', 'headless' ) ],
			'manageLink'   => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Manage settings link label.', 'headless' ) ],
		],
	] );

	register_graphql_object_type( 'CookieBannerSettings', [
		'description' => __( 'Cookie banner copy managed under Settings > Cookie Banner.', 'headless' ),
		'fields'      => [
			'heading'     => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Banner heading.', 'headless' ) ],
			'description' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Banner description text.', 'headless' ) ],
			'acceptLabel' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Accept all button label.', 'headless' ) ],
			'rejectLabel' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Reject all button label.', 'headless' ) ],
			'manageLabel' => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Manage settings button label.', 'headless' ) ],
			'saveLabel'   => [ 'type' => [ 'non_null' => 'String' ], 'description' => __( 'Save button label.', 'headless' ) ],
			'embedPlaceholder' => [
				'type'        => [ 'non_null' => 'EmbedPlaceholderSettings' ],
				'description' => __( 'Embed placeholder copy for consent-gated embeds.', 'headless' ),
			],
			'categories'  => [
				'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'CookieCategoryItem' ] ] ],
				'description' => __( 'Cookie categories.', 'headless' ),
			],
		],
	] );

	register_graphql_field( 'RootQuery', 'cookieBannerSettings', [
		'type'        => 'CookieBannerSettings',
		'description' => __( 'Cookie banner copy managed under Settings > Cookie Banner.', 'headless' ),
		'resolve'     => function () {
			$stored   = get_option( HEADLESS_COOKIE_BANNER_OPTION, '' );
			$data     = $stored ? json_decode( $stored, true ) : [];
			$defaults = headless_cookie_banner_defaults();
			// Merge stored over defaults field-by-field so missing keys never return null.
			return array_merge( $defaults, is_array( $data ) ? array_filter( $data ) : [] );
		},
	] );

} );
