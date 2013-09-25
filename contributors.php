<?php
/**
 * @package contributors_plugin
 * @version 1.0
 */
/*
Plugin Name: ContributorsPlugin
Plugin URI: https://github.com/prasaddeshmukh/wp-contributors-pluign
Description: A plugin which allows users(admin/editor/author) to select authors(wordpress users) as a contributors for a post and displays them in a box named 'contributors box' on the respective post.
Author: PrasadBDeshmkh
Version: 1.0
Author URI: http://stackoverflow/users/2125917/bhau
License: GPLv3.
*/

/**
 *Includes Settings page 
 */
 
define('CONTRIBUTORS_PLUGIN_DIR',plugin_dir_path(__FILE__)); 
define('CONTRIBUTORS_PLUGIN_URL',plugin_dir_url(__FILE__));

	function contributors_plugin_load(){
			
		if(is_admin())
			require_once(CONTRIBUTORS_PLUGIN_DIR.'includes/settings.php');
		
		}
		
	contributors_plugin_load();

/**
 *class contributors_plugin to handle the backend(admin) side.
 */
 
class contributors_plugin{
	var $cont_taxonomy = 'contributors';
	
	function __construct(){

			//first to execute. registers 'contributors' taxonomy and creates terms with name 'user_login'
			add_action('init',array($this,'add_contributors_taxonomy'));
				
			//adds meta box on post edit screen 	
			add_action( 'add_meta_boxes', array($this,'contributors_box' ));
			 
			//triggers when the post is published or updated 
			add_action('save_post',array($this,'update_post_contributors')); 
		
			}
	
	function contributors_plugin(){

		$this->__construct();

			}

/**
 * 'init' hook
 * registering 'contributors' taxonomy and create/insert terms 
 */
	
	function add_contributors_taxonomy() {
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
		register_taxonomy($this->cont_taxonomy,'post',$args);

		//insert or update terms for 'contributors' taxonomy on the post type
		$result = count_users();
		$total_users = $result['total_users'];
		
		for($user_id = 1; $user_id <= $total_users; $user_id++) {
			$user = get_userdata($user_id);		 
			$userlogin_for_term = $user->user_login;
			$userid_for_term = $user->ID;		
			$slug_term = 'contri-'.$userlogin_for_term;
		
			$term_exists = term_exists($userlogin_for_term, $this->cont_taxonomy); //will return 0 if no such terms present.
			
			if($term_exists == 0 && $term_exists == null){ //if its 0. means term is not present so, add it.

				wp_insert_term(
  					$userlogin_for_term, // the term 
  					$this->cont_taxonomy, // the taxonomy
  					array(
    					'description'=> $userid_for_term,
    					'slug' => $slug_term
    					));
    			}else{
				
					wp_update_term(
  						$term_exists['term_id'], // the term_id to update 
  						$this->cont_taxonomy, // the taxonomy
  						array(
    						'description'=> $userid_for_term,
    						'slug' => $slug_term
    						));
						}//else		

			}//for loop

	}//registering 'contributors' taxonomy and create and insert terms

/**
 *'add_meta_boxes' hook
 * Adds a meta box on the Post edit screens.
 */

	function contributors_box() {
		add_meta_box(
			'contributors-box-id', 'Contributors', array($this,'user_names_with_checkboxes'), 'post', 'side', 'high' 			  
      );
      }

/**
 * callback 'user_names_with_checkboxes' from add_meta_box
 */
	
	function user_names_with_checkboxes($post) {
		global $post;
		
		$post_id = $post->ID;		
		
		$original_author_id = $post->post_author;
		$orginal_author  = get_userdata($original_author_id);		
		
		
		$currently_logged_user = get_current_user_id(); 
		
		$result = count_users();
		$total_users = $result['total_users'];
		 
		//check option for original author display and execute the respective code
		$set_options = get_option('plugin_options');			
		if($set_options['author_option'] == 'yes'){		
			echo 'Original Author: '.$orginal_author->display_name;
			}
		
		//print users(role:roles) on the meta box with checkboxes
		?>
		<input type="text"/>
		<?php
		for($user_id = 1; $user_id <= $total_users; $user_id++) {
		
			$user = get_userdata($user_id);		 
			$username = $user->user_login;
			$display_name = $user->display_name;
			
			//to print user role along with user display name
			$role = wp_sprintf_l( '%l', $user->roles ); 
			
			//get the term_taxonomy_ids from wp_term_relationships table for current 
			//post(represented in this table by field object_id).
			$post_contributor_tt_id = wp_get_post_terms($post_id, $this->cont_taxonomy);
			
			//original author of the post and currently logged user will not be displayed on the meta box
			if($original_author_id != $user_id && $currently_logged_user != $user_id){ 
		
				$term = get_term_by('name',$username,$this->cont_taxonomy);
				$term_id = $term->term_id;  //for value's value
	?>
		
				<div id="contributors-list">
					<p>
						
						<label> 
						<input id="my_check" type="checkbox" name="contributors[]" value="<?php echo $term_id;?>" 			
						<?php
							
							//specific checkboxes will be 'on' if term_id matches the 
							//term_taxonomy_id present in wp_term_relationships table
					
							foreach($post_contributor_tt_id as $key => $tt_id){
								checked($term_id, $tt_id->term_taxonomy_id);
								if($term_id == $values->term_taxonomy_id) break;
								}	
							?>
						/> <!--input type checkboxes closed-->
		
				<?php echo $display_name." ( role: ".wp_sprintf_l( '%l', $user->roles )." )";?></label>
   				</p> 
   			</div>			
   			<?php 
	   			wp_nonce_field('contributors-list','contributors-nonce'); //nonce hidden filed
   			?>
   
	<?php
			}
		}
	}//callback 'user_names_with_checkboxes' from add_meta_box


/**
 *'save_post' hook
 *update wp_term_relationships table by inserting term_taxonomy_ids along with 
 *the post(object_id) when the post is published or 
 *updated.
*/

