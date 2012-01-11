<?php

class GCFC_Convert_Page extends scbAdminPage {
	private $lat_key;
	private $lng_key;
	
	function setup() {
		$this->args = array( 
			'page_title' => 'Geo Convert',
		);

		if ( defined( 'WP_GEO_LATITUDE_META' ) and defined( 'WP_GEO_LONGITUDE_META' ) ) {
			$this->lat_key = WP_GEO_LATITUDE_META; 
			$this->lng_key = WP_GEO_LONGITUDE_META;
		} else {
			$this->lat_key = '_wp_geo_latitude'; 
			$this->lng_key = '_wp_geo_longitude';
		}
	}

	function get_wp_geo_meta() {
		global $wpdb;
		
		$meta = $wpdb->get_results( 
			$wpdb->prepare( 
				"SELECT lat.post_id, lat.meta_value as wp_geo_latitude, lng.meta_value as wp_geo_longitude FROM {$wpdb->postmeta} lat JOIN {$wpdb->postmeta} lng ON lat.post_id = lng.post_id WHERE lat.meta_key=%s AND lng.meta_key=%s",
				$this->lat_key,
				$this->lng_key
			)
		);
		return $meta;
	}

	function page_content() {

		if ( !empty( $_POST['action'] ) )
			$this->convert();

		echo html( 'h3', 'Convert Custom Fields' );
		echo scb_admin_notice( 'Warning: This process modifies data! Make a database backup first. Changes may not be reversible.' );
		echo html( 'p', 'For each post with WP Geo custom fields:' );
		$form = $this->table_row( array(
			'title' => 'Create Geo Mashup locations',
			'type' => 'radio',
			'name' => 'geo_mashup',
			'value' => array( 
				'none' => 'No',
				'skip' => 'Leave existing',
				'overwrite' => 'Overwrite existing',
			),
			'selected' => 'none',
		) );
		$form .=	$this->table_row( array(
			'title' => 'Remove WP Geo fields',
			'type' => 'checkbox',
			'name' => 'remove_wp_geo',
			'desc' => 'Yes',
		) );
		$form = $this->table_wrap( $form );
		echo $this->form_wrap( $form, array( 'value' => 'Convert!' ) );

		echo html( 'h3', 'Current WP Geo fields' );

		$meta = $this->get_wp_geo_meta();
		if ( empty( $meta ) ) {
			echo html( 'p', 'None.' );
		} else {
			$row = html( 'th', 'Post ID' );
			$row .= html( 'th', 'wp geo latitude' );
			$row .= html( 'th', 'wp geo longitude' );
			$rows = html( 'tr', $row );
			foreach( $meta as $meta_row ) {
				$row = html( 'td', $meta_row->post_id );
				$row .= html( 'td', $meta_row->wp_geo_latitude );
				$row .= html( 'td', $meta_row->wp_geo_longitude );
				$rows .= html( 'tr', $row );
			}
			echo html( 'table', $rows );
		}
	}

	function form_handler() {
		// No options are saved currently
		// Conversion is done during page content to show results
	}

	function convert() {

		check_admin_referer( $this->nonce );

		echo html( 'h3', 'Conversion Results' );

		$geo_mashup = scbForms::get_value( 'geo_mashup', $_POST, 'none' );
		$remove_wp_geo = scbForms::get_value( 'remove_wp_geo', $_POST );

		if ( 'none' == $geo_mashup ) {
			echo html( 'p', 'No conversions requested.' );
			return false;
		}

		if ( 'none' != $geo_mashup and !class_exists( 'GeoMashupDB' ) ) {
			echo html( 'p class="error"', 'Activate Geo Mashup to create Geo Mashup locations.' );
			return false;
		}

		$meta = $this->get_wp_geo_meta();
		if ( !empty( $meta ) ) {
			$row = html( 'th', 'Post ID' );
			$row .= html( 'th', 'wp geo latitude' );
			$row .= html( 'th', 'wp geo longitude' );
			if ( 'none' != $geo_mashup ) 
				$row .= html( 'th', 'geo mashup result' );
			if ( $remove_wp_geo )
				$row .= html( 'th', 'wp geo fields deleted' );
			$rows = html( 'tr', $row );

			foreach( $meta as $meta_row ) {
				$row = html( 'td', $meta_row->post_id );
				$row .= html( 'td', $meta_row->wp_geo_latitude );
				$row .= html( 'td', $meta_row->wp_geo_longitude );
				if ( 'none' != $geo_mashup )
					$row .= html( 'td', $this->convert_to_geo_mashup( $meta_row, $geo_mashup ) );
				if ( $remove_wp_geo ) {
					delete_post_meta( $meta_row->post_id, $this->lat_key );
					delete_post_meta( $meta_row->post_id, $this->lng_key );
					$row .= html( 'td', 'yes' );
				}
				$rows .= html( 'tr', $row );
			}
			echo scb_admin_notice( html( 'table', $rows ) );
		}
	}

	function convert_to_geo_mashup( $meta_row, $policy ) {
		$gm_location = GeoMashupDB::get_object_location( 'post', $meta_row->post_id );
		if ( $gm_location and 'skip' == $policy ) 
				return 'Left existing: ' . $gm_location->lat . ',' . $gm_location->lng;
		$location = array (
			'lat' => $meta_row->wp_geo_latitude,
			'lng' => $meta_row->wp_geo_longitude
		);
		$location_id = GeoMashupDB::set_object_location( 'post', $meta_row->post_id, $location );
		if ( is_wp_error( $location_id ) ) 
			return $location_id->get_error_message();
		if ( $gm_location )
			return 'Replaced location ' . $gm_location->id . ' with ' . $location_id;

		return 'Created location ' . $location_id;
	}

}