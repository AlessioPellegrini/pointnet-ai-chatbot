<?php
/**
 * PointNet_AI_Chatbot_Updater
 *
 * Lightweight native GitHub Release updater for PointNet AI Chatbot.
 * Queries GitHub repository releases and git tags, informs WordPress of available updates,
 * and ensures safe installation with correct folder naming.
 *
 * NOTE: Remove this file, its loader in pointnet-ai-chatbot.php and the "Update URI" header
 * before submitting the plugin to WordPress.org.
 *
 * @package PointNet_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PointNet_AI_Chatbot_Updater {

	private const GITHUB_REPO   = 'AlessioPellegrini/pointnet-ai-chatbot';
	private const SLUG          = 'pointnet-ai-chatbot';
	private const TRANSIENT_KEY = 'pn_ai_chatbot_github_release';
	private const CACHE_TTL     = 3600; // 1 hour.

	/**
	 * Hook updater into WordPress lifecycle.
	 */
	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update_force' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_recheck' ) );
	}

	/**
	 * Whether the current request is a manual "check for updates" triggered by an admin.
	 */
	private static function is_recheck_request(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Read-only cache invalidation, no state change.
		if ( ! empty( $_GET['force-check'] ) ) {
			return true;
		}
		foreach ( array( $_GET, $_POST ) as $source ) {
			if ( isset( $source['action'] ) && 'do-core-recheck' === sanitize_key( wp_unslash( $source['action'] ) ) ) {
				return true;
			}
		}
		// phpcs:enable
		return false;
	}

	/**
	 * Invalidate GitHub cache when admin triggers a manual update recheck.
	 */
	public static function handle_admin_recheck(): void {
		if ( current_user_can( 'update_plugins' ) && self::is_recheck_request() ) {
			delete_site_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Retrieve the latest release or git tag from GitHub API with transient caching.
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array|null
	 */
	public static function get_latest_release( bool $force_refresh = false ): ?array {
		if ( ! $force_refresh && self::is_recheck_request() ) {
			$force_refresh = true;
		}

		if ( ! $force_refresh ) {
			$cached = get_site_transient( self::TRANSIENT_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$args = array(
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; PointNetAIChatbot/' . PN_AI_CHATBOT_VERSION . '; ' . home_url(),
			'headers'    => array( 'Accept' => 'application/vnd.github.v3+json' ),
		);

		$best_release = null;

		// 1. Formal GitHub Releases.
		$rel_res = wp_remote_get( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases', $args );
		if ( ! is_wp_error( $rel_res ) && 200 === wp_remote_retrieve_response_code( $rel_res ) ) {
			$releases = json_decode( wp_remote_retrieve_body( $rel_res ), true );
			if ( is_array( $releases ) ) {
				foreach ( $releases as $rel ) {
					if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) ) {
						continue;
					}
					$tag = isset( $rel['tag_name'] ) ? (string) $rel['tag_name'] : '';
					if ( ! preg_match( '/^v?([0-9]+\.[0-9]+(?:\.[0-9]+)?.*)$/i', $tag, $m ) ) {
						continue;
					}
					if ( ! $best_release || version_compare( $m[1], ltrim( $best_release['tag_name'], 'vV ' ), '>' ) ) {
						$best_release = $rel;
					}
				}
			}
		}

		// 2. Git tags, in case a tag was pushed without a formal GitHub release.
		$tags_res = wp_remote_get( 'https://api.github.com/repos/' . self::GITHUB_REPO . '/tags?per_page=100', $args );
		if ( ! is_wp_error( $tags_res ) && 200 === wp_remote_retrieve_response_code( $tags_res ) ) {
			$tags = json_decode( wp_remote_retrieve_body( $tags_res ), true );
			if ( is_array( $tags ) ) {
				foreach ( $tags as $tag_item ) {
					$tag_name = isset( $tag_item['name'] ) ? (string) $tag_item['name'] : '';
					if ( ! preg_match( '/^v?([0-9]+\.[0-9]+(?:\.[0-9]+)?.*)$/i', $tag_name, $m ) ) {
						continue;
					}
					$current_best = $best_release ? ltrim( $best_release['tag_name'], 'vV ' ) : '0.0.0';
					if ( version_compare( $m[1], $current_best, '>' ) ) {
						$best_release = array(
							'tag_name'    => $tag_name,
							'zipball_url' => isset( $tag_item['zipball_url'] ) ? $tag_item['zipball_url'] : 'https://api.github.com/repos/' . self::GITHUB_REPO . '/zipball/refs/tags/' . $tag_name,
							'html_url'    => 'https://github.com/' . self::GITHUB_REPO . '/releases/tag/' . $tag_name,
							'body'        => '',
							'assets'      => array(),
						);
					}
				}
			}
		}

		if ( ! $best_release || empty( $best_release['tag_name'] ) ) {
			return null;
		}

		set_site_transient( self::TRANSIENT_KEY, $best_release, self::CACHE_TTL );
		return $best_release;
	}

	/**
	 * Pick the download package: attached zip asset if present, GitHub zipball otherwise.
	 *
	 * @param array $release Release data.
	 */
	private static function get_package_url( array $release ): string {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && self::SLUG . '.zip' === $asset['name'] && ! empty( $asset['browser_download_url'] ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}
		return isset( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
	}

	/**
	 * Forced check executed when WordPress rebuilds the update_plugins transient.
	 *
	 * @param object|null $transient WordPress update plugins transient.
	 * @return object|null
	 */
	public static function check_update_force( $transient ) {
		return self::check_update( $transient, true );
	}

	/**
	 * Inject an available update into the WordPress update transient.
	 *
	 * @param object|null $transient     WordPress update plugins transient.
	 * @param bool        $force_refresh Whether to bypass transient cache.
	 * @return object|null
	 */
	public static function check_update( $transient, bool $force_refresh = false ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::get_latest_release( $force_refresh );
		if ( ! $release ) {
			return $transient;
		}

		$plugin_file = plugin_basename( PN_AI_CHATBOT_FILE );
		$latest_ver  = ltrim( $release['tag_name'], 'vV ' );

		global $wp_version;

		$item = (object) array(
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'new_version'  => $latest_ver,
			'url'          => isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . self::GITHUB_REPO,
			'package'      => self::get_package_url( $release ),
			'requires'     => '6.0',
			'tested'       => ! empty( $wp_version ) ? $wp_version : '7.1',
			'requires_php' => '7.4',
			'icons'        => array(),
			'banners'      => array(),
		);

		if ( version_compare( $latest_ver, PN_AI_CHATBOT_VERSION, '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $plugin_file ] = $item;
			unset( $transient->no_update[ $plugin_file ] );
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $plugin_file ] = $item;
			unset( $transient->response[ $plugin_file ] );
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the WordPress "View version details" modal.
	 *
	 * @param false|object $res    The result object.
	 * @param string       $action The type of information requested.
	 * @param object       $args   Plugin API arguments.
	 * @return false|object
	 */
	public static function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $res;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $res;
		}

		$changelog = ! empty( $release['body'] )
			? nl2br( esc_html( $release['body'] ) )
			: self::get_readme_changelog();
		if ( '' === $changelog ) {
			$changelog = esc_html__( 'Consult GitHub releases for full changelog.', 'pointnet-ai-chatbot' );
		}

		global $wp_version;

		return (object) array(
			'name'          => 'PointNet AI Chatbot',
			'slug'          => self::SLUG,
			'version'       => ltrim( $release['tag_name'], 'vV ' ),
			'author'        => '<a href="https://www.pointnet.it/">PointNet</a>',
			'homepage'      => 'https://www.pointnet.it/pointnet-ai-chatbot/',
			'requires'      => '6.0',
			'tested'        => ! empty( $wp_version ) ? $wp_version : '7.1',
			'requires_php'  => '7.4',
			'download_link' => self::get_package_url( $release ),
			'sections'      => array(
				'description' => esc_html__( 'AI-powered virtual assistant using RAG to answer user queries with your WordPress site content.', 'pointnet-ai-chatbot' ),
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Ensure the extracted directory is named 'pointnet-ai-chatbot' so WordPress updates it in place
	 * (GitHub zipballs extract to "<owner>-<repo>-<sha>").
	 *
	 * @param string      $source        Path on local filesystem for extracted archive.
	 * @param string      $remote_source Remote source path.
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra hook data.
	 * @return string|WP_Error
	 */
	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		$plugin_file      = plugin_basename( PN_AI_CHATBOT_FILE );
		$correct_dir_name = dirname( $plugin_file );
		$is_our_plugin    = ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $plugin_file;

		if ( ! $is_our_plugin && file_exists( trailingslashit( $source ) . 'pointnet-ai-chatbot.php' ) ) {
			$is_our_plugin = true;
		}
		if ( ! $is_our_plugin ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . $correct_dir_name;
		if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $target ) ) {
			return trailingslashit( $target );
		}

		return new WP_Error(
			'pn_ai_chatbot_rename_failed',
			esc_html__( 'Failed to rename plugin update folder to pointnet-ai-chatbot.', 'pointnet-ai-chatbot' )
		);
	}

	/**
	 * Parse the Changelog section of the local readme.txt into HTML (fallback when the release has no body).
	 */
	public static function get_readme_changelog(): string {
		$readme_file = PN_AI_CHATBOT_DIR . 'readme.txt';
		if ( ! file_exists( $readme_file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		$content = (string) file_get_contents( $readme_file );
		if ( ! preg_match( '/==\s*Changelog\s*==\s*(.*?)(?:\n==[^=]|$)/is', $content, $matches ) ) {
			return '';
		}

		// Keep only the 5 most recent versions to keep the modal readable.
		$html     = '';
		$in_list  = false;
		$versions = 0;
		foreach ( explode( "\n", trim( $matches[1] ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^=\s*([^=]+?)\s*=$/', $line, $vm ) ) {
				if ( ++$versions > 5 ) {
					break;
				}
				if ( $in_list ) {
					$html   .= '</ul>';
					$in_list = false;
				}
				$html .= '<h4>' . esc_html( $vm[1] ) . '</h4>';
			} elseif ( 0 === strpos( $line, '*' ) ) {
				if ( ! $in_list ) {
					$html   .= '<ul>';
					$in_list = true;
				}
				$html .= '<li>' . esc_html( trim( substr( $line, 1 ) ) ) . '</li>';
			}
		}

		return $html . ( $in_list ? '</ul>' : '' );
	}

	/**
	 * Clear release transient cache when an upgrade finishes.
	 *
	 * @param WP_Upgrader $upgrader   WP_Upgrader instance.
	 * @param array       $hook_extra Hook extra data.
	 */
	public static function clear_cache( $upgrader, $hook_extra = array() ): void {
		delete_site_transient( self::TRANSIENT_KEY );
	}
}
