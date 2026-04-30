<?php
/**
 * Headless Settings — Admin UI
 *
 * Provides a Settings > Headless page for configuring the Next.js frontend
 * connection. Supports separate Dev, Staging, and Production environments.
 *
 * Options stored:
 *  - headless_active_env                : 'dev' | 'staging' | 'prod'
 *  - headless_dev_frontend_url          : e.g. http://localhost:3000
 *  - headless_dev_revalidate_secret     : matches REVALIDATE_SECRET in .env.local
 *  - headless_staging_frontend_url      : e.g. https://staging.your-project.vercel.app
 *  - headless_staging_revalidate_secret : matches REVALIDATE_SECRET in staging env vars
 *  - headless_prod_frontend_url         : e.g. https://your-project.vercel.app
 *  - headless_prod_revalidate_secret    : matches REVALIDATE_SECRET in Vercel env vars
 *  - headless_preview_secret            : shared, matches PREVIEW_SECRET in .env
 *
 * wp-config.php constants still work as hard overrides:
 *  HEADLESS_FRONTEND_URL, HEADLESS_REVALIDATE_SECRET,
 *  HEADLESS_REVALIDATE_URL, HEADLESS_PREVIEW_SECRET
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Returns the currently active environment: 'dev' or 'prod'.
 */
function headless_active_env(): string {
	$env = (string) get_option( 'headless_active_env', 'dev' );
	return in_array( $env, [ 'dev', 'staging', 'prod' ], true ) ? $env : 'dev';
}

/**
 * Returns a headless setting value for the active environment.
 *
 * Priority:
 *  1. wp-config.php constant (hard override, takes precedence over UI)
 *  2. WP option for the active environment
 *  3. Auto-derived value (revalidate_url only)
 *
 * @param string $key  'frontend_url' | 'revalidate_secret' | 'revalidate_url' | 'preview_secret'
 * @return string
 */
function headless_get_setting( string $key ): string {
	$constant_map = [
		'frontend_url'      => 'HEADLESS_FRONTEND_URL',
		'revalidate_secret' => 'HEADLESS_REVALIDATE_SECRET',
		'revalidate_url'    => 'HEADLESS_REVALIDATE_URL',
		'preview_secret'    => 'HEADLESS_PREVIEW_SECRET',
	];

	// 1. Hard constant override (wp-config.php).
	if ( isset( $constant_map[ $key ] ) && defined( $constant_map[ $key ] ) && constant( $constant_map[ $key ] ) ) {
		return (string) constant( $constant_map[ $key ] );
	}

	// 2. Preview secret is shared across environments.
	if ( $key === 'preview_secret' ) {
		return (string) get_option( 'headless_preview_secret', '' );
	}

	// 3. Environment-specific option.
	$env   = headless_active_env();
	$value = (string) get_option( "headless_{$env}_{$key}", '' );

	// 4. Auto-derive revalidate_url from frontend_url if not explicitly set.
	if ( $key === 'revalidate_url' && ! $value ) {
		$frontend = headless_get_setting( 'frontend_url' );
		return $frontend ? trailingslashit( $frontend ) . 'api/revalidate' : '';
	}

	return $value;
}


// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Headless Settings', 'headless' ),
		__( 'Headless', 'headless' ),
		'manage_options',
		'headless-settings',
		'headless_render_settings_page'
	);
} );


// ---------------------------------------------------------------------------
// Register settings
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
	$options = [
		'headless_active_env',
		'headless_dev_frontend_url',
		'headless_dev_revalidate_secret',
		'headless_staging_frontend_url',
		'headless_staging_revalidate_secret',
		'headless_prod_frontend_url',
		'headless_prod_revalidate_secret',
		'headless_preview_secret',
	];

	foreach ( $options as $option ) {
		register_setting( 'headless_settings_group', $option, [
			'sanitize_callback' => 'sanitize_text_field',
		] );
	}
} );


// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

