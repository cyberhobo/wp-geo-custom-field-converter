<?php

class GCFC_Convert_Page extends scbAdminPage {
	private $lat_key;
	private $lng_key;
	private $combined_key;
	private $field_map;

	function setup() {
		$this->args = array( 
			'page_title' => 'Geo Convert',
		);

		$this->field_map = array(
			'wpgeo' => '_wp_geo_latitude,_wp_geo_longitude',
			'wpgeodata' => 'geo_latitude,geo_longitude',
			'csv' => 'enter combined field name',
			'other' => 'your_latitude_field,your_longitude_field',
		);

		if ( isset( $_POST['fields'] ) ) {
			if ( strpos( $_POST['fields'], ',' ) ) {
				list( $this->lat_key, $this->lng_key ) = explode( ',', $_POST['fields'] );
				$this->combined_key = null;
			} else {
				$this->combined_key = $_POST['fields'];
				$this->lat_key = $this->lng_key = null;
			}
		} else if ( defined( 'WP_GEO_LATITUDE_META' ) and defined( 'WP_GEO_LONGITUDE_META' ) ) {
			$this->lat_key = WP_GEO_LATITUDE_META; 
			$this->lng_key = WP_GEO_LONGITUDE_META;
			$this->field_map['wpgeo'] = $this->lat_key . ',' . $this->lng_key;
		} else {
			$this->lat_key = '_wp_geo_latitude'; 
			$this->lng_key = '_wp_geo_longitude';
		}
	}

	function get_wp_geo_meta() {
		global $wpdb;

		if ( $this->combined_key ) {
			$sql = "SELECT post_id, SUBSTR( meta_value, 1, LOCATE( ',', meta_value ) - 1 ) as wp_geo_latitude, " .
				"SUBSTR( meta_value, LOCATE( ',', meta_value ) + 1 ) as wp_geo_longitude " .
				"FROM {$wpdb->postmeta} " .
				"WHERE meta_key=%s";
			$meta = $wpdb->get_results( $wpdb->prepare( $sql, $this->combined_key ) );
		} else {
			$sql = "SELECT lat.post_id, lat.meta_value as wp_geo_latitude, lng.meta_value as wp_geo_longitude " .
				"FROM {$wpdb->postmeta} lat JOIN {$wpdb->postmeta} lng ON lat.post_id = lng.post_id " .
				"WHERE lat.meta_key=%s AND lng.meta_key=%s";

			$meta = $wpdb->get_results( $wpdb->prepare( $sql, $this->lat_key, $this->lng_key ) );
		}

		return $meta;
	}

	function page_content() {


		$selected_fieldspec = isset( $_POST['fieldspec'] ) ? $_POST['fieldspec'] : 'wpgeo';
		$fields = isset( $_POST['fields'] ) ? $_POST['fields'] : $this->field_map[$selected_fieldspec];

		if ( isset( $_POST['convert'] ) )
			$this->convert();

		echo html( 'h3', 'Convert Custom Fields' );
		echo scb_admin_notice( 'Warning: This process modifies data! Make a database backup first. Changes may not be reversible.' );
		$form = $this->table_row( array(
			'id' => 'geo-convert-form',
			'title' => 'For each post with custom fields:',
			'type' => 'radio',
			'name' => 'fieldspec',
			'value' => array(
				'wpgeo' => 'WP Geo plugin fields',
				'wpgeodata' => 'WP Geodata standard fields',
				'csv' => 'Single comma separated latitude, longitude field',
				'other' => 'Other pair of latitude, longitude fields',
			),
			'selected' => $selected_fieldspec,
		) );
		$form .= $this->table_row( array(
			'title' => 'Custom field names',
			'desc' => 'comma separated',
			'type' => 'input',
			'name' => 'fields',
			'value' => $fields,
		) );
		$form .= $this->table_row( array(
			'title' => 'Create Geo Mashup locations',
			'type' => 'radio',
			'name' => 'geo_mashup',
			'value' => array( 
				'none' => 'No',
				'skip' => 'Leave existing',
				'overwrite' => 'Overwrite existing',
			),
			'selected' => isset( $_POST['geo_mashup'] ) ? $_POST['geo_mashup'] : 'none',
		) );
		$form .= $this->table_row( array(
			'title' => 'Remove source fields',
			'type' => 'checkbox',
			'name' => 'remove_wp_geo',
			'desc' => 'Yes',
			'checked' => isset( $_POST['remove_wp_geo'] ),
		) );
		$form = $this->table_wrap( $form );

		$form .= $this->submit_button( 'Convert!', 'convert' );

		$form .= html( 'h3', 'Current WP Geo fields' );

		$form .= $this->submit_button( 'Refresh', 'refresh' );

		echo $this->form_wrap( $form, false );

		$meta = $this->get_wp_geo_meta();
		if ( empty( $meta ) ) {
			echo html( 'p', 'None.' );
		} else {
			$row = html( 'th', 'Post ID' );
			$row .= html( 'th', 'latitude' );
			$row .= html( 'th', 'longitude' );
			$rows = html( 'tr', $row );
			foreach( $meta as $meta_row ) {
				$row = html( 'td', $meta_row->post_id );
				$row .= html( 'td', $meta_row->wp_geo_latitude );
				$row .= html( 'td', $meta_row->wp_geo_longitude );
				$rows .= html( 'tr', $row );
			}
			echo html( 'table', $rows );
		}

		$field_map_js = json_encode( $this->field_map );
		$js = <<<EOD
jQuery( function($) {
	var \$fields = $('input[name=fields]' ),
		field_map = $field_map_js;
	\$fields.click( function() { \$fields.select(); } );
	$('input[name=fieldspec]').change( function() {
		var value = $(this).val();
		\$fields.val( field_map[value] );
		$('table.form-table').parent('form').submit();
	} );
} );
EOD;
		echo $this->js_wrap( $js );
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
			echo html( 'p', 'No conversions requested. (Check the "Create Geo Mashup locations" setting).' );
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
				$row .= html( 'th', 'source fields deleted' );
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
					delete_post_meta( $meta_row->post_id, $this->combined_key );
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