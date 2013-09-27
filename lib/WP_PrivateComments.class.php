<?php
	/**
	 * 
	 * @package default
	 */
	class WP_PrivateComments {

		protected static $_instance;
		protected $_comment_counts = array();


		const FIELD_PREFIX = 'wp-private-comment-';
		const VISIBILITY_EVERYONE = '';
		const VISIBILITY_POST_AUTHOR = 1;
		const VISIBILITY_COMMENT_AUTHOR = 2;

		protected function __clone() {}


		/**
		 * Get a single instance of the WP_PrivateComments class
		 * @return WP_PrivateComments
		 */
		public static function getInstance(){
			if(!self::$_instance){
				self::$_instance = new WP_PrivateComments();
			}
			else{
				return self::$_instance;
			}
		}

		/**
		 * Initialize the WP_PrivateComments object by binding necessary actions and filters
		 */
		protected function __construct(){

			// Bind actions that will server for saving the visibility preferences
			add_action( 'comment_post', array($this, 'save_visibility_fields') );
			add_action( 'edit_comment', array($this, 'save_visibility_fields') );
			 
			// Bind filters that will server for loading the visibility fields into the necessary forms
			add_filter( 'comment_form_default_fields', array($this, 'comment_form_default_fields') );
			add_filter( 'comment_form_logged_in', array($this, 'comment_form_logged_in') );
			add_filter( 'comments_array', array($this, 'comments_array'), 10, 2 );

			// Bind filter that will override the comment count shown in themes
			add_filter( 'get_comments_number', array($this, 'get_comments_number'), 10, 2 );
			
			// Bind action that will add the visibility settings to the metaboxes area of the edit comment page
			add_action( 'add_meta_boxes', array($this, 'add_meta_boxes') );

			// Bind to the admin menu so that we can have an options page
			add_action( 'admin_menu', array($this, 'admin_menu') );

			// Bind to wp in order to remove hidden comments from feeds and other pages that might include comments on them
			add_action( 'wp', array($this, 'filter_wp_query') );
		}

		/**
		 * Remove comments frorm the $wp_query object
		 * @param object $wp 
		 */
		function filter_wp_query($wp){
			global $wp_query;
			
			if(is_array($wp_query->comments) && count($wp_query->comments) > 0){
				//Filter the comments array in $wp_query if it exists
				$wp_query->comments = $this->comments_array($wp_query->comments);

				//Update the comment count just incase comments are removed during the filter
				$wp_query->comment_count = count($wp_query->comments);
			}
		}

		/**
		 * Register all the hooks for creating our options page
		 */
		function admin_menu(){
			add_options_page( 'WP Private Comments', 'WP Private Comments', 'activate_plugins', 'wp-priviate-comments' , array($this, 'add_options_page') );
			
			add_settings_section( 'wp-priviate-comments-defaults', __('Settings'), array($this, 'render_settings_section') , 'wp-priviate-comments' );


			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-visibility-default');
			$default_visibility_settings = array(
				array('type' => 'select', 'name' => 'wp-priviate-comments-visibility-default', 'values' => $this->get_visibility_values()),
			);
			add_settings_field( 'wp-priviate-comments-visibility-defaults', __('Visibility Default'), array($this, 'render_setting_fields'), 'wp-priviate-comments', 'wp-priviate-comments-defaults', $default_visibility_settings);

			
			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-show-comment-settings', 'intval' );
			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-remove-comments', 'intval' );
			$admin_default_settings = array(
				array('type' => 'checkbox', 'name' => 'wp-priviate-comments-show-comment-settings', 'default' => 'Show visibility settings to users'),
				array('type' => 'checkbox', 'name' => 'wp-priviate-comments-remove-comments', 'default' => 'Remove hidden comments'),
			);						
			add_settings_field( 'wp-priviate-comments-user-defaults', __('User Defaults'), array($this, 'render_setting_fields'), 'wp-priviate-comments', 'wp-priviate-comments-defaults', $admin_default_settings);

		}

		/**
		 * Create our options page
		 */
		function add_options_page(){
			
			?>
			<div class="wrap">
				<?php screen_icon(); ?><h2><?php _e('WP Private Comments') ?></h2>
				<form method="post" action="options.php">
					<?php settings_fields('wp-priviate-comments'); ?>
					<?php do_settings_sections('wp-priviate-comments'); ?>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Add the settings table for our options page
		 */
		function render_settings_section($args){
			// Do nothing
		}

		/**
		 * Add the fields to our settings table
		 */
		function render_setting_fields($options){	
			?>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e($args['label']); ?></span></legend>
					<?php foreach($options as $option): ?>
					<?php 		if ($option['type'] == 'checkbox'): ?>
					<label for="<?php echo $option['name'] ?>"><input type="checkbox" name="<?php echo $option['name'] ?>" id="<?php echo $option['name'] ?>" value="1" <?php checked('1', get_option($option['name'])); ?> /> <?php _e($option['default']); ?></label><br>
					<?php 		elseif ($option['type'] == 'select'): ?>
					<select name="<?php echo $option['name'] ?>" id="<?php echo $option['name'] ?>">
					<?php
									$selected_value = get_option($option['name']);
									foreach($option['values'] as $title => $value){
										if($selected_value == $value){
											echo '<option value="' . esc_html($value) . '" selected>' . esc_html__($title);
										}
										else{
											echo '<option value="' . esc_html($value) . '">' . esc_html__($title);
										}
									}
					?>
					</select><br>
					<?php 		endif; ?>
					<?php endforeach;?>
				</fieldset>
			<?php
		}

		/**
		 * Add our meta box to the comments page
		 */
		function add_meta_boxes(){
			add_meta_box( 'wp-private-comment', __( 'Comment Visibility' ), array($this, 'comment_meta_box'), 'comment', 'normal');
		}

		/**
		 * Display our fields in the meta box
		 * @param object $comment 
		 */
		function comment_meta_box( $comment){
			$fields = $this->get_fields($comment->comment_ID);

			foreach ( $fields as $field ) {
				echo $field['html'];
			}

			echo $this->get_nonce();
		}

		/**
		 * Get posible values for the visibility field
		 * @return array
		 */
		function get_visibility_values(){
			return apply_filters('WP_PrivateComments::get_visibility_values', array(
				'Everyone' => self::VISIBILITY_EVERYONE,
				'Post author' => self::VISIBILITY_POST_AUTHOR,
				'Comment author' => self::VISIBILITY_COMMENT_AUTHOR,
			));
		}

		/**
		 * Get the fields that will be placed on a comment form
		 * @return array
		 */
		function get_fields($comment_id = null){
			
			$visibility_values = $this->get_visibility_values();

			$default_visibility_value = self::VISIBILITY_EVERYONE;

			$selected_visibility_value = ($comment_id) ? get_comment_meta($comment_id , self::FIELD_PREFIX . 'visibility', true) : $default_visibility_value;

			$options = '';
			foreach($visibility_values as $visibility_value_title => $visibility_value){
				if($selected_visibility_value == $visibility_value){
					$options .= '<option value="' . esc_html($visibility_value) . '" selected>' . esc_html__($visibility_value_title);
				}
				else{
					$options .= '<option value="' . esc_html($visibility_value) . '">' . esc_html__($visibility_value_title);
				}
			}

			return apply_filters('WP_PrivateComments::get_fields', array(
				'visibility' => array(
									'default' => $default_visibility_value,
									'html' => '<p class="comment-form-visibility"><label for="visibility" style="padding-right:15px">' . __( 'Visibility' ) . '</label><select id="visibility" name="visibility"/>' . $options . '</select>'
								)
			), $comment_id);
		}

		/**
		 * Get the adjusted comment count for a post
		 * @param int $count 
		 * @param int $post_id 
		 * @return int
		 */
		function get_comments_number($count, $post_id){
			if(isset($this->_comment_counts[$post_id]))
				return $this->_comment_counts[$post_id];
			else
				return $count;
		}

		/**
		 * Filter an array of comment objects and remove comments that should not be visible for the logged in user
		 * @param array $comments 
		 * @return array
		 */
		function comments_array($comments, $post_id = null){
			global $wpdb, $current_user;

			$filtered_comments = array();
			
			$comment_ids = array();
			
			foreach($comments as $comment){
				$comment_ids[] = intval($comment->comment_ID);
			}

			// Create a list of the comment ids for use in SQL
			$comment_ids = implode(',', $comment_ids);

			// Get the currently logged in user
			$current_user_id = intval($current_user->ID);

			// Get the commenter cookie info just incase the user is an Anonymous user who isn't logged in.
			$commenter = wp_get_current_commenter();
			$comment_author = $commenter['comment_author'];
			$comment_author_email = $commenter['comment_author_email'];

			// Get the visibility settings values for use in the SQL query below
			$visibility_post_author = intval(self::VISIBILITY_POST_AUTHOR);
			$visibility_comment_author = intval(self::VISIBILITY_COMMENT_AUTHOR);

			// Generate the SQL that will return comments that should be hidden out of the list that is provided
			if($current_user_id == 0){
				/* 
				 * The query for the logged out user doesn't need to join on the posts table because it doesn't need to check if you are the post 
				 * author. It assumes your not since you aren't logged in.
				 */
				$sql = $wpdb->prepare("
					select comment.comment_ID 
						from {$wpdb->comments} comment
							inner join {$wpdb->commentmeta} meta on meta.comment_id = comment.comment_ID and meta.meta_key = %s
							left outer join {$wpdb->comments} comment_parent on comment_parent.comment_ID = comment.comment_parent
					where comment.comment_ID in ({$comment_ids})
						and !(comment.comment_author = %s and comment.comment_author_email = %s)
						and ( 
							(meta.meta_value = '{$visibility_post_author}' ) 
							|| (meta.meta_value = '{$visibility_comment_author}' and comment_parent.user_id != {$current_user_id})
							|| (meta.meta_value = '{$visibility_comment_author}' and !(comment_parent.comment_author = %s && comment_parent.comment_author_email = %s) ) 
						)", self::FIELD_PREFIX . 'visibility', wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email, wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email);
			}
			else{
				// Same SQL as above except it checks the post author field to see if it is the currently logged in user
				$sql = $wpdb->prepare("
					select comment.comment_ID 
						from {$wpdb->comments} comment
							inner join {$wpdb->commentmeta} meta on meta.comment_id = comment.comment_ID and meta.meta_key = %s
							inner join {$wpdb->posts} post on post.ID = comment.comment_post_ID 
							left outer join {$wpdb->comments} comment_parent on comment_parent.comment_ID = comment.comment_parent
					where comment.comment_ID in ({$comment_ids})
						and post.post_author != {$current_user_id} 
						and comment.user_id != {$current_user_id}
						and !(comment.comment_author = %s and comment.comment_author_email = %s)
						and ( 
							(meta.meta_value = '{$visibility_post_author}' ) 
							|| (meta.meta_value = '{$visibility_comment_author}' and comment_parent.user_id != {$current_user_id})
							|| (meta.meta_value = '{$visibility_comment_author}' and !(comment_parent.comment_author = %s && comment_parent.comment_author_email = %s) ) 
						)", self::FIELD_PREFIX . 'visibility', wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email, wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email);
			}
			
			$remove_hidden_comments = get_option('wp-priviate-comments-remove-comments') == '1';

			// Use the query and get the comment ids that should be removed
			$removed_comments = $wpdb->get_col($sql);

			// Start removing comments along with their children
			while(count($removed_comments) > 0){
				$comment_id_to_remove = array_pop($removed_comments);
				
				foreach($comments as $key => $comment){
					if($comment->comment_ID == $comment_id_to_remove){
						//Handle a private comment
						if($remove_hidden_comments){
							//Remove comment from the array
							unset($comments[$key]);
						}
						else{
							//Change the comment text to a message
							$comments[$key]->comment_content = apply_filters('WP_PrivateComments::blankComment', '<i>This is a private comment.</i>', $comments[$key]);
						}
					}
					else if($comment->comment_parent == $comment_id_to_remove){
						// Add child comment to the list of comments to remove so that you don't have orphaned comments
						array_push($removed_comments, $comment->comment_ID);
					}
				}
			}

			if($post_id){
				// Cache the comment count for later use 
				$this->_comment_counts[$post_id] = count($comments);
			}

			//Normalize the array keys since some might have been removed and return the altered list of comments
			return array_values($comments);
		}

		/**
		 * Get nonce fields for use when saving data
		 * @return string
		 */
		function get_nonce(){
			return wp_nonce_field('wp-private-comments-savedata', self::FIELD_PREFIX . 'NONCE', true, false);
		}

		/**
		 * Verify nonce post fields
		 * @return boolean
		 */
		function verify_nonce(){
			return wp_verify_nonce($_POST[self::FIELD_PREFIX . 'NONCE'], 'wp-private-comments-savedata');
		}

		/**
		 * Append our fields to the $logged_in_as html that is output above the comment form
		 * @param string $logged_in_as 
		 * @return string
		 */
		function comment_form_logged_in($logged_in_as) {
			$fields = $this->get_fields();

			foreach ( $fields as $name => $field ) {
				$logged_in_as .= apply_filters( "comment_form_field_{$name}", $field['html'] ) . "\n";
			}

			$logged_in_as .= $this->get_nonce();

			return $logged_in_as;
		}

		/**
		 * Append our fields to the fields that wordpress shows on the comment form
		 * @param array $fields 
		 * @return array
		 */
		function comment_form_default_fields( $fields ){
			$visibility_fields = $this->get_fields();
			foreach($visibility_fields as $name => $visibility_field){
				$fields[$name] = $visibility_field['html'];
			}
			return array_merge($fields, $visibility_fields);
		}

		/**
		 * Save the fields that were added to the comment form as meta data for later use
		 * @param int $comment_id 
		 */
		function save_visibility_fields( $comment_id ) {

			// a nonce value is always required so verify it here
			if(!$this->verify_nonce()){
				return;
			}

			$field_names = array_keys($this->get_fields());

			foreach($field_names as $field_name){
				// Delete the meta data since you don't want blank values
				delete_comment_meta( $comment_id, self::FIELD_PREFIX . $field_name );

				//Only save the meta data if it is not blank
				if(isset($_POST[$field_name]) && !empty($_POST[$field_name])){
					add_comment_meta( $comment_id, self::FIELD_PREFIX . $field_name, $_POST[$field_name] );
				}
			}
		}
	}