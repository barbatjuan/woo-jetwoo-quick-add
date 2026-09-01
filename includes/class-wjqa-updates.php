<?php
/**
 * Self-hosted update channel.
 *
 * @package Woo_JetWooBuilder_Quick_Add
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tells WordPress where to look for new versions of this plugin.
 *
 * A plugin that does not come from wordpress.org gets no update notice, so it silently
 * rots on every site it is installed on. This points WordPress at a small JSON manifest
 * instead, and from there the update appears in Plugins and Dashboard → Updates with a
 * working Update button, exactly like any plugin from the directory.
 *
 * WHAT THE MANIFEST LOOKS LIKE
 *
 *   {
 *     "version":      "3.3.0",
 *     "download_url": "https://example.com/updates/woo-jetwoo-quick-add-3.3.0.zip",
 *     "requires":     "6.0",
 *     "requires_php": "7.4",
 *     "tested":       "6.8",
 *     "last_updated": "2026-09-01 09:30:00",
 *     "sections": { "description": "…", "changelog": "…" }
 *   }
 *
 * Only `version` and `download_url` are required. `bin/release.sh` writes the file.
 *
 * READ THIS BEFORE PUBLISHING A MANIFEST
 *
 * WordPress will download whatever that URL serves and install it, unattended, on every
 * site running this plugin. The manifest host is therefore code execution on all of
 * them. Serve it from a host you control, over HTTPS, and treat write access to it the
 * way you would treat SSH access to the client sites themselves.
 *
 * The download URL is required to be `https://` for that reason: a plain-HTTP package
 * can be swapped in transit by anyone on the path between the client site and the host,
 * and the site would install it without a murmur.
 *
 * TURNING IT ON
 *
 * Off until a URL is configured, so a copy installed without one makes no outbound
 * requests at all. Set it in wp-config.php:
 *
 *   define( 'WJQA_UPDATE_MANIFEST', 'https://example.com/updates/woo-jetwoo-quick-add.json' );
 *
 * or through the `wjqa_update_manifest_url` filter.
 */
class WJQA_Updates {

	/**
	 * Where the fetched manifest is cached.
	 */
	const TRANSIENT = 'wjqa_update_manifest';

	/**
	 * How long a fetched manifest is trusted, in hours.
	 *
	 * WordPress checks for updates far more often than a plugin is released, and every
	 * check would otherwise be an outbound request blocking an admin page load.
	 */
	const CACHE_HOURS = 12;

