<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://appsdabanda.com
 * @since      1.0.0
 *
 * @package    Wp_Rest_Comment
 * @subpackage Wp_Rest_Comment/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wp_Rest_Comment
 * @subpackage Wp_Rest_Comment/public
 * @author     Workcompany <support@appsdabanda.com>
 */
class Wp_Rest_Comment_Public {
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */

    public function __construct($plugin_name,$version){

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
	 * Add the endpoints to the API
	 */
	public function add_api_route() {
		/**
		 * Handle Create Comment request.
		 */
		register_rest_route('wp/v2', 'comments/create', array(
                'methods' => 'POST',
                'callback' => array($this, 'create_comment'),
        ));
	}


    /**
	 * Creates a comment.
	 *
	 * @author Joaquim Interesting 
	 *
	 * @since    1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return  @return WP_REST_Response|WP_Error Response object on success, or error object on failure.
	 */
	public function create_comment($request = null) {
        $response = [];
        $parameters = $request->get_json_params();
		$post_id = sanitize_text_field($parameters['post']);
		$author_name = sanitize_text_field($parameters['author_name']);
		$author_email = sanitize_text_field($parameters['author_email']);
		$content = sanitize_text_field($parameters['content']);

        if(! empty(sanitize_text_field($parameters['id']))){
            return new WP_Error('rest_comment_exists',__( 'Cannot create existing comment.' ),array( 'status' => 400));
        }

		if(empty($post_id)){
			return new WP_Error('rest_argument_missing',__( "Post Id field 'post' is required." ),array( 'status' => 400));
		}

		if(empty($author_name)){
			return new WP_Error('rest_argument_missing',__( "Author name field 'author_name' is required." ),array( 'status' => 400));
		}

		if(empty($author_email)){
			return new WP_Error('rest_argument_missing',__( "Author email field 'author_email' is required." ),array( 'status' => 400));
		}

		if(empty($content)){
			return new WP_Error('rest_argument_missing',__( "Content field 'content' is required." ),array( 'status' => 400));
		}

















		$response = array(
			'id' => 1,
			'author_name' => $author_name,
			'author_email' => $author_email,
			'content' => $content,
			'Author' => $post_id
		);
		return new WP_REST_Response($response, 201);
    }

	
    /**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Rest_Comment_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Rest_Comment_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-rest-comment-public.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Rest_Comment_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Rest_Comment_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-rest-comment-public.js', array('jquery'), $this->version, false);

	}
}