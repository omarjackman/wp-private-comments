<?php
/*
	Plugin Name: WP Private Comments
	Plugin URI: http://www.omarjackman.com/plugins/wp-private-comments/
	Description: Give your users the ability to assign visibility settings for their comments
	Version: 0.0.01
	Author: Omar Jackman
	Author URI: http://www.omarjackman.com
	License: GPLv2
*/

/*  Copyright 2013  Omar Jackman  (email : plugins@omarjackman.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

	require_once('lib/WP_PrivateComments.class.php');

	WP_PrivateComments::getInstance();