<?php
/**
 * Plugin Name:       Narkis Avatars
 * Plugin URI:        https://narkis.ai/developers/avatars
 * Description:       Adds free AI-generated faces (of people who do not exist) as a Default Avatar option, for commenters and users without a Gravatar.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Narkis.ai
 * Author URI:        https://narkis.ai
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       narkis-avatars
 *
 * @package NarkisAvatars
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NARKIS_AVATARS_VERSION', '1.0.0' );

/**
 * Key stored in the core `avatar_default` option when our default is selected.
 */
define( 'NARKIS_AVATARS_DEFAULT_KEY', 'narkis_avatars' );

/**
 * Option name for the "Always use Narkis faces" setting.
 */
define( 'NARKIS_AVATARS_OPTION_ALWAYS', 'narkis_avatars_always' );

/**
 * Base URL of the avatar API. Path form, no query string, because Gravatar
 * ignores default-image URLs that contain a query string.
 */
define( 'NARKIS_AVATARS_API_BASE', 'https://narkis.ai/api/avatar/' );

/**
 * Add "Narkis AI face (generated)" to Settings > Discussion > Default Avatar.
 *
 * @param array $defaults Existing avatar defaults (key => label).
 * @return array
 */
function narkis_avatars_add_default( $defaults ) {
	$defaults[ NARKIS_AVATARS_DEFAULT_KEY ] = __( 'Narkis AI face (generated)', 'narkis-avatars' );
	return $defaults;
}
add_filter( 'avatar_defaults', 'narkis_avatars_add_default' );

/**
 * Whether the "Always use Narkis faces instead of Gravatar" setting is on.
 *
 * @return bool
 */
function narkis_avatars_always_enabled() {
	return (bool) get_option( NARKIS_AVATARS_OPTION_ALWAYS, false );
}

/**
 * Build a privacy-safe seed for the given avatar identifier.
 *
 * Never returns a raw email address: emails are hashed with md5 (after
 * trim + lowercase, same normalisation as Gravatar), user IDs are hashed too.
 *
 * @param mixed $id_or_email User ID, email, WP_User, WP_Post, WP_Comment or md5 hash.
 * @return string 32-char hex seed.
 */
function narkis_avatars_seed( $id_or_email ) {
	$email   = '';
	$user_id = 0;
	$name    = '';

	if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		// WP_Comment.
		if ( ! empty( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		}
		if ( ! empty( $id_or_email->comment_author_email ) ) {
			$email = $id_or_email->comment_author_email;
		}
		if ( ! empty( $id_or_email->comment_author ) ) {
			$name = $id_or_email->comment_author;
		}
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = (int) $id_or_email->ID;
		$email   = $id_or_email->user_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_id = (int) $id_or_email->post_author;
	} elseif ( is_numeric( $id_or_email ) ) {
		$user_id = absint( $id_or_email );
	} elseif ( is_string( $id_or_email ) ) {
		$value = strtolower( trim( $id_or_email ) );
		// Core accepts "{md5}@md5.gravatar.com" as an already-hashed identifier.
		if ( preg_match( '/^([a-f0-9]{32})@md5\.gravatar\.com$/', $value, $matches ) ) {
			return $matches[1];
		}
		$email = $value;
	}

	if ( '' === $email && $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$email = $user->user_email;
		}
	}

	if ( '' !== $email ) {
		return md5( strtolower( trim( $email ) ) );
	}
	if ( $user_id ) {
		return md5( 'user-' . $user_id );
	}
	if ( '' !== $name ) {
		return md5( 'name-' . strtolower( trim( $name ) ) );
	}
	return md5( 'anonymous' );
}

/**
 * Build the Narkis avatar URL: https://narkis.ai/api/avatar/{size}/{seed}.jpg
 *
 * @param string $seed Hashed seed.
 * @param int    $size Requested size in pixels.
 * @return string
 */
function narkis_avatars_url( $seed, $size ) {
	$size = max( 16, min( 1024, absint( $size ) ) );
	$seed = preg_replace( '/[^a-f0-9]/', '', strtolower( $seed ) );

	/**
	 * Filters the Narkis avatar URL.
	 *
	 * @param string $url  Avatar URL.
	 * @param string $seed Hashed seed.
	 * @param int    $size Size in pixels.
	 */
	return apply_filters( 'narkis_avatars_url', NARKIS_AVATARS_API_BASE . $size . '/' . $seed . '.jpg', $seed, $size );
}

/**
 * Point avatars at Narkis faces.
 *
 * - "Always" on: skip Gravatar entirely and serve the Narkis URL directly.
 * - Narkis selected as Default Avatar: keep Gravatar, but pass the Narkis URL
 *   as Gravatar's `d=` fallback so people without a Gravatar get a face.
 *
 * @param array $args        Avatar data arguments.
 * @param mixed $id_or_email Avatar identifier.
 * @return array
 */
