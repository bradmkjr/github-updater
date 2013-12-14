<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Update a WordPress plugin from a GitHub repo.
 *
 * @package GitHub_Plugin_Updater
 * @author  Andy Fragen
 * @author  Codepress
 * @link    https://github.com/codepress/github-plugin-updater
 */
class GitHub_Plugin_Updater extends GitHub_Updater_API {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config
	 */
	public function __construct() {

		$this->type = 'github_plugin';

		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_plugin_headers', array( $this, 'add_plugin_headers' ) );

		// Get details of GitHub-sourced plugins
		$this->config = $this->get_plugin_meta();
		
		if ( empty( $this->config ) ) return;

		foreach ( $this->config as $plugin ) {
			$this->{$this->type} = $plugin;
			$this->set_defaults();
			$this->get_remote_info( basename( $this->{$this->type}->slug ) );
			$this->get_remote_tag();
			$this->get_remote_changes( 'CHANGES.md' );
			$this->{$this->type}->download_link = $this->construct_download_link();
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins' ) );
//		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
	}

	/**
	 * Put changelog in plugins_api
	 *
	 * @since 1.9.0
	 */
	public function plugins_api( $false, $action, $response ) {
		foreach ( (array) $this->config as $plugin ) {
			if ($response->slug === $plugin->repo) {
				$response->sections = $plugin->sections;
			}
		}
		return $response;  
	}

	/**
	 * Hook into pre_set_site_transient_update_plugins to update from GitHub.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient Original transient.
	 * @param stdClass plugin data
	 *
	 * @return $transient If all goes well, an updated transient that may include details of a plugin update.
	 */
	public function pre_set_site_transient_update_plugins( $transient ) {
		if ( empty( $transient->checked ) )
			return $transient;

		foreach ( (array) $this->config as $plugin ) {

			$remote_is_newer = ( 1 === version_compare( $plugin->remote_version, $plugin->local_version ) );

			if ( $remote_is_newer ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
				);

				$transient->response[ $plugin->slug ] = (object) $response;
			}
		}
		return $transient;
	}

}
