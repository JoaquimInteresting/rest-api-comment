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
 * @package    Wp_Rest_comment
 * @subpackage Wp_Rest_comment/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Wp_Rest_comment
 * @subpackage Wp_Rest_comment/includes
 * @author     Workcompany, Appsdabanda, joaquiminteresting <support@appsdabanda.com>
 */
class Wp_Rest_Comment_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wp-rest-comment',
			false,
			dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
		);

	}

}