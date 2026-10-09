<?php
/**
 * Uninstall handler for PMT PostGrid for block editor with elementor support.
 *
 * WordPress only ever includes this exact file, and only when a user
 * clicks "Delete" on an already-deactivated plugin from the Plugins
 * screen -- never on simple deactivation. This is the standard,
 * documented mechanism for plugin cleanup:
 * https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * Removes every piece of persistent data this plugin writes:
 * - wp_postmeta: _pmtpofob_views on every post that has one (this plugin's
 *   own view-count tracking).
 * - Transients: the short-lived per-instance settings cache used by the
 *   live front-end AJAX category/sort filter (pmtpofob_grid_{instance_id},
 *   12-hour TTL). These self-expire on their own, but a genuine
 *   uninstall shouldn't leave anything behind to wait out.
 *
 * Deliberately does NOT touch any block/widget configuration saved
 * inside post_content (page/post content itself) -- that's the user's
 * own content, not this plugin's internal data, and stays untouched
 * regardless of whether the plugin is later reinstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Runs the actual cleanup against whichever site's tables are currently
 * active -- called once for a single site, or once per site on a
 * multisite network (see below).
 */
function pmtpofob_post_grid_uninstall_cleanup() {
	global $wpdb;

	// Post meta -- every post that has our view-count meta, not just one.
	// A bulk delete with no caching use case: this runs exactly once,
	// ever, right before the data it touches ceases to exist -- there's
	// no "next read" for a cache to save.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_pmtpofob_views' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Transients (pmtpofob_grid_{instance_id}) -- both the value and its
	// paired timeout option, deleted directly since there's no
	// delete_transient() call that supports a wildcard/LIKE match. Same
	// reasoning as above -- a bulk, pattern-based, one-time delete has
	// no meaningful caching equivalent.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_pmtpofob_grid_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pmtpofob_grid_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$pmtpofob_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $pmtpofob_site_ids as $pmtpofob_site_id ) {
		switch_to_blog( $pmtpofob_site_id );
		pmtpofob_post_grid_uninstall_cleanup();
		restore_current_blog();
	}
} else {
	pmtpofob_post_grid_uninstall_cleanup();
}
