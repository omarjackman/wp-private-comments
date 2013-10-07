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

			// Bind to the save_post action so we can set the default visibility for future comments of the saved post
			add_action( 'save_post', array($this, 'save_visibility_fields_for_post') );
			 
			// Bind filters that will server for loading the visibility fields into the necessary forms
			add_filter( 'comment_form_default_fields', array($this, 'comment_form_default_fields') );
			add_filter( 'comment_form_logged_in', array($this, 'comment_form_logged_in') );

			// Bind filters that will filter out comments that shouldn't be visible for the current user
			add_filter( 'comments_array', array($this, 'comments_array'), 10, 2 );
			add_filter( 'the_comments', array($this, 'the_comments'), 10, 2 );

			// Bind to wp in order to remove hidden comments from feeds and other pages that might include comments on them
			add_action( 'wp', array($this, 'filter_wp_query') );

			// Bind to the comment reply filter so that users cant reply to comments that are private
			add_filter( 'comment_reply_link', array($this, 'comment_reply_link'), 10, 4 );

			// Bind filter that will override the comment count shown in themes
			add_filter( 'get_comments_number', array($this, 'get_comments_number'), 10, 2 );
			
			// Bind action that will add the visibility settings to the metaboxes area of the edit comment page
			add_action( 'add_meta_boxes', array($this, 'add_meta_boxes') );

			// Bind to the admin menu so that we can have an options page
			add_action( 'admin_menu', array($this, 'admin_menu') );

			// Bind to the admin_init action so that we can do a check for jetpack
			add_action( 'admin_init', array($this, 'jetpack_comments_check') );

			// Bind to the admin_init action so that we can do a check for intense debate
			add_action( 'admin_init', array($this, 'intensedebate_check') );
		}

		/**
		 * Checks to see if Jetpacks comments is enabled
		 * @return boolean
		 */
		function jetpack_comments_enabled(){
			return class_exists('Jetpack') && in_array('comments', Jetpack::get_active_modules());
		}

		/**
		 * Checks to see if Jetpacks comments is enabled
		 * @return boolean
		 */
		function intensedebate_enabled(){
			return defined('ID_PLUGIN_VERSION');
		}

		/**
		 * Check if Jetpack comments are enabled an show compatibility warnings if it is
		 */
		function jetpack_comments_check(){
			// Check to see if the jetpack comments module is enabled and the show visibility settings to users option is checked
			if ( $this->jetpack_comments_enabled() && get_option('wp-priviate-comments-show-visbility-settings') == '1' ) {
				// The comments module is enabled and the show visibility settings to users option is checked so show the user a warning
				add_action( 'admin_notices', array($this, 'jetpack_comments_notice') );
			}
		}

		/**
		 * Check if the intense debate plugin is enabled and give a warning if it is
		 */
		function intensedebate_check(){
			// Check to see if the intense debate plugin is enable
			if ( $this->intensedebate_enabled() ) {
				// The plugin is enabled so show the user a warning
				add_action( 'admin_notices', array($this, 'intensedebate_notice') );
			}
		}

		/**
		 * Show the user a warning about the incompatibility with Jetpack Comments
		 * @return type
		 */
		function jetpack_comments_notice() {
			?>
			<div class="error">
				<p>
					The WP Private Comments plugin "Show visibility settings to users" option is currently incompatible with Jetpack Comments.
					<br>The default you've set will be applied to all future comments
					<br>You can modify your settings <a href="<?php echo get_admin_url(null, 'options-general.php?page=wp-priviate-comments'); ?>">here</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Show the user a warning about the incompatibility with Jetpack Comments
		 * @return type
		 */
		function intensedebate_notice() {
			?>
			<div class="error">
				<p>
					The WP Private Comments plugin is currently incompatible with the intense debate plugin.
				</p>
			</div>
			<?php
		}

		/**
		 * Register all the hooks for creating our options page
		 */
		function admin_menu(){
			add_options_page( 'WP Private Comments', 'WP Private Comments', 'activate_plugins', 'wp-priviate-comments' , array($this, 'add_options_page') );
			
			add_settings_section( 'wp-priviate-comments-settings-section', '', array($this, 'render_settings_section') , 'wp-priviate-comments' );


			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-visibility-default');
			$default_visibility_settings = array(
				array('type' => 'select', 'name' => 'wp-priviate-comments-visibility-default', 'values' => $this->get_visibility_values(), 'description' => 'Select the default visibility setting for comments on posts and replies to comments.<BR>Note: This can also be overriden for each post'),
			);
			add_settings_field( 'wp-priviate-comments-visibility-defaults', __('Visibility Default'), array($this, 'render_setting_fields'), 'wp-priviate-comments', 'wp-priviate-comments-settings-section', $default_visibility_settings);

			
			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-show-visbility-settings', 'intval' );
			register_setting( 'wp-priviate-comments', 'wp-priviate-comments-remove-comments', 'intval' );
			$admin_default_settings = array(
				array('type' => 'checkbox', 'name' => 'wp-priviate-comments-show-visbility-settings', 'default' => 'Show visibility settings to users'),
				array('type' => 'checkbox', 'name' => 'wp-priviate-comments-remove-comments', 'default' => 'Remove hidden comments'),
			);						
			add_settings_field( 'wp-priviate-comments-settings', __('Settings'), array($this, 'render_setting_fields'), 'wp-priviate-comments', 'wp-priviate-comments-settings-section', $admin_default_settings);

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
					<?php 		if(isset($option['description'])):?>
					<p class="description"><?php echo $option['description'] ?></p>
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
			add_meta_box( 'wp-private-comment', __( 'Comment Visibility' ), array($this, 'post_meta_box'), 'post', 'normal');
		}

		/**
		 * Display our fields in the comment meta box
		 * @param object $comment 
		 */
		function comment_meta_box( $comment ){
			echo $this->get_field_html( get_comment_meta($comment->comment_ID, self::FIELD_PREFIX . 'visibility', true) );
			echo $this->get_nonce();
		}

		/**
		 * Display our fields in the comment meta box
		 * @param object $comment 
		 */
		function post_meta_box( $post ){
			// Override the get_visibility_values function so that we can add an extra value which will represent the blog default
			add_filter( 'WP_PrivateComments::get_visibility_values', array($this, 'get_visibility_values_for_post') );
			
			$default_visibility = get_post_meta($post->ID, self::FIELD_PREFIX . 'visibility', true);

			echo $this->get_field_html( $default_visibility ? $default_visibility : "-1");
			echo $this->get_nonce();

			// remove the filter we added before since it was only for one time use above
			remove_filter( 'WP_PrivateComments::get_visibility_values', array($this, 'get_visibility_values_for_post') );
		}

		/**
		 * Get the visibility values with an extra value added just for the post meta box
		 * @param array $values 
		 * @return array
		 */
		function get_visibility_values_for_post($values){
			return array_merge(array('-- Blog Default --' => -1), $values);
		}

		/**
		 * Get posible values for the visibility field
		 * @return array
		 */
		function get_visibility_values(){
			return apply_filters('WP_PrivateComments::get_visibility_values', array(
				'Everyone' => self::VISIBILITY_EVERYONE,
				'Post author' => self::VISIBILITY_POST_AUTHOR,
				'Post author and comment author' => self::VISIBILITY_COMMENT_AUTHOR,
			));
		}

		/**
		 * Get the default visibility value
		 * @return string
		 */
		function get_default_visiblity(){
			$default_visibility = get_option('wp-priviate-comments-visibility-default');
			if($default_visibility == null){
				$default_visibility = self::VISIBILITY_EVERYONE;
			}
			return $default_visibility;
		}

		/**
		 * Get the html for the visibility field that will be placed on a comment form
		 * @return array
		 */
		function get_field_html($selected_visibility_value = null){
			
			$visibility_values = $this->get_visibility_values();

			if( is_null($selected_visibility_value) ){
				$selected_visibility_value = $this->get_default_visiblity();
			}

			$options = '';
			foreach($visibility_values as $visibility_value_title => $visibility_value){
				if($selected_visibility_value == $visibility_value){
					$options .= '<option value="' . esc_html($visibility_value) . '" selected>' . esc_html__($visibility_value_title);
				}
				else{
					$options .= '<option value="' . esc_html($visibility_value) . '">' . esc_html__($visibility_value_title);
				}
			}

			return apply_filters('WP_PrivateComments::get_field_html', '<p class="comment-form-visibility"><label for="visibility" style="padding-right:15px">' . __( 'Visibility' ) . '</label><select id="visibility" name="visibility"/>' . $options . '</select>', $comment_id);
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
		function filter_comments($comments, $post_id = null){
			global $wpdb, $current_user;

			// Check if the logged in user can edit others posts. If they can then that also means they can edit comments belonging to those posts
			if(current_user_can('edit_others_posts')){
				// Don't filter out any comments
				return $comments;
			}


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

			// Generate the SQL that will return comments that should be inspected and filtered out if needed
			
			/* 
			 * The query for the logged out user doesn't need to join on the posts table because it doesn't need to check if you are the post 
			 * author. It assumes your not since you aren't logged in.
			 */
			$sql = $wpdb->prepare("
				select comment.comment_ID, meta.meta_value, post.post_author, comment.comment_author, comment.comment_author_email, comment.user_id, comment_parent.comment_author `parent_comment_author`, comment_parent.comment_author_email `parent_comment_author_email`, comment_parent.user_id `parent_comment_user_id`
					from {$wpdb->comments} comment
						inner join {$wpdb->commentmeta} meta on meta.comment_id = comment.comment_ID and meta.meta_key = %s
						inner join {$wpdb->posts} post on post.ID = comment.comment_post_ID
						left outer join {$wpdb->comments} comment_parent on comment_parent.comment_ID = comment.comment_parent
				where comment.comment_ID in ({$comment_ids})", self::FIELD_PREFIX . 'visibility');
			$comments_to_check = $wpdb->get_results($sql);

			$removed_comments = array();

			foreach($comments_to_check as $comment_to_check){

				$remove_the_comment = true;

				//Check if the current user is the author of the comment or the post
				if($current_user_id != 0){
					// Is the current logged in user the post author or the comment author
					if($comment_to_check->post_author == $current_user_id || $comment_to_check->user_id == $current_user_id){
						//dont hide the comment
						$remove_the_comment = false;
					}
				}
				else{
					// Is the current anonymous user the author of the comment
					if($comment_to_check->comment_author == $comment_author && $comment_to_check->comment_author_email == $comment_author_email){
						//dont hide the comment
						$remove_the_comment = false;
					}

					if($comment_to_check->meta_value == self::VISIBILITY_COMMENT_AUTHOR){

						// Is the current anonymous user the author of the parent comment
						if($comment_to_check->parent_comment_author == $comment_author && $comment_to_check->parent_comment_author_email == $comment_author_email){
							//dont hide the comment
							$remove_the_comment = false;
						}
					}
				}

				if($remove_the_comment){
					$removed_comments[] = $comment_to_check->comment_ID;
				}
			}
			
			$remove_hidden_comments = get_option('wp-priviate-comments-remove-comments') == '1';

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
							$replacements = apply_filters('WP_PrivateComments::private::replacements', array(
								'comment_content' => '<i>This is a private comment.</i>',
								'comment_author' => 'private',
								'comment_author_email' => 'private',
							), $comments[$key]);

							foreach($replacements as $replacement_key => $replacement_value){
								$comments[$key]->$replacement_key = $replacement_value;
							}
							$comments[$key]->is_private = true;
						}
					}
					else if($comment->comment_parent == $comment_id_to_remove && $remove_hidden_comments){
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
		 * Callback for the comments_array filter. This will filter out the comments that should be hidden
		 * @param array $comments 
		 * @param int $post_id 
		 * @return array
		 */
		function comments_array($comments, $post_id) {
			return $this->filter_comments($comments, $post_id);
		}

		/**
		 * Callback for the the_comments filter. This will filter out the comments that should be hidden
		 * @param array $comments 
		 * @param object $wp_comment_query 
		 * @return array
		 */
		function the_comments($comments, $wp_comment_query) {
			$post_id = ($wp_comment_query->query_vars['post_ID']) ? intval($wp_comment_query->query_vars['post_ID']) : null;
			return $this->filter_comments($comments, $post_id);
		}

		/**
		 * Remove comments frorm the $wp_query object
		 * @param object $wp 
		 */
		function filter_wp_query($wp){
			global $wp_query;
			
			if(is_array($wp_query->comments) && count($wp_query->comments) > 0){
				//Filter the comments array in $wp_query if it exists
				$wp_query->comments = $this->filter_comments($wp_query->comments);

				//Update the comment count just incase comments are removed during the filter
				$wp_query->comment_count = count($wp_query->comments);
			}
		}

		/**
		 * Filter the comment reply link so that you cannot reply to private comments
		 * @param string $link 
		 * @param array $args 
		 * @param object $comment 
		 * @param object $post 
		 * @return type
		 */
		function comment_reply_link($link, $args, $comment, $post){
			return (isset($comment->is_private) && $comment->is_private) ? false : $link;
		}

		/**
		 * Append our fields to the $logged_in_as html that is output above the comment form
		 * @param string $logged_in_as 
		 * @return string
		 */
		function comment_form_logged_in($logged_in_as) {
			global $post;

			if(get_option('wp-priviate-comments-show-visbility-settings') != '1')return $logged_in_as;

			// Get the visibility setting from the post if its been set
			if($post){
				$default_visibility = get_post_meta($post->ID, self::FIELD_PREFIX . 'visibility', true);
			}
			else{
				$default_visibility = null;
			}
			
			$logged_in_as .= apply_filters( "WP_PrivateComments::comment_form_logged_in", $this->get_field_html($default_visibility) ) . "\n";
			$logged_in_as .= $this->get_nonce();

			return $logged_in_as;
		}

		/**
		 * Append our fields to the fields that wordpress shows on the comment form
		 * @param array $fields 
		 * @return array
		 */
		function comment_form_default_fields( $fields ){
			global $post;

			if(get_option('wp-priviate-comments-show-visbility-settings') != '1'){
				return $fields;
			}

			// Get the visibility setting from the post if its been set
			if($post){
				$default_visibility = get_post_meta($post->ID, self::FIELD_PREFIX . 'visibility', true);
			}
			else{
				$default_visibility = null;
			}

			$fields['visibility'] = $this->get_field_html($default_visibility);
			return $fields;
		}

		/**
		 * Set the visibility for a comment
		 * @param int $comment_id 
		 * @param string $visibility 
		 * @return type
		 */
		function set_comment_visibility( $comment_id, $visibility = null) {

			if( is_null($visibility) ) {
				// Get the visibility setting from the post if its been set
				if($comment = get_comment($comment_id)){
					$default_visibility = get_post_meta($comment->comment_post_ID, self::FIELD_PREFIX . 'visibility', true);
				}
				else{
					$default_visibility = null;
				}

				if($default_visibility == null){
					$default_visibility = $this->get_default_visiblity();
				}

				$visibility = $default_visibility;
			}

			// Delete the meta data since you don't want blank values
			delete_comment_meta( $comment_id, self::FIELD_PREFIX . 'visibility' );

			//Only save the meta data if it is not blank
			if(!empty($visibility)){
				add_comment_meta( $comment_id, self::FIELD_PREFIX . 'visibility', sanitize_text_field($visibility) );
			}	
		}

		/**
		 * Save the visibility field that was added to the comment form as meta data for later use
		 * @param int $comment_id 
		 * @return int
		 */
		function save_visibility_fields( $comment_id ) {
			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
				return $comment_id;

			// Check to see if the user was able to override the default setting
			if(get_option('wp-priviate-comments-show-visbility-settings') == '1'){
				
				// a nonce value is always required so verify it here
				if(!$this->verify_nonce()){
					// There wasn't a valid nonce value so just use the default set by the administrator
					$this->set_comment_visibility($comment_id);
					return $comment_id;
				}

				// Use the value that was submitted by the user
				$this->set_comment_visibility($comment_id, $_POST['visibility']);
			}
			else{

				// Use the default set by the administrator
				$this->set_comment_visibility($comment_id);						
			}

			return $comment_id;
		}

		/**
		 * Save the visibility field that was added to the post form as meta data for later use when adding comments
		 * @param int $post_id 
		 * @return int
		 */
		function save_visibility_fields_for_post( $post_id ){
			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
				return $post_id;

			// a nonce value is always required so verify it here
			if(!$this->verify_nonce()){
				return $post_id;
			}

			// Check the user's permissions.
			if('page' == $_POST['post_type']){
				if(!current_user_can('edit_page', $post_id)){
					return $post_id;
				}
			}
			else {
				if(!current_user_can('edit_post', $post_id)){
					return $post_id;
				}
			}

			// Delete the meta data since you don't want blank values
			delete_post_meta( $post_id, self::FIELD_PREFIX . 'visibility' );

			//Only save the meta data if it is not blank and not set to "Blog Default"
			if(isset($_POST['visibility']) && !empty($_POST['visibility']) && $_POST['visibility'] != '-1'){
				add_post_meta( $post_id, self::FIELD_PREFIX . 'visibility', sanitize_text_field($_POST['visibility']) );
			}
		}
	}