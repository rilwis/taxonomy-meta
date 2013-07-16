<?php
/*
Plugin Name: Taxonomy Meta
Plugin URI: http://www.deluxeblogtips.com/taxonomy-meta-script-for-wordpress
Description: Add meta values to terms, mimic custom post fields
Version: 1.2
Author: Rilwis
Author URI: http://www.deluxeblogtips.com
License: GPL2+
*/

class RW_Taxonomy_Meta {
	protected $_meta;
	protected $_taxonomies;
	protected $_fields;

	/**
	 * Store all CSS of fields
	 * @var string
	 */
	public $css = '';

	/**
	 * Store all JS of fields
	 * @var string
	 */
	public $js = '';

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
		add_action( 'admin_head', array( $this, 'output_css' ) );
		add_action( 'admin_footer', array( $this, 'output_js' ), 100 );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	function admin_enqueue_scripts()
	{
		wp_enqueue_script( 'jquery' );

		$this->check_field_upload();
		$this->check_field_date();
		$this->check_field_color();
		$this->check_field_time();
	}

	/**
	 * Output CSS into header
	 *
	 * @return void
	 */
	function output_css()
	{
		echo $this->css ? '<style>' . $this->css . '</style>' : '';
	}

	/**
	 * Output JS into footer
	 *
	 * @return void
	 */
	function output_js()
	{
		echo $this->js ? '<script>jQuery(function($){' . $this->js . '});</script>' : '';
	}

	/******************** BEGIN FIELDS **********************/

