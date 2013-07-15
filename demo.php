<?php
/**
 * Registering meta sections for taxonomies
 *
 * All the definitions of meta sections are listed below with comments, please read them carefully.
 * Note that each validation method of the Validation Class MUST return value.
 *
 * You also should read the changelog to know what has been changed
 *
 */

// Hook to 'admin_init' to make sure the class is loaded before
// (in case using the class in another plugin)
add_action( 'admin_init', 'YOUR_PREFIX_register_taxonomy_meta_boxes' );

/**
 * Register meta boxes
 *
 * @return void
 */
function YOUR_PREFIX_register_taxonomy_meta_boxes()
{
	// Make sure there's no errors when the plugin is deactivated or during upgrade
	if ( !class_exists( 'RW_Taxonomy_Meta' ) )
		return;

	$meta_sections = array();

	// First meta section
	$meta_sections[] = array(
		'title'      => 'Standard Fields',             // section title
		'taxonomies' => array('category', 'post_tag'), // list of taxonomies. Default is array('category', 'post_tag'). Optional
		'id'         => 'option_name',                 // ID of each section, will be the option name

		'fields' => array(                             // List of meta fields
			// TEXT
			array(
				'name' => 'Text',                      // field name
				'desc' => 'Simple text field',         // field description, optional
				'id'   => 'text',                      // field id, i.e. the meta key
				'type' => 'text',                      // field type
				'std'  => 'Text',                      // default value, optional
			),
			// TEXTAREA
			array(
				'name' => 'Textarea',
				'id'   => 'textarea',
				'type' => 'textarea',
			),
			// SELECT
			array(
				'name'    => 'Select',
				'id'      => 'select',
				'type'    => 'select',
				'options' => array(                     // Array of value => label pairs for radio options
					'value1' => 'Label 1',
					'value2' => 'Label 2'
				),
			),
			// RADIO
			array(
				'name'    => 'Radio',
				'id'      => 'radio',
				'type'    => 'radio',
				'options' => array(                     // Array of value => label pairs for radio options
					'value1' => 'Label 1',
					'value2' => 'Label 2'
				),
			),
			// CHECKBOX
			array(
				'name' => 'Checkbox',
				'id'   => 'checkbox',
				'type' => 'checkbox',
			),
		),
	);

	// Second meta section
	$meta_sections[] = array(
		'title' => 'Advanced Fields',
		'id'    => 'option_name',

		'fields' => array(
			// CHECKBOX LIST
			array(
				'name'    => 'Checkbox list',
				'id'      => 'checkbox_list',
				'type'    => 'checkbox_list',
				'options' => array(                     // Array of value => label pairs for radio options
					'value1' => 'Label 1',
					'value2' => 'Label 2'
				),
				'desc'    => 'What do you do in free time?'
			),
			// WYSIWYG
			array(
				'name' => 'WYSIWYG Editor',
				'id'   => 'wysiwyg',
				'type' => 'wysiwyg',
			),
			// DATE PICKER
			array(
				'name'   => 'Date Picker',
				'id'     => 'date',
				'type'   => 'date',
				'format' => 'd MM, yy',                // Date format, default yy-mm-dd. Optional. See: http://goo.gl/po8vf
			),
			// TIME PICKER
			array(
				'name'   => 'Time Picker',
				'id'     => 'time',
				'type'   => 'time',
				'format' => 'hh:mm:ss',                // Time format, default hh:mm. Optional. See: http://goo.gl/hXHWz
			),
			// FILE
			array(
				'name' => 'File',
				'id'   => 'file',
				'type' => 'file',
			),
			// IMAGE
			array(
				'name' => 'Image',
				'id'   => 'image',
				'type' => 'image',
			),
			// COLOR PICKER
			array(
				'name' => 'Color Picker',
				'id'   => 'color',
				'type' => 'color',
			),
		),
	);

	foreach ( $meta_sections as $meta_section )
	{
		new RW_Taxonomy_Meta( $meta_section );
	}
}
