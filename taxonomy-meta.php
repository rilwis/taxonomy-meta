<?php

/*
Plugin Name: Taxonomy Meta
Plugin URI: http://www.deluxeblogtips.com/taxonomy-meta-script-for-wordpress
Description: Add meta values to terms, mimic custom post fields
Version: 1.1.7
Author: Rilwis
Author URI: http://www.deluxeblogtips.com
License: GPL2+
*/

class RW_Taxonomy_Meta {
	protected $_meta;
	protected $_taxonomies;
	protected $_fields;

	function __construct( $meta ) {
		if ( !is_admin() )
			return;

		$this->_meta = $meta;
		$this->normalize();

		add_action( 'admin_init', array( $this, 'add' ), 100 );
		add_action( 'edit_term', array( $this, 'save' ), 10, 2 );
		add_action( 'delete_term', array( $this, 'delete' ), 10, 2 );
		add_action( 'load-edit-tags.php', array( $this, 'load_edit_page' ) );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	function load_edit_page()
	{
		$screen = get_current_screen();
		if (
			'edit-tags' != $screen->base
			|| empty( $_GET['action'] ) || 'edit' != $_GET['action']
			|| !in_array( $screen->taxonomy, $this->_taxonomies )
		)
		{
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		$this->check_field_upload();
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	function admin_enqueue_scripts()
	{
		$this->check_field_date();
		$this->check_field_color();
		$this->check_field_time();
	}

	/******************** BEGIN UPLOAD **********************/

	// Check field upload and add needed actions
	function check_field_upload() {
		if ( $this->has_field( 'image' ) || $this->has_field( 'file' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_upload' ) );

			add_action( 'admin_head', array( $this, 'add_script_upload' ) ); // add scripts for handling add/delete images
			add_action( 'wp_ajax_rw_delete_file', array( $this, 'delete_file' ) );   // ajax delete files
		}
	}

	function enqueue_scripts_upload() {
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
	}

	// Add scripts for handling add/delete images
	function add_script_upload() {
		echo '
		<style type="text/css">
		.rw-images li {margin: 0 10px 10px 0; float: left; width: 150px; height: 100px; text-align: center; border: 3px solid #ccc; position: relative}
		.rw-images img {max-width: 150px; max-height: 100px}
		.rw-images a {position: absolute; bottom: 0; right: 0; color: #fff; background: #000; font-weight: bold; padding: 5px}
		</style>
		';

		echo '
		<script type="text/javascript">
		jQuery(document).ready(function($) {
		';

		echo '
			// Add enctype
			$("#edittag").attr("enctype", "multipart/form-data");
		';

		echo '
			// add more file
			$(".rw-add-file").click(function(){
				var $first = $(this).parent().find(".file-input:first");
				$first.clone().insertAfter($first).show();
				return false;
			});
		';

		echo '
			// delete file
			$(".rw-delete-file").click(function(){
				var $parent = $(this).parent(),
					data = $(this).attr("rel");
				$.post(ajaxurl, {action: \'rw_delete_file\', data: data}, function(response){
					if (response == "0") {
						alert("File has been successfully deleted.");
						$parent.remove();
					}
					if (response == "1") {
						alert("You don\'t have permission to delete this file.");
					}
				});
				return false;
			});
		';

		foreach ( $this->_fields as $field ) {
			if ( 'image' != $field['type'] )
				continue;

			$id = $field['id'];
			$tag_ID = isset( $_GET['tag_ID'] ) ? $_GET['tag_ID'] : '';
			$rel = "{$this->_meta['id']}!{$tag_ID}!{$field['id']}";
			$nonce_delete = wp_create_nonce( 'rw_ajax_delete_file' );
			echo "
			// thickbox upload
			$('#rw_upload_$id').click(function(){
				backup = window.send_to_editor;
				window.send_to_editor = function(html) {
					var el = $(html).is('a') ? $('img', html) : $(html),
						img_url = el.attr('src'),
						img_id = el.attr('class');

					img_id = img_id.slice((img_id.search(/wp-image-/) + 9));

					html = '<li id=\"item_' + img_id + '\">';
					html += '<img src=\"' + img_url + '\">';
					html += '<a title=\"" . __( 'Delete this image' ) . "\" class=\"rw-delete-file\" href=\"#\" rel=\"$rel!' + img_id + '!$nonce_delete\">" . __( 'Delete' ) . "</a>';
					html += '<input type=\"hidden\" name=\"{$id}[]\" value=\"' + img_id + '\">';
					html += '</li>';

					$('#rw-images-$id').append($(html));

					tb_remove();
					window.send_to_editor = backup;
				}
				tb_show('', 'media-upload.php?type=image&TB_iframe=true');

				return false;
			});
			";
		}

		echo '
		});
		</script>
		';
	}

	// Ajax delete files callback
	function delete_file() {
		if ( !isset( $_POST['data'] ) ) return;

		list( $meta_id, $term_id, $name, $att, $nonce ) = explode( '!', $_POST['data'] );

		if ( !wp_verify_nonce( $nonce, 'rw_ajax_delete_file' ) ) die( '1' );

		$metas = get_option( $meta_id );
		if ( empty( $metas ) ) $metas = array();
		if ( !is_array( $metas ) ) $metas = (array) $metas;

		// work on current term only
		if ( !isset( $metas[$term_id] ) || !isset( $metas[$term_id][$name] ) ) return;

		$files = & $metas[$term_id][$name];
		foreach ( $files as $k => $v ) {
			if ( $v == $att ) unset( $files[$k] );
		}

		update_option( $meta_id, $metas );
		die();
	}

	/******************** END UPLOAD **********************/

	/******************** BEGIN COLOR PICKER **********************/

	// Check field color
	function check_field_color() {
		if ( !$this->has_field( 'color' ) )
			return;
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		add_action( 'admin_footer', array( $this, 'add_script_color' ), 100 );
	}

	// Custom script for color picker
	function add_script_color() {
		echo '<script>
		jQuery(function($){
			$(".color").wpColorPicker();
		});
		</script>';
	}

	/******************** END COLOR PICKER **********************/

	/******************** BEGIN DATE PICKER **********************/

	// Check field date
	function check_field_date() {
		if ( !$this->has_field( 'date' ) )
			return;

		wp_enqueue_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		add_action( 'admin_head', array( $this, 'add_script_date' ) );
	}

	// Custom script for date picker
	function add_script_date() {
		$dates = array();
		foreach ( $this->_fields as $field ) {
			if ( 'date' == $field['type'] ) {
				$dates[$field['id']] = $field['format'];
			}
		}
		echo '
		<script type="text/javascript">
		jQuery(document).ready(function($){
		';
		foreach ( $dates as $id => $format ) {
			echo "$('#$id').datepicker({
				dateFormat: '$format',
				showButtonPanel: true
			});";
		}
		echo '
		});
		</script>
		';
	}

	/******************** END DATE PICKER **********************/

	/******************** BEGIN TIME PICKER **********************/

	// Check field time
	function check_field_time() {
		if ( !$this->has_field( 'time' ) )
			return;
		// add style and script, use proper jQuery UI version
		wp_enqueue_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css' );
		wp_enqueue_style( 'jquery-ui-timepicker', 'http://cdn.jsdelivr.net/jquery.ui.timepicker.addon/1.3/jquery-ui-timepicker-addon.css' );
		wp_enqueue_script( 'jquery-ui-timepicker', 'http://cdn.jsdelivr.net/jquery.ui.timepicker.addon/1.3/jquery-ui-timepicker-addon.min.js', array( 'jquery-ui-datepicker', 'jquery-ui-slider' ) );
		add_action( 'admin_head', array( $this, 'add_script_time' ) );
	}

	// Custom script and style for time picker
	function add_script_time() {
		// script
		$times = array();
		foreach ( $this->_fields as $field ) {
			if ( 'time' == $field['type'] ) {
				$times[$field['id']] = $field['format'];
			}
		}
		echo '
		<script type="text/javascript">
		jQuery(document).ready(function($){
		';
		foreach ( $times as $id => $format ) {
			echo "$('#$id').timepicker({showSecond: true, timeFormat: '$format'})";
		}
		echo '
		});
		</script>
		';
	}

	/******************** END TIME PICKER **********************/

	/******************** BEGIN META BOX PAGE **********************/

	// Add meta fields for taxonomies
	function add() {
		//foreach (get_taxonomies(array('show_ui' => true)) as $tax_name) {
		foreach ( get_taxonomies() as $tax_name ) {
			if ( in_array( $tax_name, $this->_taxonomies ) ) {
				add_action( $tax_name . '_edit_form', array( $this, 'show' ), 9, 2 );
			}
		}
	}

	// Show meta fields
	function show( $tag, $taxonomy ) {
		// get meta fields from option table
		$metas = get_option( $this->_meta['id'] );
		if ( empty( $metas ) ) $metas = array();
		if ( !is_array( $metas ) ) $metas = (array) $metas;

		// get meta fields for current term
		$metas = isset( $metas[$tag->term_id] ) ? $metas[$tag->term_id] : array();

		wp_nonce_field( basename( __FILE__ ), 'rw_taxonomy_meta_nonce' );

		echo "<h3>{$this->_meta['title']}</h3>
			<table class='form-table'>";

		foreach ( $this->_fields as $field ) {
			echo '<tr>';

			$meta = !empty( $metas[$field['id']] ) ? $metas[$field['id']] : $field['std']; // get meta value for current field
			$meta = is_array( $meta ) ? array_map( 'esc_attr', $meta ) : esc_attr( $meta );

			call_user_func( array( $this, 'show_field_' . $field['type'] ), $field, $meta );

			echo '</tr>';
		}

		echo '</table>';
	}

	/******************** END META BOX PAGE **********************/

	/******************** BEGIN META BOX FIELDS **********************/

	function show_field_begin( $field, $meta ) {
		echo "<th scope='row' valign='top'><label for='{$field['id']}'>{$field['name']}</label></th><td>";
	}

	function show_field_end( $field, $meta ) {
		echo $field['desc'] ? "<br>{$field['desc']}</td>" : '</td>';
	}

	function show_field_text( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		echo "<input type='text' name='{$field['id']}' id='{$field['id']}' value='$meta' size='40'  style='{$field['style']}'>";
		$this->show_field_end( $field, $meta );
	}

	function show_field_textarea( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		echo "<textarea name='{$field['id']}' cols='60' rows='15' style='{$field['style']}'>$meta</textarea>";
		$this->show_field_end( $field, $meta );
	}

	function show_field_select( $field, $meta ) {
		if ( !is_array( $meta ) ) $meta = (array) $meta;
		$this->show_field_begin( $field, $meta );
		echo "<select style='{$field['style']}' name='{$field['id']}" . ( $field['multiple'] ? "[]' multiple='multiple'" : "'" ) . ">";
		foreach ( $field['options'] as $key => $value ) {
			if ( $field['optgroups'] && is_array( $value ) ) {
				echo "<optgroup label=\"{$value['label']}\">";
				foreach ( $value['options'] as $option_key => $option_value ) {
					echo "<option value='$option_key'" . selected( in_array( $option_key, $meta ), true, false ) . ">$option_value</option>";
				}
				echo '</optgroup>';
			} else {
				echo "<option value='$key'" . selected( in_array( $key, $meta ), true, false ) . ">$value</option>";
			}
		}
		echo "</select>";
		$this->show_field_end( $field, $meta );
	}

	function show_field_radio( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		$html = array();
		foreach ( $field['options'] as $key => $value ) {
			$html[] .= "<label><input type='radio' name='{$field['id']}' value='$key'" . checked( $meta, $key, false ) . "> $value</label>";
		}
		echo implode( ' ', $html );
		$this->show_field_end( $field, $meta );
	}

	function show_field_checkbox( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		echo "<label><input type='checkbox' name='{$field['id']}' value='1'" . checked( !empty( $meta ), true, false ) . "></label>";
		$this->show_field_end( $field, $meta );
	}

	function show_field_wysiwyg( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		wp_editor( $meta, $field['id'], array(
			'textarea_name' => $field['id'],
			'editor_class'  => $field['id'].' theEditor',
		) );
		$this->show_field_end( $field, $meta );
	}

	function show_field_file( $field, $meta ) {
		if ( !is_array( $meta ) ) $meta = (array) $meta;

		$this->show_field_begin( $field, $meta );
		echo "{$field['desc']}<br>";

		if ( !empty( $meta ) ) {
			$nonce = wp_create_nonce( 'rw_ajax_delete_file' );
			$rel = "{$this->_meta['id']}!{$_GET['tag_ID']}!{$field['id']}";

			echo '<div style="margin-bottom: 10px"><strong>' . esc_html__( 'Uploaded files', 'meta_box' ) . '</strong></div>';
			echo '<ol>';
			foreach ( $meta as $att ) {
				if ( wp_attachment_is_image( $att ) ) continue; // what's image uploader for?
				echo "<li>" . wp_get_attachment_link( $att ) . " (<a class='rw-delete-file' href='#' rel='$rel!$att!$nonce'>" . __( 'Delete' ) . "</a>)</li>";
			}
			echo '</ol>';
		}

		// show form upload
		echo "<div style='clear: both'><strong>" . __( 'Upload new files' ) . "</strong></div>
			<div class='new-files'>
				<div class='file-input'><input type='file' name='{$field['id']}[]'></div>
				<a class='rw-add-file' href='javascript:void(0)'>" . __( 'Add more file' ) . "</a>
			</div>
		</td>";
	}

	function show_field_image( $field, $meta ) {
		if ( !is_array( $meta ) )
			$meta = (array) $meta;

		$this->show_field_begin( $field, $meta );
		if ( $field['desc'] )
			echo "{$field['desc']}<br>";

		$nonce_delete = wp_create_nonce( 'rw_ajax_delete_file' );
		$rel = "{$this->_meta['id']}!{$_GET['tag_ID']}!{$field['id']}";

		echo "<ul id='rw-images-{$field['id']}' class='rw-images'>";
		foreach ( $meta as $att ) {
			$img = wp_get_attachment_image( $att, array( 150, 100 ) );

			echo "<li id='item_{$att}'>
					<img src='$src'>
					<a title='" . __( 'Delete this image' ) . "' class='rw-delete-file' href='#' rel='$rel!$att!$nonce_delete'>" . __( 'Delete' ) . "</a>
					<input type='hidden' name='{$field['id']}[]' value='$att'>
				</li>";
		}
		echo '</ul>';

		echo "<a href='#' style='float: left; clear: both; margin-top: 10px' id='rw_upload_{$field['id']}' class='rw_upload button'>" . __( 'Upload new image' ) . "</a>";
	}

	function show_field_color( $field, $meta ) {
		if ( empty( $meta ) ) $meta = '#';
		$this->show_field_begin( $field, $meta );
		echo "<input type='text' name='{$field['id']}' id='{$field['id']}' value='$meta' class='color'>";
		$this->show_field_end( $field, $meta );
	}

	function show_field_checkbox_list( $field, $meta ) {
		if ( !is_array( $meta ) ) $meta = (array) $meta;
		$this->show_field_begin( $field, $meta );
		$html = array();
		foreach ( $field['options'] as $key => $value ) {
			$html[] = "<input type='checkbox' name='{$field['id']}[]' value='$key'" . checked( in_array( $key, $meta ), true, false ) . "> $value";
		}
		echo implode( '<br>', $html );
		$this->show_field_end( $field, $meta );
	}

	function show_field_date( $field, $meta ) {
		$this->show_field_text( $field, $meta );
	}

	function show_field_time( $field, $meta ) {
		$this->show_field_text( $field, $meta );
	}

	/******************** END META BOX FIELDS **********************/

	/******************** BEGIN META BOX SAVE **********************/

	// Save meta fields
	function save( $term_id, $tt_id ) {
		/*
		if (!check_admin_referer(basename(__FILE__), 'rw_taxonomy_meta_nonce')) {	// check nonce
			return;
		}
		*/

		$metas = get_option( $this->_meta['id'] );
		if ( !is_array( $metas ) )
			$metas = (array) $metas;

		$meta = isset( $metas[$term_id] ) ? $metas[$term_id] : array();

		foreach ( $this->_fields as $field ) {
			$name = $field['id'];
			$type = $field['type'];

			$old = isset( $meta[$name] ) ? $meta[$name] : ( $field['multiple'] ? array() : '' );
			$new = isset( $_POST[$name] ) ? $_POST[$name] : ( $field['multiple'] ? array() : '' );

			// Call defined method to save meta value, if there's no methods, call common one
			$save_func = 'save_field_' . $type;
			if ( method_exists( $this, $save_func ) ) {
				$meta = call_user_func( array( $this, $save_func ), $meta, $field, $old, $new );
			} else {
				$this->save_field( $meta, $field, $old, $new );
			}
		}

		$metas[$term_id] = $meta;
		update_option( $this->_meta['id'], $metas );
	}

	// Common functions for saving field
	function save_field( &$meta, $field, $old, $new ) {
		$name = $field['id'];

		$new = is_array( $new ) ? array_map( 'stripslashes', $new ) : stripslashes( $new );
		if ( empty( $new ) ) {
			unset( $meta[$name] );
		} else {
			$meta[$name] = $new;
		}
	}

	function save_field_file( $meta, $field, $old, $new ) {
		$name = $field['id'];
		if ( empty( $_FILES[$name] ) ) return;

		$this->fix_file_array( $_FILES[$name] );

		foreach ( $_FILES[$name] as $position => $fileitem ) {
			$file = wp_handle_upload( $fileitem, array( 'test_form' => false ) );

			if ( empty( $file['file'] ) ) continue;
			$filename = $file['file'];

			$attachment = array(
				'post_mime_type' => $file['type'],
				'guid'           => $file['url'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
			);
			$id = wp_insert_attachment( $attachment, $filename );
			if ( !is_wp_error( $id ) ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $filename ) );
				$meta[$name][] = $id;
			}
		}

		return $meta;
	}

	/******************** END META BOX SAVE **********************/

	function delete( $term_id, $tt_id ) {
		$metas = get_option( $this->_meta['id'] );
		if ( !is_array( $metas ) ) $metas = (array) $metas;

		unset( $metas[$term_id] );

		update_option( $this->_meta['id'], $metas );
	}

	/******************** BEGIN HELPER FUNCTIONS **********************/

	// Add missed values for meta box
	function normalize() {
		// Default values for meta box
		$this->_meta = array_merge( array(
			'taxonomies' => array( 'category', 'post_tag' )
		), $this->_meta );

		$this->_taxonomies = $this->_meta['taxonomies'];
		$this->_fields = $this->_meta['fields'];

		// Default values for fields
		foreach ( $this->_fields as & $field ) {
			$multiple = in_array( $field['type'], array( 'checkbox_list', 'file', 'image' ) ) ? true : false;
			$std = $multiple ? array() : '';
			$format = 'date' == $field['type'] ? 'yy-mm-dd' : ( 'time' == $field['type'] ? 'hh:mm' : '' );
			$style = 'width: 97%';
			$optgroups = false;
			if ( 'select' == $field['type'] )
				$style = 'height: auto';

			$field = array_merge( array(
				'multiple'  => $multiple,
				'optgroups' => $optgroups,
				'std'       => $std,
				'desc'      => '',
				'format'    => $format,
				'style'     => $style,
			), $field );
		}
	}

	// Check if field with $type exists
	function has_field( $type ) {
		foreach ( $this->_fields as $field ) {
			if ( $type == $field['type'] ) return true;
		}
		return false;
	}

	/**
	 * Fixes the odd indexing of multiple file uploads from the format:
	 *  $_FILES['field']['key']['index']
	 * To the more standard and appropriate:
	 *  $_FILES['field']['index']['key']
	 */
	function fix_file_array( &$files ) {
		$output = array();
		foreach ( $files as $key => $list ) {
			foreach ( $list as $index => $value ) {
				$output[$index][$key] = $value;
			}
		}
		$files = $output;
	}

	/******************** END HELPER FUNCTIONS **********************/
}