	/**
	 * Register the feature.
	 *
	 * @return void
	 */
	public static function init() {
		if ( '' === self::manifest_url() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'offer_update' ] );
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_details' ], 20, 3 );
		add_action( 'upgrader_process_complete', [ __CLASS__, 'forget_manifest' ], 10, 2 );
	}

	/**
	 * URL of the update manifest, or an empty string to stay switched off.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		$url = defined( 'WJQA_UPDATE_MANIFEST' ) ? (string) WJQA_UPDATE_MANIFEST : '';

		/**
		 * Filters the update manifest URL.
		 *
		 * Return an empty string to switch the update channel off entirely, which is
		 * also the default. A site that has not configured one never calls out.
		 *
		 * @param string $url Manifest URL.
		 */
		$url = (string) apply_filters( 'wjqa_update_manifest_url', $url );

		// Anything but https is refused: see the note in the class docblock about what
		// this channel is allowed to do to a site.
		return 0 === stripos( $url, 'https://' ) ? $url : '';
	}

	/**
	 * Add this plugin to the list of available updates when the manifest is ahead.
	 *
	 * @param object $transient Update transient WordPress is about to store.
	 * @return object
	 */
	public static function offer_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$manifest = self::manifest();

		if ( ! $manifest ) {
			return $transient;
		}

		$file = plugin_basename( WJQA_FILE );
		$item = self::update_item( $manifest );

		if ( version_compare( $manifest['version'], WJQA_VERSION, '>' ) ) {
			$transient->response[ $file ] = $item;

			return $transient;
		}

		// Reporting "no update" rather than staying silent is what makes the Plugins
		// screen say the plugin is up to date instead of saying nothing about it.
		unset( $transient->response[ $file ] );
		$transient->no_update[ $file ] = $item;

		return $transient;
	}

	/**
	 * Fill the "View details" screen, which otherwise 404s for a plugin WordPress.org
	 * has never heard of.
	 *
	 * @param false|object|array $result Response from the plugins API.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = dirname( plugin_basename( WJQA_FILE ) );

		if ( ! isset( $args->slug ) || $args->slug !== $slug ) {
			return $result;
		}

		$manifest = self::manifest();

		if ( ! $manifest ) {
			return $result;
		}

		$data = get_file_data( WJQA_FILE, [ 'name' => 'Plugin Name', 'author' => 'Author' ] );

		return (object) [
			'name'          => $data['name'],
			'slug'          => $slug,
			'version'       => $manifest['version'],
			'author'        => $data['author'],
			'requires'      => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'tested'        => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'last_updated'  => isset( $manifest['last_updated'] ) ? $manifest['last_updated'] : '',
			'download_link' => $manifest['download_url'],
			'sections'      => isset( $manifest['sections'] ) && is_array( $manifest['sections'] )
				? array_map( 'wp_kses_post', $manifest['sections'] )
				: [],
		];
	}

	/**
	 * Shape a manifest into the object the update transient expects.
	 *
	 * @param array $manifest Validated manifest.
	 * @return object
	 */
	private static function update_item( $manifest ) {
		$file = plugin_basename( WJQA_FILE );

		return (object) [
			'id'           => $file,
			'slug'         => dirname( $file ),
			'plugin'       => $file,
			'new_version'  => $manifest['version'],
			'package'      => $manifest['download_url'],
			'url'          => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
			'requires'     => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'tested'       => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'icons'        => [],
			'banners'      => [],
			'banners_rtl'  => [],
		];
	}

	/**
	 * Fetch the manifest, cached.
	 *
	 * Every failure returns null and is cached as such for a shorter spell: a host that
	 * is down should not make every admin page load wait on a timeout, and it must never
	 * be mistaken for "no update available" long enough to matter.
	 *
	 * @return array|null
	 */
	private static function manifest() {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( 'none' === $cached ) {
			return null;
		}

		$response = wp_remote_get(
			self::manifest_url(),
			[
				'timeout' => 5,
				'headers' => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT, 'none', HOUR_IN_SECONDS );

			return null;
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! self::is_usable( $manifest ) ) {
			set_transient( self::TRANSIENT, 'none', HOUR_IN_SECONDS );

			return null;
		}

		set_transient( self::TRANSIENT, $manifest, self::CACHE_HOURS * HOUR_IN_SECONDS );

		return $manifest;
	}

	/**
	 * Whether a decoded manifest is safe to act on.
	 *
	 * A malformed manifest is treated as no manifest. The alternative is handing a
	 * half-parsed structure to the updater and letting WordPress try to install whatever
	 * fell out of it.
	 *
	 * @param mixed $manifest Decoded JSON.
	 * @return bool
	 */
	private static function is_usable( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return false;
		}

		if ( empty( $manifest['version'] ) || ! is_string( $manifest['version'] ) ) {
			return false;
		}

		if ( empty( $manifest['download_url'] ) || ! is_string( $manifest['download_url'] ) ) {
			return false;
		}

		// Same reasoning as the manifest URL itself: the package is code, and it may not
		// arrive over a channel anyone on the path can rewrite.
		return 0 === stripos( $manifest['download_url'], 'https://' );
	}

	/**
	 * Drop the cached manifest once this plugin has been updated.
	 *
	 * Without this the site keeps offering the version it has just installed until the
	 * cache expires.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $extra    Update context.
	 * @return void
	 */
	public static function forget_manifest( $upgrader, $extra ) {
		if ( ! isset( $extra['action'], $extra['type'] ) || 'update' !== $extra['action'] || 'plugin' !== $extra['type'] ) {
			return;
		}

		$file = plugin_basename( WJQA_FILE );

		if ( isset( $extra['plugins'] ) && is_array( $extra['plugins'] ) && ! in_array( $file, $extra['plugins'], true ) ) {
			return;
		}

		delete_transient( self::TRANSIENT );
	}
}
