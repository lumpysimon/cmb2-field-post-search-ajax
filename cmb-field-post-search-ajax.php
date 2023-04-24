<?php
/*
Plugin Name: CMB2 Field Type: Post Search Ajax
Plugin URI: https://github.com/lumpysimon/cmb2-field-post-search-ajax
GitHub Plugin URI: https://github.com/lumpysimon/cmb2-field-post-search-ajax
Description: CMB2 field type to attach posts to each others.
Version: 1.1.6
Author: Magina, Simon Blackbourn, Matt Radford
Author URI: http://magina.fr/
License: GPLv2+
*/

/**
 * Class MAG_CMB2_Field_Post_Search_Ajax
 */
if( ! class_exists( 'MAG_CMB2_Field_Post_Search_Ajax' ) ) {

	class MAG_CMB2_Field_Post_Search_Ajax {

		/**
		 * Current version number
		 */
		const VERSION = '1.1.6';

		/**
		 * The url which is used to load local resources
		 */
		protected static $url = '';

		/**
		 * Initialize the plugin by hooking into CMB2
		 */
		public function __construct() {
			add_action( 'cmb2_render_post_search_ajax', array( $this, 'render' ), 10, 5 );
			add_action( 'cmb2_sanitize_post_search_ajax', array( $this, 'sanitize' ), 10, 4 );
			add_action( 'wp_ajax_cmb_post_search_ajax_get_results', array( $this, 'cmb_post_search_ajax_get_results' ) );
			add_filter( 'query_vars', [ $this, 'add_query_var' ] );

		}

		/**
		 * Render field
		 */
		public function render( $field, $value, $object_id, $object_type, $field_type ) {
			$this->setup_admin_scripts();
			$field_name = $field->_name();
			$field_id = $field->id();

			if( empty( $field->args( 'limit' ) ) or 1 == $field->args( 'limit' ) ){
				if(is_array($value)){ $value = $value[0]; }
				if( $field->args( 'object_type' ) == 'user' ){
					$field_value = ($value ? get_userdata($value)->display_name : '');
				}
				else{
					$field_value = ($value ? get_the_title($value) : '');
				}
				echo $field_type->input( array(
					'type'  => 'hidden',
					'name'  => 'results_' . $field_name,
					'id'    => 'results_' . $field_id,
					'value' => $value,
					'desc'  => false
				) );
			}
			else{
				echo '<ul class="cmb-post-search-ajax-results" id="results_' . $field_name . '">';
				if( isset($value) && !empty($value) ){
					if( !is_array($value) ){ $value = array($value); }
					foreach($value as $val){
						$handle = ($field->args( 'sortable' )) ? '<span class="hndl"></span>' : '';
						if( $field->args( 'object_type' ) == 'user' ){
							$guid 	= get_edit_user_link($val);
							$user	= get_userdata($val);
							$title	= $user->display_name;
						}
						else{
							$guid 	= get_edit_post_link($val);
							$title	= get_the_title($val);
						}
						echo '<li>'.$handle.'<input type="hidden" name="results_'.$field_name.'[]" value="'.$val.'"><a href="'.$guid.'" target="_blank" class="edit-link">'.$title.'</a><a class="remover"><span class="dashicons dashicons-no"></span><span class="dashicons dashicons-dismiss"></span></a></li>';
					}
				}
				echo '</ul>';
				$field_value = '';
			}

			echo $field_type->input( array(
				'type' 			=> 'text',
				'name' 			=> $field_name,
				'id'			=> $field_name,
				'class'			=> 'cmb-post-search-ajax',
				'value' 		=> $field_value,
				'desc'			=> false,
				'data-limit'	=> $field->args( 'limit' ) ? $field->args( 'limit' ) : '1',
				'data-sortable'	=> $field->args( 'sortable' ) ? $field->args( 'sortable' ) : '0',
				'data-object'	=> $field->args( 'object_type' ) ? $field->args( 'object_type' ) : 'post',
				'data-queryargs'=> $field->args( 'query_args' ) ? htmlspecialchars( json_encode( $field->args( 'query_args' ) ), ENT_QUOTES, 'UTF-8' ) : ''
			) );

			echo '<img src="'.admin_url( 'images/spinner.gif' ).'" class="cmb-post-search-ajax-spinner" />';

			$field_type->_desc( true, true );

		}

		/**
		 * Get the input
		 */
		public function sanitize( $override_value, $value, $object_id, $field_args ) {
			$fid = $field_args['id'];
			$data = $field_args['render_row_cb'][0]->data_to_save['results_'.$fid];
			if ( ! empty( $data ) ) {
				$value = $data;
			}
			else{
				$value = false;
			}
			return $value;
		}

		/**
		 * Defines the url which is used to load local resources. Based on, and uses,
		 * the CMB2_Utils class from the CMB2 library.
		 */
		public static function url( $path = '' ) {
			if ( self::$url ) {
				return self::$url . $path;
			}

			/**
			 * Set the variable cmb2_fpsa_dir
			 */
			$cmb2_fpsa_dir = trailingslashit( dirname( __FILE__ ) );

			/**
			 * Use CMB2_Utils to gather the url from cmb2_fpsa_dir
			 */
			$cmb2_fpsa_url = CMB2_Utils::get_url_from_dir( $cmb2_fpsa_dir );

			/**
			 * Filter the CMB2 FPSA location url
			 */
			self::$url = trailingslashit( apply_filters( 'cmb2_fpsa_url', $cmb2_fpsa_url, self::VERSION ) );

			return self::$url . $path;
		}

		/**
		 * Enqueue scripts and styles
		 */
		public function setup_admin_scripts() {

			wp_register_script( 'jquery-autocomplete', self::url( 'js/jquery.autocomplete.min.js' ), array( 'jquery' ), self::VERSION );
			wp_register_script( 'mag-post-search-ajax', self::url( 'js/mag-post-search-ajax.js' ), array( 'jquery', 'jquery-autocomplete', 'jquery-ui-sortable' ), self::VERSION );
			wp_localize_script( 'mag-post-search-ajax', 'psa', array(
				'ajaxurl' 	=> admin_url( 'admin-ajax.php' ),
				'nonce'		=> wp_create_nonce( 'mag_cmb_post_search_ajax_get_results' )
			) );
			wp_enqueue_script( 'mag-post-search-ajax' );
			wp_enqueue_style( 'mag-post-search-ajax', self::url( 'css/mag-post-search-ajax.css' ), array(), self::VERSION );

		}

		/**
		 * Ajax request : get results
		 */
		public function cmb_post_search_ajax_get_results() {
			$nonce = $_POST['psacheck'];
			if ( ! wp_verify_nonce( $nonce, 'mag_cmb_post_search_ajax_get_results' ) ) {
				die( json_encode( array( 'error' => __( 'Error : Unauthorized action' ) ) ) );
			}
			else {
				$args 		= json_decode(stripslashes(htmlspecialchars_decode($_POST['query_args'])), true);
				$args['s'] 	= $_POST['query'];
				$args['cmb_post_ajax_search'] = true;
				$datas 		= array();
				if( $_POST['object'] == 'user' ){
					$args['search'] = '*'.esc_attr($_POST['query']).'*';
					$users 	= new WP_User_Query( $args );
					$results = $users->get_results();
					if (!empty($results)) {
						foreach( $results as $result ){
							$user_info = get_userdata($result->ID);
							// Define filter "mag_cmb_post_search_ajax_result" to allow customize ajax results.
							$datas[] = apply_filters( 'mag_cmb_post_search_ajax_result', array(
								'value' => $user_info->display_name,
								'data'	=> $result->ID,
								'guid'	=> get_edit_user_link($result->ID)
							) );
						}
					}
				}else{
					$results 	= new WP_Query( $args );
					if ( $results->have_posts() ) :
						while ( $results->have_posts() ) : $results->the_post();
							// Define filter "mag_cmb_post_search_ajax_result" to allow customize ajax results.
							$datas[] = apply_filters( 'mag_cmb_post_search_ajax_result', array(
								'value' => get_the_title(),
								'data'	=> get_the_ID(),
								'guid'	=> get_edit_post_link()
							), $args );
						endwhile;
					endif;
				}
				wp_reset_postdata();
				$datas = apply_filters( 'mag_cmb_post_search_ajax_results', $datas, $args );
				die( json_encode( $datas ) );
			}
		}

		function add_query_var( $vars ) {

			$vars[] = 'cmb_post_ajax_search';

			return $vars;

		}

	}

}

$mag_cmb2_field_post_search_ajax = new MAG_CMB2_Field_Post_Search_Ajax();