	// Check field upload and add needed actions
	function check_field_upload() {
		if ( !$this->has_field( 'image' ) && $this->has_field( 'file' ) )
			return;

		$this->css .= '
			.rwtm-uploaded {overflow: hidden; margin: 0 0 10px}
			.rwtm-files {padding-left: 20px}
			.rwtm-images li {margin: 0 10px 10px 0; float: left; width: 150px; height: 100px; text-align: center; border: 3px solid #ccc; position: relative}
			.rwtm-images img {max-width: 150px; max-height: 100px}
			.rwtm-images a {position: absolute; bottom: 0; right: 0; color: #fff; background: #000; font-weight: bold; padding: 5px}
		';

		// Add enctype
		$this->js .= '
			$("#edittag").attr("enctype", "multipart/form-data");
		';

		// Delete file
		$this->js .= '
			$("body").on("click", ".rwtm-delete-file", function(){
				$(this).parent().remove();
				return false;
			});
		';

		// File upload
		if ( $this->has_field( 'file' ) ) {
			$this->js .= "
			\$('body').on('click', '.rwtm-file-upload', function(){
				var id = \$(this).data('field');

				var template = '<# _.each(attachments, function(attachment) { #>';
				template += '<li>';
				template += '<a href=\"{{{ attachment.url }}}\">{{{ attachment.filename }}}</a>';
				template += ' (<a class=\"rwtm-delete-file\" href=\"#\">" . __( 'Delete', 'rwtm' ) . "</a>)';
				template += '<input type=\"hidden\" name=\"' + id + '[]\" value=\"{{{ attachment.id }}}\">';
				template += '</li>';
				template += '<# }); #>';

				var \$uploaded = \$(this).siblings('.rwtm-uploaded');

				var frame = wp.media({
					multiple : true,
					title    : \"" . __( 'Select File', 'rwtm' ) . "\"
				});
				frame.on('select', function()
				{
					var selection = frame.state().get('selection').toJSON();

					\$uploaded.append(_.template(template, { attachments: selection }, {
						evaluate:    /<#([\s\S]+?)#>/g,
						interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
						escape:      /\{\{([^\}]+?)\}\}(?!\})/g
					}));
				});
				frame.open();

				return false;
			});
			";
		}

		if ( !$this->has_field( 'image' ) )
			return;

		wp_enqueue_media();

		// Image upload
		$this->js .= "
		\$('body').on('click', '.rwtm-image-upload', function(){
			var id = \$(this).data('field');

			var template = '<# _.each(attachments, function(attachment) { #>';
			template += '<li>';
			template += '<img src=\"{{{ attachment.sizes.full.url }}}\">';
			template += '<a class=\"rwtm-delete-file\" href=\"#\">" . __( 'Delete', 'rwtm' ) . "</a>';
			template += '<input type=\"hidden\" name=\"' + id + '[]\" value=\"{{{ attachment.id }}}\">';
			template += '</li>';
			template += '<# }); #>';

			var \$uploaded = \$(this).siblings('.rwtm-uploaded');

			var frame = wp.media({
				multiple : true,
				title    : \"" . __( 'Select Image', 'rwtm' ) . "\",
				library  : {
					type: 'image'
				}
			});
			frame.on('select', function()
			{
				var selection = frame.state().get('selection').toJSON();

				\$uploaded.append(_.template(template, { attachments: selection }, {
					evaluate:    /<#([\s\S]+?)#>/g,
					interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
					escape:      /\{\{([^\}]+?)\}\}(?!\})/g
				}));
			});
			frame.open();

			return false;
		});
		";
	}

	// Check field color
	function check_field_color() {
		if ( !$this->has_field( 'color' ) )
			return;
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		$this->js .= '$(".color").wpColorPicker();';
	}

	// Check field date
	function check_field_date() {
		if ( !$this->has_field( 'date' ) )
			return;

		wp_enqueue_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		// CSS
		$this->css .= '#ui-datepicker-div {display: none}';

		// JS
		$dates = array();
		foreach ( $this->_fields as $field ) {
			if ( 'date' == $field['type'] ) {
				$dates[$field['id']] = $field['format'];
			}
		}
		foreach ( $dates as $id => $format ) {
			$this->js .= "$('#$id').datepicker({
				dateFormat: '$format',
				showButtonPanel: true
			});";
		}
	}

	// Check field time
	function check_field_time() {
		if ( !$this->has_field( 'time' ) )
			return;

		wp_enqueue_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css' );
		wp_enqueue_style( 'jquery-ui-timepicker', 'http://cdn.jsdelivr.net/jquery.ui.timepicker.addon/1.3/jquery-ui-timepicker-addon.css' );
		wp_enqueue_script( 'jquery-ui-timepicker', 'http://cdn.jsdelivr.net/jquery.ui.timepicker.addon/1.3/jquery-ui-timepicker-addon.min.js', array( 'jquery-ui-datepicker', 'jquery-ui-slider' ) );

		// CSS
		$this->css .= '#ui-datepicker-div {display: none}';

		// JS
		$times = array();
		foreach ( $this->_fields as $field ) {
			if ( 'time' == $field['type'] ) {
				$times[$field['id']] = $field['format'];
			}
		}
		foreach ( $times as $id => $format ) {
			$this->js .= "$('#$id').timepicker({showSecond: true, timeFormat: '$format'})";
		}
	}

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

	/******************** BEGIN META BOX FIELDS **********************/

	function show_field_begin( $field, $meta ) {
		echo "<th scope='row' valign='top'><label for='{$field['id']}'>{$field['name']}</label></th><td>";
	}

	function show_field_end( $field, $meta ) {
		echo $field['desc'] ? "<br>{$field['desc']}</td>" : '</td>';
	}

	function show_field_text( $field, $meta ) {
		$this->show_field_begin( $field, $meta );
		echo "<input type='text' name='{$field['id']}' id='{$field['id']}' value='$meta' style='{$field['style']}'>";
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
		if ( !is_array( $meta ) )
			$meta = (array) $meta;

		$this->show_field_begin( $field, $meta );
		if ( $field['desc'] )
			echo "{$field['desc']}<br>";

		echo '<ol class="rwtm-files rwtm-uploaded">';
		foreach ( $meta as $att ) {
			printf( '
				<li>
					%s (<a class="rwtm-delete-file" href="#">%s</a>)
					<input type="hidden" name="%s[]" value="%s">
				</li>',
				wp_get_attachment_link( $att ),
				__( 'Delete', 'rwtm' ),
				$field['id'],
				$att
			);
		}
		echo '</ol>';

		echo "<a href='#' class='rwtm-file-upload button' data-field='{$field['id']}'>" . __( 'Select File', 'rwtm' ) . "</a>";
		echo '</td>';
	}

	function show_field_image( $field, $meta ) {
		if ( !is_array( $meta ) )
			$meta = (array) $meta;

		$this->show_field_begin( $field, $meta );
		if ( $field['desc'] )
			echo "{$field['desc']}<br>";

		echo '<ul class="rwtm-uploaded rwtm-images">';
		foreach ( $meta as $att ) {
			printf( '
				<li>
					%s <a class="rwtm-delete-file" href="#">%s</a>
					<input type="hidden" name="%s[]" value="%s">
				</li>',
				wp_get_attachment_image( $att ),
				__( 'Delete', 'rwtm' ),
				$field['id'],
				$att
			);
		}
		echo '</ul>';

		echo "<a href='#' class='rwtm-image-upload button' data-field='{$field['id']}'>" . __( 'Select Image', 'rwtm' ) . "</a>";
		echo '</td>';
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

	/******************** BEGIN META BOX SAVE **********************/

	// Save meta fields
	function save( $term_id, $tt_id ) {
		$metas = get_option( $this->_meta['id'] );
		if ( !is_array( $metas ) )
			$metas = (array) $metas;

		$meta = isset( $metas[$term_id] ) ? $metas[$term_id] : array();

		foreach ( $this->_fields as $field ) {
			$name = $field['id'];

			$new = isset( $_POST[$name] ) ? $_POST[$name] : ( $field['multiple'] ? array() : '' );
			$new = is_array( $new ) ? array_map( 'stripslashes', $new ) : stripslashes( $new );
			if ( empty( $new ) ) {
				unset( $meta[$name] );
			} else {
				$meta[$name] = $new;
			}
		}

		$metas[$term_id] = $meta;
		update_option( $this->_meta['id'], $metas );
	}

	/******************** BEGIN META BOX DELETE **********************/

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
			$style = in_array( $field['type'], array( 'text', 'textarea' ) ) ? 'width: 95%' : '';
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
	function fix_file_array( $files ) {
		$output = array();
		foreach ( $files as $key => $list ) {
			foreach ( $list as $index => $value ) {
				$output[$index][$key] = $value;
			}
		}
		$files = $output;
		return $output;
	}

	/******************** END HELPER FUNCTIONS **********************/
}
