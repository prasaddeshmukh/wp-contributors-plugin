<?php

/*
Plugin Name: ContributorsPlugin
Plugin URI: https://github.com/prasaddeshmukh/wp-contributors-pluign
Description: A plugin which allows users(admin/editor/author) to select authors(wordpress users) as a contributors for a post and displays them in a box named 'contributors box' on the respective post.
Author: PrasadBDeshmkh
Version: 1.0
Author URI: http://stackoverflow/users/2125917/bhau
License: GPLv3.
*/

// Deny direct access
if ( ! defined( 'ABSPATH' ) ) exit( 'Direct access is denied.' );

/**
 *Includes Settings page 
 */
 
define( 'CONTRIBUTORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); 
define( 'CONTRIBUTORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

	function contributors_plugin_load(){
			
		if( is_admin() )
			require_once( CONTRIBUTORS_PLUGIN_DIR.'includes/settings.php' );
		
		}
		
	contributors_plugin_load();

/**
 *class contributors_plugin to handle the backend(admin) side.
 */
 
class Contributors_Plugin {
	
	var $cont_taxonomy = 'contributors';
	
	function __construct() {

			//First to execute. registers 'contributors' taxonomy and creates terms with name 'user_login'
			add_action( 'init', array( $this, 'cp_add_contributors_taxonomy' ) );
			
			// Load admin_init function
			add_action( 'admin_init', array( $this, 'cp_admin_init' ) );
			
			//Adds meta box on post edit screen 	
			add_action( 'add_meta_boxes', array($this, 'cp_contributors_box' ) );
			 
			//Triggers when the post is published or updated 
			add_action( 'save_post', array($this, 'cp_update_post_contributors' ) );
			
			// Action to set up author auto-suggest
			add_action( 'wp_ajax_contributors_ajax_suggest', array( $this, 'cp_contributors_ajax_suggest' ) ); 
		
			}

	// PHP backward compatibility, just in case
	public function Contributors_Plugin() {

		$this->__construct();

			}

	
	function cp_admin_init() {
	
		//Include scripts
		add_action(	'admin_enqueue_scripts', array( $this, 'cp_contributors_scripts' ) );
		
		}

/**
 * 'init' hook
 * registering 'contributors' taxonomy and create/insert terms 
 */
	
	function cp_add_contributors_taxonomy() {
		$args = array(
			'hierarchical' => false,
			'update_count_callback' => '_update_post_term_count',
			'label' => false,
			'query_var' => false,
			'rewrite' => false,
			'public' => false,
			'sort' => true,
			'args' => array( 'orderby' => 'term_order' ),
			'show_ui' => false
			);
		
		//register taxonomy on the post type with arguments $args 
		register_taxonomy( $this->cont_taxonomy, 'post', $args );

		//insert or update terms for 'contributors' taxonomy on the post type
		$result = count_users();
		$total_users = $result[ 'total_users' ];
		
		for( $user_id = 1; $user_id <= $total_users; $user_id++ ) {
			
			$user = get_userdata( $user_id );		 
			$userlogin_for_term = $user->user_login;
			$userid_for_term = $user->ID;		
			
			$slug_term = 'contri-'.$userlogin_for_term;
		
			//Will return 0 if no such terms present.		
			$term_exists = term_exists( $userlogin_for_term, $this->cont_taxonomy ); 
			
			//If its 0. means term is not present so, add it.
			if( $term_exists == 0 && $term_exists == null ) { 		

				wp_insert_term(
  					$userlogin_for_term, // the term 
  					$this->cont_taxonomy, // the taxonomy
  					array(
    					'description'=> $userid_for_term,
    					'slug' => $slug_term
    					) );
    			 } else {
				
					wp_update_term(
  						$term_exists['term_id'], // the term_id to update 
  						$this->cont_taxonomy, // the taxonomy
  						array(
    						'description'=> $userid_for_term,
    						'slug' => $slug_term
    						) );
						}		
					}
			}//Registering 'contributors' taxonomy and create and insert terms

/**
 *'add_meta_boxes' hook
 * Adds a meta box on the Post edit screens.
 */

	function cp_contributors_box() {
		
		add_meta_box(
			'contributors-box-id', 'Contributors', array($this,'cp_user_names_with_checkboxes'), 'post', 'side', 'high' 			  
      );
      
      }

/**
 * Callback 'cp_user_names_with_checkboxes' from add_meta_box
 */
	
	function cp_user_names_with_checkboxes( $post ) {
	
		global $post;
		$post_id = $post->ID;		
		
		$original_author_id = $post->post_author;
		$orginal_author  = get_userdata( $original_author_id );		
		
		$currently_logged_user = get_current_user_id(); 
		
		$result = count_users();
		$total_users = $result[ 'total_users' ];
		 
		//Check option for original author display. if yes, execute following code to display it on meta box
		$set_options = get_option( 'plugin_options' );			
		if( $set_options[ 'author_option' ] == 'yes' ){		
			echo '<span id="post_author"> Post Author: '.$orginal_author->display_name.'<i style="color:grey"> '.$orginal_author->user_email.'</i></span>';
			}
		
		//Display AJAX powered search field and current contributors with checked checkboxes on meta box .  
		?>
					
			<input type="text" class="search_author" id="search_author_id" name="search_input" placeholder="Search by username or email" autocomplete="off" size="30"/>
			
			<!--Message to display when no user found matching the search term-->		
			<p id="nomatch" style="display: none; color: red; text-align: center;">No such User found!</p>	
			
			<!--a div to append selected users with checkboxes using js-->			
			<div class="toappend" id="contributors-list"></div>

		<?php
		for( $user_id = 1; $user_id <= $total_users; $user_id++ ) {
		
			$user = get_userdata ( $user_id );		 
			$username = $user->user_login;
			$display_name = $user->display_name;
			$email = $user->user_email; 
			
			//Get the term_taxonomy_ids from wp_term_relationships table for current post(represented in this table by field object_id).
			$post_contributor_tt_id = wp_get_post_terms( $post_id, $this->cont_taxonomy );
			
			//Original author of the post will not be displayed on the meta box
			if( $original_author_id != $user_id ) { 
		
				$term = get_term_by( 'name', $username, $this->cont_taxonomy );
				$term_id = $term->term_id;  
			
			foreach( $post_contributor_tt_id as $key => $tt_id ) {	
				if ($term_id == $tt_id->term_taxonomy_id ) {
			?>
		
				<div id="contributors-list">
					<p>
						
						<label id="my_check"> 
						<input id="my_check" type="checkbox" name="contributors[]" value="<?php echo $term_id;?>" 			
						<?php
								checked($term_id, $tt_id->term_taxonomy_id);	
							?>
						/> 
						<?php echo $display_name. ' <i style="color:grey">' .$email. '</i>';?> 
						</label>

   				</p> 
   			</div>			

   			<?php 
   			
					wp_nonce_field('contributors-list','contributors-nonce'); //nonce hidden filed
					
	   			}
				}
			}
		}
		
		wp_nonce_field('contributors-list','contributors-nonce'); //for the newly added contributors through search

	}


/**
 *'save_post' hook
 *update wp_term_relationships table by inserting term_taxonomy_ids along with 
 *the post(object_id) when the post is published or updated.
 */

	function cp_update_post_contributors( $post ) {
		
		global $post;
		
		$post_id = $post->ID;		
			
		// Auto save check  
   	if( defined ( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
   	
   	// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) return $post_id; 
     
    	// Get contributors  
  		if( isset( $_POST[ 'contributors-nonce' ] ) ) {
			
			check_admin_referer( 'contributors-list', 'contributors-nonce' );   

   		$contributors_term_id = ( array ) $_POST[ 'contributors' ];
			return $this->cp_add_contributors( $post_id, $contributors_term_id );
			}
		}

//Called from 'update_post_contributors()'
	function cp_add_contributors( $post_id, $contributors_term_id, $append = false ) {

		foreach( $contributors_term_id as $key => $term_id ) {	
			$terms = get_term_by( 'term_id', $term_id, $this->cont_taxonomy );
			$term_slugs[] = $terms->slug;
		}	
		
		//Insert or update wp_term_relationships table with post_id(object_id) and contributors(term_taxonomy_id).	
		wp_set_post_terms( $post_id, $term_slugs, $this->cont_taxonomy, $append );

	}

/**
 *update wp_term_taxonomy.count based on number of contributions by specific users(terms)
 */
 
	function _update_post_term_count( $terms, $taxonomy ) {
	
		global $wpdb;

		foreach ( (array) $terms as $term ) {
			
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );
			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
			
			}
		}
		
		
/**
 * 'wp_ajax_contributors_ajax_suggest' searches users as you type
 */
	
	function cp_contributors_ajax_suggest() {
		
		global $wpdb;
	
		// If search term exists
		if ( $search_term = ( isset( $_POST[ 'author_search_term' ] ) && ! empty( $_POST[ 'author_search_term' ] ) ) ? $_POST[ 'author_search_term' ] : NULL ) {
		
		$users = $wpdb->get_results( "SELECT * FROM $wpdb->users  WHERE (user_login LIKE '%$search_term%' OR display_name LIKE '%$search_term%' OR user_email LIKE '%$search_term%' ) ORDER BY display_name" );			
		
		if ( $users && is_array( $users ) ) {
			
			// Build the autocomplete results
			$results = array();
			
			foreach ( $users as $user ) {			
		 	
				$terms = get_term_by( 'name', $user->user_login, $this->cont_taxonomy );
				
				$results[] = array(
					
					'user_id'		=> $user->ID,
					'user_login'	=> $user->user_login,
					'display_name'	=> $user->display_name,
					'email'			=> $user->user_email,
					'term_id'      => $terms->term_id  

					);
					}						
				
				// "return" the results
				echo json_encode( $results);
				
				}else {
					
					$results = array();
					
					$results[] = array(
					'no_result' => 'no such User found!'			
					);
					
					echo json_encode( $results );
					
					}
				die();
			}
		die();
		
		}

/**
 * Include js  scripts
 */
	
	function cp_contributors_scripts() {
		
		global $post;
		
		//Variables for js 
		$js_var_array = array(
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'current_post_author' => $post->post_author,
			'post_author_text'	 => " is the author of this post. \n Enable display author from Contributors Settings Page \n to display post author.",
			'user_present_text'	 => "This user is already selected!",							
			
			);

		wp_enqueue_script( 'contributors_js', plugin_dir_url( __FILE__ ).'js/contributors.js', array( 'jquery', 'jquery-ui-autocomplete' ), '', true );
		wp_localize_script( 'contributors_js', 'cp_js_vars', $js_var_array );
		
		}

}//class 'contributors_plugin' ends here

	global $contributors_plugin;
	$contributors_plugin = new Contributors_Plugin();


/**
 * function 'add_contributors_box' to handle the frontend 'Contributors Box'. 
 *'the_content' filter handles the display of 'Contributors Box' on the posts along with selected contributors. 
 */

	add_filter( 'the_content', 'cp_add_contributors_box' );
	function cp_add_contributors_box( $content ) {
		
		if( is_singular( 'post' ) ) {
				
			global $post;
			global $wpdb;
			$post_id = $post->ID;
			
			$cb_data  = "<div class='contributors-box'>";
			$cb_data .= "<div class='cb-heading'> <b> Contributors Box </b> </div> ";
			$cb_data .= "<div class='cb-contri'> <p>";
			
			$author_id = $post->post_author;
			$author = get_userdata($author_id);

			//displays original author(the one who created the post) along with his/her gravatar if it checked from settings page
			$set_options = get_option( 'plugin_options' );			
			
			if( $set_options[ 'author_option' ] == 'yes' ) {
				
				$cb_data .= '<span class="avatar">'.get_avatar( $author->user_email, 35 ).'</span>';
				$cb_data .= '<span class="author_page_link"> <a href="' . get_author_posts_url( $author->ID ) . '">' . $author->display_name .'</a>[author]. </span>';
			
				}
			
			//get the terms belonging to 'contributors' taxonomy on specific posts(object_id) from wp_term_relationship table
			$terms = wp_get_post_terms( $post_id, 'contributors' );
				
			//loops 'n' number of times, where 'n' is a number of 
			//contributors (terms belonging to 'contributors' taxonomy) on the post(object_id).  
			foreach( $terms as $keys => $values ) {
				
				$user_query = new WP_User_Query( array( 'search' => $values->name ) );
			
			//displays selected contributors along with its gravatar.
				foreach ( $user_query->results as $user ) {
			
					$cb_data .= '<span class="avatar">'.get_avatar( $user->user_email, 35 ).'</span>';
					$cb_data .= '<span class="author_page_link"> <a href="' . get_author_posts_url( $user->ID ) . '">' . $user->display_name .'</a>. </span>';

					}			
				}
				
				if( count( $terms ) == 0 && $set_options[ 'author_option' ] == 'no' ) {
					
					$cb_data .="<b>NO CONTRIBUTORS SELECTED AND DISPLAY ORIGINAL AUTHOR IS OFF</b>";
								
				}
			
			$cb_data .="</div></p></div>";
			
			//attach contributors box to content as per selected option for it from settings page
			$set_options = get_option( 'plugin_options' );
			
			if($set_options[ 'cbox_option' ] == 'above' )
				return "$cb_data".$content;	//above the content	
				
				return $content."$cb_data"; //below the content
								
			} else {
	
				return $content;	//if not single post, don't display contributors box
			}
		}

/**
 * Include css for Contributors Box
 */
	add_action( 'wp_enqueue_scripts', 'cp_contributors_box_styles' );
	
	function cp_contributors_box_styles() {
		
		$css_url = plugins_url( 'css/contributors.css', __FILE__ );
		wp_register_style( 'contributors_css', $css_url, '', '1.0' );
		wp_enqueue_style( 'contributors_css' );
	
	}


/**
*Add settings link on plugin page
*/	
	
	function cp_contributors_plugin_settings_link ($links ) { 

  		$settings_link = '<a href="options-general.php?page=contributors-plugin/includes/settings.php">Settings</a>'; 
  		array_unshift( $links, $settings_link ); 
  		return $links; 

		}
 	$plugin = plugin_basename( __FILE__ ); 
	add_filter( "plugin_action_links_$plugin", 'cp_contributors_plugin_settings_link' );
?>