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


		protected function __construct(){
			add_action( 'comment_post', array($this, 'comment_post') );
			add_filter( 'comment_form_default_fields', array($this, 'comment_form_default_fields') );
			add_filter( 'comment_form_logged_in', array($this, 'comment_form_logged_in') );
			add_filter( 'comments_array', array($this, 'comments_array'), 10, 2 );
			add_filter( 'get_comments_number', array($this, 'get_comments_number'), 10, 2 );
			
			//TODO: Add filter that will remove hidden comments from feeds
		}

		/**
		 * Get posible values for the visibility field
		 * @return array
		 */
		function getVisibilityValues(){
			return apply_filters('WP_PrivateComments::getVisibilityValues', array(
				'Everyone' => self::VISIBILITY_EVERYONE,
				'Post author' => self::VISIBILITY_POST_AUTHOR,
				'Comment author' => self::VISIBILITY_COMMENT_AUTHOR,
			));
		}


		/**
		 * Get the fields that will be placed on a comment form
		 * @return array
		 */
		function getFields(){
			
			$visibility_values = $this->getVisibilityValues();

			$options = '';
			foreach($visibility_values as $visibility_value_title => $visibility_value){
				$options .= '<option value="' . esc_html($visibility_value) . '">' . esc_html__($visibility_value_title);
			}

			return apply_filters('WP_PrivateComments::getFields', array(
				'visibility' => '<p class="comment-form-visibility"><label for="visibility">' . __( 'Visibility' ) . '</label><select id="visibility" name="visibility"/>' . $options . '</select>'
			));
		}

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
		function comments_array($comments, $post_id){			
			global $wpdb, $current_user, $post;

			$filtered_comments = array();
			
			$comment_ids = array();
			
			foreach($comments as $comment){
				$comment_ids[] = intval($comment->comment_ID);
			}

			$comment_ids = implode(',', $comment_ids);

			$current_user_id = intval($current_user->ID);

			$commenter = wp_get_current_commenter();
			$comment_author = $commenter['comment_author'];
			$comment_author_email = $commenter['comment_author_email'];

			$visibility_post_author = intval(self::VISIBILITY_POST_AUTHOR);
			$visibility_comment_author = intval(self::VISIBILITY_COMMENT_AUTHOR);


			if($current_user_id == 0){
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
			
			$removed_comments = $wpdb->get_col($sql);

			while(count($removed_comments) > 0){
				$comment_id_to_remove = array_pop($removed_comments);
				
				foreach($comments as $key => $comment){
					if($comment->comment_ID == $comment_id_to_remove){
						unset($comments[$key]);
					}
					else if($comment->comment_parent == $comment_id_to_remove){
						array_push($comment->comment_ID);
					}
				}
			}

			$this->_comment_counts[$post_id] = count($comments);
			return array_values($comments);
		}

		/**
		 * Append our fields to the $logged_in_as html that is output above the comment form
		 * @param string $logged_in_as 
		 * @return string
		 */
		function comment_form_logged_in($logged_in_as) {
			$fields = $this->getFields();

			foreach ( $fields as $name => $field ) {
				$logged_in_as .= apply_filters( "comment_form_field_{$name}", $field ) . "\n";
			}

			return $logged_in_as;
		}

		/**
		 * Append our fields to the fields that wordpress shows on the comment form
		 * @param array $fields 
		 * @return array
		 */
		function comment_form_default_fields( $fields ){
			return array_merge($fields, $this->getFields());
		}

		/**
		 * Save the fields that were added to the comment form as meta data for later use
		 * @param int $comment_id 
		 */
		function comment_post( $comment_id ) {
			$field_names = array_keys($this->getFields());

			foreach($field_names as $field_name){
				if(isset($_POST[$field_name]) && !empty($_POST[$field_name])){
					add_comment_meta( $comment_id, self::FIELD_PREFIX . $field_name, $_POST[$field_name] );
				}
			}
		}
	}