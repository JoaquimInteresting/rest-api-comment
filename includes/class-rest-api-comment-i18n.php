<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://sk8.tech
 * @since      1.0.0
 *
 * @package    Rest_api_comment
 * @subpackage Rest_api_comment/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Rest_api_comment
 * @subpackage Rest_api_comment/includes
 * @author     Workcompany, Appsdabanda, joaquiminteresting <support@appsdabanda.com>
 */
class Rest_Api_Comment_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'rest-api-comment',
			false,
			dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
		);

	}

}