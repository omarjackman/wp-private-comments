<?php

	/**
	 * 
	 * @package default
	 */
	class WP_PrivateComments {

		private static $_instance;


		private function __clone() {}


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


		private function __construct(){

		} 
	}