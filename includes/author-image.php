<?php
/**
 * Author Image — User Profile Field + WPGraphQL
 *
 * Adds a custom profile photo (media upload) to WordPress user profiles.
 * Stored as user meta `headless_author_image_id` (attachment ID).
 * Exposed to WPGraphQL as `authorImage` on the User type.
 *
 * Reuses:
 *   headless_media_field()        — from includes/theme-settings.php
 *   headless_theme_resolve_image() — from includes/theme-settings.php
 *   ThemeSettingsImage (GraphQL)  — registered in includes/theme-settings.php
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

// Meta key used throughout this file.
if ( ! defined( 'HEADLESS_AUTHOR_IMAGE_META' ) ) {
	define( 'HEADLESS_AUTHOR_IMAGE_META', 'headless_author_image_id' );
}


// ---------------------------------------------------------------------------
// Enqueue media uploader on user profile screens
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	if ( in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
		wp_enqueue_media();
	}
} );


// ---------------------------------------------------------------------------
// Render the author image field on user profile pages
// ---------------------------------------------------------------------------

/**
 * Outputs the Author Image media uploader field.
 *
 * @param WP_User $user The user being edited.
 */
function headless_author_image_field( WP_User $user ): void {
	$image_id  = (int) get_user_meta( $user->ID, HEADLESS_AUTHOR_IMAGE_META, true );
	$image_src = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	?>
	<h3><?php esc_html_e( 'Author Image', 'headless' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Profile Photo', 'headless' ); ?></th>
			<td>
				<?php headless_media_field(
					HEADLESS_AUTHOR_IMAGE_META,
					$image_id,
					$image_src,
					__( 'Select Author Image', 'headless' ),
					'max-width:150px;max-height:150px;border-radius:50%'
				); ?>
				<p class="description">
					<?php esc_html_e( 'Used on the frontend instead of Gravatar. Recommended: square image, at least 200×200 px.', 'headless' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<script>
	(function () {
		document.querySelectorAll('.headless-media-select').forEach(function (btn) {
			if (btn.dataset.bound) return;
			btn.dataset.bound = '1';
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
			if (btn.dataset.bound) return;
			btn.dataset.bound = '1';
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

add_action( 'show_user_profile', 'headless_author_image_field' );
add_action( 'edit_user_profile', 'headless_author_image_field' );


// ---------------------------------------------------------------------------
// Save the author image field
// ---------------------------------------------------------------------------

/**
 * Saves the author image attachment ID to user meta.
 *
 * @param int $user_id The ID of the user being saved.
 */
function headless_author_image_save( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST[ HEADLESS_AUTHOR_IMAGE_META ] ) ) {
		return;
	}

	$image_id = absint( $_POST[ HEADLESS_AUTHOR_IMAGE_META ] );

	if ( $image_id ) {
		update_user_meta( $user_id, HEADLESS_AUTHOR_IMAGE_META, $image_id );
	} else {
		delete_user_meta( $user_id, HEADLESS_AUTHOR_IMAGE_META );
	}
}

add_action( 'personal_options_update', 'headless_author_image_save' );
add_action( 'edit_user_profile_update', 'headless_author_image_save' );


// ---------------------------------------------------------------------------
// WPGraphQL — Register authorImage field on User type
// ---------------------------------------------------------------------------

add_action( 'graphql_register_types', function () {
	register_graphql_field( 'User', 'authorImage', [
		'type'        => 'ThemeSettingsImage',
		'description' => __( 'Custom author profile image (replaces Gravatar).', 'headless' ),
		'resolve'     => function ( $source ): ?array {
			$user_id = $source->userId ?? 0;
			if ( ! $user_id ) {
				return null;
			}
			$image_id = (int) get_user_meta( $user_id, HEADLESS_AUTHOR_IMAGE_META, true );
			return headless_theme_resolve_image( $image_id );
		},
	] );
} );
