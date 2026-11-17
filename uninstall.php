<?php
/**
 * Uninstall Narkis Avatars: remove plugin options.
 *
 * @package NarkisAvatars
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'narkis_avatars_always' );

if ( 'narkis_avatars' === get_option( 'avatar_default' ) ) {
	update_option( 'avatar_default', 'mystery' );
}
