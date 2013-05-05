<?php
/*
Plugin Name: Geo Custom Field Converter
Version: 0.2.0
Description: Convert WP Geo custom fields to Geo Mashup in bulk.
Author: Dylan Kuhn
Author URI: http://www.cyberhobo.net
Plugin URI: http://github.com/cyberhobo/wp-geo-convert-custom-fields


Copyright (C) 2011 Dylan Kuhn (cyberhobo@cyberhobo.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

require dirname( __FILE__ ) . '/scb/load.php';

function gcfc_init() {
	if ( is_admin() ) {
		require_once( dirname( __FILE__ ) . '/convert-page.php' );
		new GCFC_Convert_Page( __FILE__ );
	}
}
scb_init( 'gcfc_init' );