function narkis_avatars_pre_get_avatar_data( $args, $id_or_email ) {
	if ( ! empty( $args['url'] ) ) {
		// Another plugin already provided a URL (e.g. a local avatar). Respect it.
		return $args;
	}

	if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		// Match core: no avatars for comment types like pingbacks.
		$comment_type = empty( $id_or_email->comment_type ) ? 'comment' : $id_or_email->comment_type;
		if ( ! in_array( $comment_type, (array) get_avatar_comment_types(), true ) ) {
			return $args;
		}
	}

	$default    = isset( $args['default'] ) ? $args['default'] : get_option( 'avatar_default', 'mystery' );
	$is_ours    = ( NARKIS_AVATARS_DEFAULT_KEY === $default );
	$force      = ! empty( $args['force_default'] );
	$always     = narkis_avatars_always_enabled();
	$size       = isset( $args['size'] ) ? (int) $args['size'] : 96;
	$narkis_url = narkis_avatars_url( narkis_avatars_seed( $id_or_email ), $size );

	if ( $force && ! $is_ours ) {
		// E.g. the Default Avatar previews for other options on Settings > Discussion.
		return $args;
	}

	if ( $always || ( $is_ours && $force ) ) {
		$args['url']          = $narkis_url;
		$args['found_avatar'] = true;
		return $args;
	}

	if ( $is_ours ) {
		// Gravatar requires a public, query-string-free URL with an image extension.
		$args['default'] = $narkis_url;
	}

	return $args;
}
add_filter( 'pre_get_avatar_data', 'narkis_avatars_pre_get_avatar_data', 10, 2 );

/**
 * Register the setting and its field on Settings > Discussion.
 */
function narkis_avatars_register_settings() {
	register_setting(
		'discussion',
		NARKIS_AVATARS_OPTION_ALWAYS,
		array(
			'type'              => 'boolean',
			'description'       => __( 'Always use Narkis faces instead of Gravatar.', 'narkis-avatars' ),
			'sanitize_callback' => 'narkis_avatars_sanitize_checkbox',
			'default'           => false,
		)
	);

	add_settings_section(
		'narkis_avatars',
		__( 'Narkis Avatars', 'narkis-avatars' ),
		'narkis_avatars_section_text',
		'discussion'
	);

	add_settings_field(
		NARKIS_AVATARS_OPTION_ALWAYS,
		__( 'Skip Gravatar', 'narkis-avatars' ),
		'narkis_avatars_field_always',
		'discussion',
		'narkis_avatars',
		array( 'label_for' => NARKIS_AVATARS_OPTION_ALWAYS )
	);
}
add_action( 'admin_init', 'narkis_avatars_register_settings' );

/**
 * Sanitize the checkbox value to 1 or 0.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function narkis_avatars_sanitize_checkbox( $value ) {
	return empty( $value ) ? 0 : 1;
}

/**
 * Section intro text.
 */
function narkis_avatars_section_text() {
	echo '<p>' . esc_html__( 'Faces are AI-generated people who do not exist. The same commenter always gets the same face. Only a hash of the email address and the image size are sent to narkis.ai.', 'narkis-avatars' ) . '</p>';
}

/**
 * Render the "Always use Narkis faces" checkbox.
 */
function narkis_avatars_field_always() {
	printf(
		'<label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
		esc_attr( NARKIS_AVATARS_OPTION_ALWAYS ),
		checked( narkis_avatars_always_enabled(), true, false ),
		esc_html__( 'Always use Narkis faces instead of Gravatar', 'narkis-avatars' ),
		esc_html__( 'When checked, no avatar requests go to Gravatar at all; every user and commenter gets a generated face.', 'narkis-avatars' )
	);
}

/**
 * Add a Settings link on the Plugins screen.
 *
 * @param array $links Action links.
 * @return array
 */
function narkis_avatars_action_links( $links ) {
	$url = admin_url( 'options-discussion.php#narkis_avatars_always' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'narkis-avatars' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'narkis_avatars_action_links' );

/**
 * On deactivation, switch the Default Avatar back to Mystery Person if ours was selected,
 * so core never sends an unknown default to Gravatar.
 */
function narkis_avatars_deactivate() {
	if ( NARKIS_AVATARS_DEFAULT_KEY === get_option( 'avatar_default' ) ) {
		update_option( 'avatar_default', 'mystery' );
	}
}
register_deactivation_hook( __FILE__, 'narkis_avatars_deactivate' );
