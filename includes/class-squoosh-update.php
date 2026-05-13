<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DD_Plugin_Updater {

	private $endpoint_token;
	private $plugin_slug;
	private $plugin_file;
	private $api_base_url;

	public function __construct( $endpoint_token, $plugin_slug, $plugin_file = null ) {
		$this->endpoint_token = $endpoint_token;
		$this->plugin_slug = $plugin_slug;
		
		if ( ! $plugin_file ) {
			$plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
		}
		$this->plugin_file = $plugin_file;
		
		$this->api_base_url = 'https://daniyaldev.com/wp-json/dd-dm/v1/';

		// Hook into WordPress update system
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'site_transient_update_plugins', [ $this, 'maybe_clear_update_cache' ] );
		
		// Schedule periodic update checks
		add_action( 'admin_init', [ $this, 'schedule_update_check' ] );
		add_action( 'dd_plugin_check_updates_' . $plugin_slug, [ $this, 'force_update_check' ] );
	}

	/**
	 * Check for plugin updates and inject into WordPress update transient
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get current version from plugin header
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_file );
		$current_version = $plugin_data['Version'];

		// Build update check URL using slug with optional token parameter
		$url = $this->api_base_url . 'update/' . $this->plugin_slug . '/';
		$args = [
			'timeout' => 10,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body' => json_encode( [ 'current_version' => $current_version ] )
		];

		// If endpoint_token is provided, add it as a query parameter for validation
		if ( ! empty( $this->endpoint_token ) ) {
			$url = add_query_arg( 'token', $this->endpoint_token, $url );
		}

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			error_log( '[DD Plugin Updater] Update check failed for ' . $this->plugin_slug . ': ' . 
				( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
			return $transient;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $data || ! isset( $data->has_update ) ) {
			return $transient;
		}

		// If update available, inject into WordPress update system
		if ( $data->has_update ) {
			$transient->response[ $this->plugin_file ] = (object) [
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $data->version,
				'url'         => $data->download_url ?? '',
				'package'     => $data->download_url ?? '',
				'icons'       => [],
				'banners'     => [],
				'banners_rtl' => [],
				'tested'      => $data->tested ?? '',
				'requires'    => $data->requires ?? '6.0',
				'compatibility' => new stdClass(),
			];
		}

		return $transient;
	}

	/**
	 * Provide plugin details for WordPress update screen
	 */
	public function plugin_info( $false, $action, $response ) {
		if ( $action !== 'plugin_information' || $response->slug !== $this->plugin_slug ) {
			return $false;
		}

		// Build version check URL using slug with optional token parameter
		$url = $this->api_base_url . 'version/' . $this->plugin_slug . '/';

		// If endpoint_token is provided, add it as a query parameter for validation
		if ( ! empty( $this->endpoint_token ) ) {
			$url = add_query_arg( 'token', $this->endpoint_token, $url );
		}

		$api_response = wp_remote_get( $url, [
			'timeout' => 10,
		] );

		if ( is_wp_error( $api_response ) || wp_remote_retrieve_response_code( $api_response ) !== 200 ) {
			return $false;
		}

		$data = json_decode( wp_remote_retrieve_body( $api_response ) );
		if ( ! $data ) {
			return $false;
		}

		// Build WordPress-compatible plugin info object
		$info = new stdClass();
		$info->name = $data->name ?? $this->plugin_slug;
		$info->slug = $this->plugin_slug;
		$info->version = $data->version ?? '1.0.0';
		$info->author = 'Plugin Developer';
		$info->author_profile = '';
		$info->requires = $data->requires ?? '6.0';
		$info->tested = $data->tested ?? '6.5';
		$info->requires_php = $data->requires_php ?? '8.0';
		$info->homepage = '';
		$info->sections = [
			'description' => $data->description ?? '',
			'changelog' => $data->changelog ?? '',
			'installation' => '',
		];
		$info->download_link = $data->download_url ?? '';
		$info->last_updated = $data->updated_at ?? '';
		$info->tags = [];
		$info->banners = [];
		$info->icons = [];
		$info->rating = 0;
		$info->num_ratings = 0;

		return $info;
	}

	/**
	 * Clear update cache when needed
	 */
	public function maybe_clear_update_cache( $transient ) {
		// Force recheck if cache is older than 12 hours
		if ( isset( $transient->last_checked ) && ( time() - $transient->last_checked ) > 12 * HOUR_IN_SECONDS ) {
			delete_site_transient( 'update_plugins' );
		}
		return $transient;
	}

	/**
	 * Schedule periodic update checks
	 */
	public function schedule_update_check() {
		if ( ! wp_next_scheduled( 'dd_plugin_check_updates_' . $this->plugin_slug ) ) {
			wp_schedule_event( time(), 'twicedaily', 'dd_plugin_check_updates_' . $this->plugin_slug );
		}
	}

	/**
	 * Force immediate update check (for admin manual refresh)
	 */
	public function force_update_check() {
		delete_site_transient( 'update_plugins' );
		set_site_transient( 'update_plugins', [], 0 );
	}
}