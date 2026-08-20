<?php
namespace WPComplianceCL\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides native WordPress updates from versioned GitHub Release assets.
 *
 * Public repositories work without credentials. Private repositories require
 * WPCCL_GITHUB_TOKEN to be defined outside the plugin, preferably in wp-config.php.
 */
final class GitHubReleaseProvider implements UpdateProviderInterface {
	private const DEFAULT_REPOSITORY = 'dmaurelc/wp-compliance-cl';
	private const PLUGIN_FILE = 'wp-compliance-cl/wp-compliance-cl.php';
	private const SLUG = 'wp-compliance-cl';
	private const UPDATE_URI = 'https://github.com/dmaurelc/wp-compliance-cl';
	private const RELEASE_CACHE_KEY = 'wpccl_github_latest_release_v1';

	public function boot(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'pre_download' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
	}

	public function filter_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
		unset( $plugin_data, $locales );

		if ( self::PLUGIN_FILE !== $plugin_file ) {
			return $update;
		}

		$release = $this->latest_release();
		if ( is_wp_error( $release ) || ! version_compare( $release['version'], WPCCL_VERSION, '>' ) ) {
			return false;
		}

		return array(
			'id'           => self::UPDATE_URI,
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['release_url'],
			'package'      => $release['package'],
			'tested'       => '7.1',
			'requires_php' => '8.1',
			'autoupdate'   => false,
		);
	}

	public function filter_plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'WP Compliance CL',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'WP Compliance CL',
			'homepage'      => self::UPDATE_URI,
			'requires'      => '6.5',
			'tested'        => '7.1',
			'requires_php'  => '8.1',
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => 'Compliance Hub para WordPress orientado a la Ley 21.719 de Chile.',
				'changelog'   => nl2br( esc_html( $release['notes'] ) ),
			),
		);
	}

	public function pre_download( $reply, string $package, $upgrader, array $hook_extra ) {
		unset( $upgrader );

		if ( false !== $reply || ! $this->is_our_upgrade( $hook_extra ) ) {
			return $reply;
		}

		$metadata = get_site_transient( $this->package_cache_key( $package ) );
		if ( ! is_array( $metadata ) ) {
			$release = $this->latest_release();
			if ( is_wp_error( $release ) || $release['package'] !== $package ) {
				return $reply;
			}
			$metadata = $release;
		}

		$tmp_file = wp_tempnam( 'wp-compliance-cl-' . $metadata['version'] . '.zip' );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'wpccl_update_temp_file', 'No fue posible crear el archivo temporal para la actualización.' );
		}

		$response = wp_safe_remote_get(
			$package,
			array(
				'headers'     => $this->github_headers( 'application/octet-stream' ),
				'timeout'     => 300,
				'redirection' => 5,
				'stream'      => true,
				'filename'    => $tmp_file,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->delete_temp_file( $tmp_file );
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! file_exists( $tmp_file ) || 0 === filesize( $tmp_file ) ) {
			$this->delete_temp_file( $tmp_file );
			return new \WP_Error( 'wpccl_update_download', 'GitHub no entregó un paquete de actualización válido.' );
		}

		$actual_checksum = hash_file( 'sha256', $tmp_file );
		if ( ! is_string( $actual_checksum ) || ! hash_equals( $metadata['checksum'], strtolower( $actual_checksum ) ) ) {
			$this->delete_temp_file( $tmp_file );
			return new \WP_Error( 'wpccl_update_checksum', 'La verificación SHA-256 del paquete de actualización falló.' );
		}

		return $tmp_file;
	}

	public function clear_cache_after_update( $upgrader, array $options ): void {
		unset( $upgrader );

		if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}

		$plugins = $options['plugins'] ?? array( $options['plugin'] ?? '' );
		if ( in_array( self::PLUGIN_FILE, $plugins, true ) ) {
			delete_site_transient( self::RELEASE_CACHE_KEY );
		}
	}

	private function latest_release() {
		$cached = get_site_transient( self::RELEASE_CACHE_KEY );
		if ( is_array( $cached ) ) {
			$this->cache_package_metadata( $cached );
			return $cached;
		}

		$repository = $this->repository();
		$endpoint = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( explode( '/', $repository, 2 )[0] ),
			rawurlencode( explode( '/', $repository, 2 )[1] )
		);

		$response = wp_safe_remote_get(
			$endpoint,
			array(
				'headers' => $this->github_headers(),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'wpccl_update_api', 'No fue posible consultar el último release de GitHub.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'vV' ) : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return new \WP_Error( 'wpccl_update_version', 'El release de GitHub no contiene una versión válida.' );
		}

		$zip_name = 'wp-compliance-cl-' . $version . '.zip';
		$zip_asset = $this->find_asset( $data['assets'] ?? array(), $zip_name );
		$checksum_asset = $this->find_asset( $data['assets'] ?? array(), $zip_name . '.sha256' );
		if ( ! $zip_asset || ! $checksum_asset ) {
			return new \WP_Error( 'wpccl_update_assets', 'El release no contiene el ZIP y su checksum requeridos.' );
		}

		$checksum = $this->fetch_checksum( $checksum_asset );
		if ( is_wp_error( $checksum ) ) {
			return $checksum;
		}

		$release = array(
			'version'     => $version,
			'release_url' => esc_url_raw( (string) ( $data['html_url'] ?? self::UPDATE_URI . '/releases' ) ),
			'package'     => $this->asset_download_url( $zip_asset ),
			'checksum'    => $checksum,
			'notes'       => (string) ( $data['body'] ?? '' ),
		);

		set_site_transient( self::RELEASE_CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );
		$this->cache_package_metadata( $release );

		return $release;
	}

	private function repository(): string {
		$repository = (string) apply_filters( 'wpccl_github_repository', self::DEFAULT_REPOSITORY );
		return preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository ) ? $repository : self::DEFAULT_REPOSITORY;
	}

	private function github_token(): string {
		$token = defined( 'WPCCL_GITHUB_TOKEN' ) ? (string) WPCCL_GITHUB_TOKEN : '';
		return trim( (string) apply_filters( 'wpccl_github_token', $token ) );
	}

	private function github_headers( string $accept = 'application/vnd.github+json' ): array {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'WP-Compliance-CL/' . WPCCL_VERSION,
			'X-GitHub-Api-Version' => '2022-11-28',
		);

		$token = $this->github_token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	private function find_asset( array $assets, string $name ): ?array {
		foreach ( $assets as $asset ) {
			if ( is_array( $asset ) && $name === ( $asset['name'] ?? '' ) ) {
				return $asset;
			}
		}
		return null;
	}

	private function asset_download_url( array $asset ): string {
		if ( '' !== $this->github_token() && ! empty( $asset['url'] ) ) {
			return esc_url_raw( (string) $asset['url'] );
		}
		return esc_url_raw( (string) ( $asset['browser_download_url'] ?? '' ) );
	}

	private function fetch_checksum( array $asset ) {
		$url = $this->asset_download_url( $asset );
		if ( '' === $url ) {
			return new \WP_Error( 'wpccl_update_checksum_url', 'El checksum del release no tiene una URL válida.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers'             => $this->github_headers( 'application/octet-stream' ),
				'timeout'             => 20,
				'redirection'         => 5,
				'limit_response_size' => 4096,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'wpccl_update_checksum_download', 'No fue posible descargar el checksum del release.' );
		}

		if ( ! preg_match( '/\b([a-fA-F0-9]{64})\b/', wp_remote_retrieve_body( $response ), $matches ) ) {
			return new \WP_Error( 'wpccl_update_checksum_format', 'El checksum del release no tiene un formato válido.' );
		}

		return strtolower( $matches[1] );
	}

	private function is_our_upgrade( array $hook_extra ): bool {
		if ( isset( $hook_extra['plugin'] ) ) {
			return self::PLUGIN_FILE === $hook_extra['plugin'];
		}
		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			return in_array( self::PLUGIN_FILE, $hook_extra['plugins'], true );
		}
		return false;
	}

	private function package_cache_key( string $package ): string {
		return 'wpccl_update_package_' . md5( $package );
	}

	private function cache_package_metadata( array $release ): void {
		if ( ! empty( $release['package'] ) && ! empty( $release['checksum'] ) ) {
			set_site_transient( $this->package_cache_key( $release['package'] ), $release, 12 * HOUR_IN_SECONDS );
		}
	}

	private function delete_temp_file( string $file ): void {
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}
}