	function update_post_contributors($post) {
		
		global $post;
		
		$post_id = $post->ID;		
			
		// Bail if we're doing an auto save  
   	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
     
    	// if our nonce isn't there, or we can't verify it, bail   
  		if( isset( $_POST['contributors-nonce'] )) {
			
			check_admin_referer( 'contributors-list', 'contributors-nonce' );   

   		$contributors_term_id = (array) $_POST['contributors'];
			return $this->add_contributors($post_id, $contributors_term_id);
			}
		}

//called from 'update_post_contributors()'
	function add_contributors($post_id, $contributors_term_id, $append = false) {

		foreach($contributors_term_id as $key => $term_id){	
			$terms = get_term_by('term_id',$term_id,$this->cont_taxonomy);
			$term_slugs[] = $terms->slug;
		}	
		
		//insert or update wp_term_relationships table with post_id(object_id) and contributors(term_taxonomy_id).	
		wp_set_post_terms($post_id, $term_slugs, $this->cont_taxonomy, $append);

	}

/**
 *update wp_term_taxonomy.count based on number of contributions by specific users(terms)
 */
 
	function _update_post_term_count($terms, $taxonomy){
	
		global $wpdb;

		foreach ( (array) $terms as $term ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );
			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
			}
		}

}//class 'contributors_plugin' ends here

	global $contributors_plugin;
	$contributors_plugin = new contributors_plugin();


/**
 * function 'add_contributors_box' to handle the frontend 'Contributors Box'. 
 *'the_content' filter handles the display of 'Contributors Box' on the posts along with selected contributors. 
 */

	add_filter('the_content', 'add_contributors_box');
	function add_contributors_box( $content ) {
		
		if(is_singular('post')) {
				
			global $post;
			global $wpdb;
			global $set_options;
			$post_id = $post->ID;
			
			$cb_data = "<div class='contributors-box'>";
			$cb_data .= "<div class='cb-heading'><b>Contributors Box</b></div><div class='cb-contri'><p>";
			
			$author_id = $post->post_author;
			$author = get_userdata($author_id);

			//$last_revision_by = get_the_modified_author();
			 
			//displays original author(the one who created the post) along with his/her gravatar if it checked from settings page
			$set_options = get_option('plugin_options');			
			
			if($set_options['author_option'] == 'yes'){
				
				$cb_data .= get_avatar($author->user_email, 35);
				$cb_data .= "<a href= '?author=$author_id'>".$author->display_name."</a>(author) ";
				
				}
			
			//get the terms belonging to 'contributors' taxonomy on specific posts(object_id) from wp_term_relationship table
			$terms = wp_get_post_terms($post_id, 'contributors');
				
			//loops 'n' number of times, where 'n' is a number of 
			//contributors (terms belonging to 'contributors' taxonomy) on the post(object_id).  
			foreach($terms as $keys => $values){
				
				$user_query = new WP_User_Query(array('search' => $values->name));
			
			//displays selected contributors along with its gravatar.
				foreach ($user_query->results as $user) {
			
					$cb_data .= get_avatar($user->user_email, 35);
					$cb_data .= "<a href= '?author=$user->ID'>".$user->display_name."</a>\n";

					}			
				}
				
				if(count($terms) == 0 && $set_options['author_option'] == 'no'){
					$cb_data .="<b>NO CONTRIBUTORS SELECTED AND DISPLAY ORIGINAL AUTHOR IF OFF</b>";			
				}
			
			$cb_data .="</div></p></div>";
			
			//attach contributors box to content as per selected option for it from settings page
			$set_options = get_option('plugin_options');
			if($set_options['cbox_option'] == 'above')
				return "$cb_data".$content;	//above the content	
				
				return $content."$cb_data"; //below the content
								
			}else {
	
				return $content;	//if not single post, don't display contributors box
			}
	}

/**
 * include css for Contributors Box
 */
	add_action('wp_enqueue_scripts', 'contributors_box_styles');
	function contributors_box_styles() {
		$css_url = plugins_url('css/contributors.css', __FILE__);
		wp_register_style( 'contributors_css', $css_url, '', '1.0');
		wp_enqueue_style( 'contributors_css' );
	}


/**
*Add settings link on plugin page
*/	

	function contributors_plugin_settings_link($links) { 
  		$settings_link = '<a href="options-general.php?page=contributors-plugin/includes/settings.php">Settings</a>'; 
  		array_unshift($links, $settings_link); 
  		return $links; 
		}
 
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", 'contributors_plugin_settings_link' );

?>