function headless_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active_env = headless_active_env();

	// Which keys are hard-overridden by wp-config.php constants.
	$overrides = [
		'frontend_url'      => defined( 'HEADLESS_FRONTEND_URL' ) && HEADLESS_FRONTEND_URL,
		'revalidate_secret' => defined( 'HEADLESS_REVALIDATE_SECRET' ) && HEADLESS_REVALIDATE_SECRET,
		'preview_secret'    => defined( 'HEADLESS_PREVIEW_SECRET' ) && HEADLESS_PREVIEW_SECRET,
	];

	$has_overrides = (bool) array_filter( $overrides );

	// Current resolved values for status panel.
	$status_frontend  = headless_get_setting( 'frontend_url' );
	$status_revalidate_url = headless_get_setting( 'revalidate_url' );
	$status_secret    = headless_get_setting( 'revalidate_secret' );
	$status_preview   = headless_get_setting( 'preview_secret' );
	?>
	<div class="wrap">

		<h1 style="display:flex;align-items:center;gap:10px">
			<?php esc_html_e( 'Headless Settings', 'headless' ); ?>
			<?php headless_env_badge( $active_env ); ?>
		</h1>

		<p class="description">
			<?php esc_html_e( 'Configure the connection between WordPress and the Next.js frontend. Select an active environment and fill in the corresponding credentials.', 'headless' ); ?>
		</p>

		<?php if ( $has_overrides ) : ?>
		<div class="notice notice-info inline" style="margin:16px 0 0">
			<p>
				<?php esc_html_e( 'One or more settings are overridden by constants in wp-config.php and cannot be changed here.', 'headless' ); ?>
				<code>HEADLESS_FRONTEND_URL</code>, <code>HEADLESS_REVALIDATE_SECRET</code>, <code>HEADLESS_PREVIEW_SECRET</code>
			</p>
		</div>
		<?php endif; ?>

		<!-- Status panel -->
		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin:20px 0;max-width:700px">
			<h3 style="margin:0 0 12px"><?php esc_html_e( 'Current Status', 'headless' ); ?></h3>
			<table style="width:100%;border-collapse:collapse">
				<tr>
					<td style="padding:4px 12px 4px 0;width:160px;color:#646970"><?php esc_html_e( 'Active environment', 'headless' ); ?></td>
					<td><?php headless_env_badge( $active_env ); ?></td>
				</tr>
				<tr>
					<td style="padding:4px 12px 4px 0;color:#646970"><?php esc_html_e( 'Frontend URL', 'headless' ); ?></td>
					<td>
						<?php if ( $status_frontend ) : ?>
							<code><?php echo esc_html( $status_frontend ); ?></code>
						<?php else : ?>
							<?php headless_status_badge( false, __( 'Not configured', 'headless' ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 12px 4px 0;color:#646970"><?php esc_html_e( 'Webhook target', 'headless' ); ?></td>
					<td>
						<?php if ( $status_revalidate_url ) : ?>
							<code><?php echo esc_html( $status_revalidate_url ); ?></code>
						<?php else : ?>
							<?php headless_status_badge( false, __( 'Revalidation disabled — set Frontend URL first', 'headless' ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 12px 4px 0;color:#646970"><?php esc_html_e( 'Revalidate secret', 'headless' ); ?></td>
					<td><?php headless_status_badge( (bool) $status_secret ); ?></td>
				</tr>
				<tr>
					<td style="padding:4px 12px 4px 0;color:#646970"><?php esc_html_e( 'Preview secret', 'headless' ); ?></td>
					<td><?php headless_status_badge( (bool) $status_preview, $status_preview ? '' : __( 'Not set — Preview button disabled', 'headless' ) ); ?></td>
				</tr>
			</table>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'headless_settings_group' ); ?>

			<!-- Active Environment -->
			<h2><?php esc_html_e( 'Active Environment', 'headless' ); ?></h2>
			<p><?php esc_html_e( 'All webhooks and preview links will target the selected environment.', 'headless' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Environment', 'headless' ); ?></th>
					<td>
						<fieldset style="display:flex;gap:24px">
							<label style="display:flex;align-items:center;gap:8px;cursor:pointer">
								<input type="radio" name="headless_active_env" value="dev" <?php checked( $active_env, 'dev' ); ?> />
								<?php headless_env_badge( 'dev' ); ?>
								<?php esc_html_e( 'Development', 'headless' ); ?>
							</label>
							<label style="display:flex;align-items:center;gap:8px;cursor:pointer">
								<input type="radio" name="headless_active_env" value="staging" <?php checked( $active_env, 'staging' ); ?> />
								<?php headless_env_badge( 'staging' ); ?>
								<?php esc_html_e( 'Staging', 'headless' ); ?>
							</label>
							<label style="display:flex;align-items:center;gap:8px;cursor:pointer">
								<input type="radio" name="headless_active_env" value="prod" <?php checked( $active_env, 'prod' ); ?> />
								<?php headless_env_badge( 'prod' ); ?>
								<?php esc_html_e( 'Production', 'headless' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<!-- Development -->
			<h2 style="display:flex;align-items:center;gap:8px">
				<?php headless_env_badge( 'dev' ); ?>
				<?php esc_html_e( 'Development', 'headless' ); ?>
			</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_dev_frontend_url"><?php esc_html_e( 'Frontend URL', 'headless' ); ?></label>
					</th>
					<td>
						<?php if ( $overrides['frontend_url'] ) : ?>
							<input type="text" class="regular-text" value="<?php echo esc_attr( HEADLESS_FRONTEND_URL ); ?>" disabled />
							<p class="description"><?php esc_html_e( 'Set by HEADLESS_FRONTEND_URL in wp-config.php', 'headless' ); ?></p>
						<?php else : ?>
							<input type="url" id="headless_dev_frontend_url" name="headless_dev_frontend_url"
								class="regular-text"
								value="<?php echo esc_attr( get_option( 'headless_dev_frontend_url', '' ) ); ?>"
								placeholder="http://localhost:3000" />
							<p class="description"><?php esc_html_e( 'Local Next.js dev server URL.', 'headless' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_dev_revalidate_secret"><?php esc_html_e( 'Revalidate Secret', 'headless' ); ?></label>
					</th>
					<td>
						<?php if ( $overrides['revalidate_secret'] ) : ?>
							<input type="text" class="regular-text" value="<?php echo esc_attr( str_repeat( '•', 24 ) ); ?>" disabled />
							<p class="description"><?php esc_html_e( 'Set by HEADLESS_REVALIDATE_SECRET in wp-config.php', 'headless' ); ?></p>
						<?php else : ?>
							<input type="text" id="headless_dev_revalidate_secret" name="headless_dev_revalidate_secret"
								class="regular-text"
								value="<?php echo esc_attr( get_option( 'headless_dev_revalidate_secret', '' ) ); ?>"
								placeholder="<?php esc_attr_e( 'Paste REVALIDATE_SECRET from .env.local', 'headless' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Must match', 'headless' ); ?>
								<code>REVALIDATE_SECRET</code>
								<?php esc_html_e( 'in your Next.js', 'headless' ); ?>
								<code>.env.local</code>.
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<!-- Staging -->
			<h2 style="display:flex;align-items:center;gap:8px">
				<?php headless_env_badge( 'staging' ); ?>
				<?php esc_html_e( 'Staging', 'headless' ); ?>
			</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_staging_frontend_url"><?php esc_html_e( 'Frontend URL', 'headless' ); ?></label>
					</th>
					<td>
						<input type="url" id="headless_staging_frontend_url" name="headless_staging_frontend_url"
							class="regular-text"
							value="<?php echo esc_attr( get_option( 'headless_staging_frontend_url', '' ) ); ?>"
							placeholder="https://staging.your-project.vercel.app" />
						<p class="description"><?php esc_html_e( 'Your staging deployment URL.', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_staging_revalidate_secret"><?php esc_html_e( 'Revalidate Secret', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="headless_staging_revalidate_secret" name="headless_staging_revalidate_secret"
							class="regular-text"
							value="<?php echo esc_attr( get_option( 'headless_staging_revalidate_secret', '' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Paste REVALIDATE_SECRET from staging env vars', 'headless' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Must match', 'headless' ); ?>
							<code>REVALIDATE_SECRET</code>
							<?php esc_html_e( 'in your staging environment variables.', 'headless' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Production -->
			<h2 style="display:flex;align-items:center;gap:8px">
				<?php headless_env_badge( 'prod' ); ?>
				<?php esc_html_e( 'Production', 'headless' ); ?>
			</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_prod_frontend_url"><?php esc_html_e( 'Frontend URL', 'headless' ); ?></label>
					</th>
					<td>
						<input type="url" id="headless_prod_frontend_url" name="headless_prod_frontend_url"
							class="regular-text"
							value="<?php echo esc_attr( get_option( 'headless_prod_frontend_url', '' ) ); ?>"
							placeholder="https://your-project.vercel.app" />
						<p class="description"><?php esc_html_e( 'Your Vercel deployment URL.', 'headless' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="headless_prod_revalidate_secret"><?php esc_html_e( 'Revalidate Secret', 'headless' ); ?></label>
					</th>
					<td>
						<input type="text" id="headless_prod_revalidate_secret" name="headless_prod_revalidate_secret"
							class="regular-text"
							value="<?php echo esc_attr( get_option( 'headless_prod_revalidate_secret', '' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Paste REVALIDATE_SECRET from Vercel env vars', 'headless' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Must match', 'headless' ); ?>
							<code>REVALIDATE_SECRET</code>
							<?php esc_html_e( 'in your Vercel environment variables.', 'headless' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Shared -->
			<h2><?php esc_html_e( 'Shared', 'headless' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="headless_preview_secret"><?php esc_html_e( 'Preview Secret', 'headless' ); ?></label>
					</th>
					<td>
						<?php if ( $overrides['preview_secret'] ) : ?>
							<input type="text" class="regular-text" value="<?php echo esc_attr( str_repeat( '•', 24 ) ); ?>" disabled />
							<p class="description"><?php esc_html_e( 'Set by HEADLESS_PREVIEW_SECRET in wp-config.php', 'headless' ); ?></p>
						<?php else : ?>
							<input type="text" id="headless_preview_secret" name="headless_preview_secret"
								class="regular-text"
								value="<?php echo esc_attr( get_option( 'headless_preview_secret', '' ) ); ?>"
								placeholder="<?php esc_attr_e( 'Paste PREVIEW_SECRET from .env', 'headless' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Must match', 'headless' ); ?>
								<code>PREVIEW_SECRET</code>
								<?php esc_html_e( 'in your Next.js', 'headless' ); ?>
								<code>.env</code>.
								<?php esc_html_e( 'Used for both dev and prod.', 'headless' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'headless' ) ); ?>
		</form>
	</div>
	<?php
}


// ---------------------------------------------------------------------------
// Shared UI helpers
// ---------------------------------------------------------------------------

/** Renders a coloured DEV / STAGING / PROD badge. */
function headless_env_badge( string $env ): void {
	$badges = [
		'prod'    => [ '#00a32a', 'PROD' ],
		'staging' => [ '#dba617', 'STAGING' ],
		'dev'     => [ '#2271b1', 'DEV' ],
	];
	$badge = $badges[ $env ] ?? $badges['dev'];
	printf(
		'<span style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:700;letter-spacing:.5px">%s</span>',
		esc_attr( $badge[0] ),
		esc_html( $badge[1] )
	);
}

/** Renders a green ✓ Configured or red ⚠ warning badge. */
function headless_status_badge( bool $ok, string $error_label = '' ): void {
	if ( $ok ) {
		echo '<span style="color:#00a32a;font-weight:600">&#10003; ' . esc_html__( 'Configured', 'headless' ) . '</span>';
	} else {
		$label = $error_label ?: __( 'Not configured', 'headless' );
		echo '<span style="color:#d63638;font-weight:600">&#9888; ' . esc_html( $label ) . '</span>';
	}
}
