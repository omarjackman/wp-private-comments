<?php

	/**
	 * 
	 * @package default
	 */
	class WP_PrivateComments {

		protected static $_instance;


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
		}


		/**
		 * Get the fields that will be placed on a comment form
		 * @return array
		 */
		function getFields(){
			return apply_filters('WP_PrivateComments::getFields', array(
				'visibility' => '<p class="comment-form-visibility"><label for="visibility">' . __( 'Visibility' ) . '</label><select id="visibility" name="visibility"/></select>'
			));
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
			$fields_names = array_keys($this->getFields());
			foreach($field_names as $field_name){
				add_comment_meta( $comment_id, $field_name, $_POST[$field_name] );
			}
		}
	}