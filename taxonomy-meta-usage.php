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

/********************* BEGIN DEFINITION OF META SECTIONS ***********************/

$meta_sections = array();

// first meta section
$meta_sections[] = array(
	'title' => 'Personal Information',			// section title
	'taxonomies' => array('people'),			// list of taxonomies. Default is array('category', 'post_tag'). Optional
	'id' => 'first_section',					// ID of each section, will be the option name
	
	'fields' => array(							// list of meta fields
		array(
			'name' => 'Full name',					// field name
			'desc' => 'Format: Firstname Lastname',	// field description, optional
			'id' => 'fname',						// field id, i.e. the meta key
			'type' => 'text',						// text box
			'std' => 'Anh Tran',					// default value, optional
			'validate_func' => 'check_name'			// validate function, created below, inside RW_Meta_Box_Validate class
		),
		array(
			'name' => 'DOB',
			'id' => 'dob',
			'type' => 'date',						// date
			'format' => 'd MM, yy'					// date format, default yy-mm-dd. Optional. See more formats here: http://goo.gl/po8vf
		),
		array(
			'name' => 'Gender',
			'id' => 'gender',
			'type' => 'radio',						// radio box
			'options' => array(						// array of key => value pairs for radio options
				'm' => 'Male',
				'f' => 'Female'
			),
			'std' => 'm',
			'desc' => 'Need an explaination?'
		),
		array(
			'name' => 'Bio',
			'desc' => 'What\'s your professions? What you\'ve done?',
			'id' => 'bio',
			'type' => 'textarea',					// textarea
			'std' => 'I\'m a WP developer and a freelancer from Vietnam.'
		),
		array(
			'name' => 'Where do you live?',
			'id' => 'place',
			'type' => 'select',						// select box
			'options' => array(						// array of key => value pairs for select box
				'usa' => 'USA',
				'vn' => 'Vietnam'
			),
			'multiple' => true,						// select multiple values, optional. Default is false.
			'std' => array('vn'),					// default value, can be string (single value) or array (for both single and multiple values)
			'desc' => 'Select the current place, not in the past'
		),
		array(
			'name' => 'About WordPress',			// checkbox
			'id' => 'love_wp',
			'type' => 'checkbox',
			'desc' => 'I love WordPress'
		)
	)
);

// second meta section
$meta_sections[] = array(
	'id' => 'additional',
	'title' => 'Additional Information',
	'taxonomies' => array('category', 'post_tag'),

	'fields' => array(
		array(
			'name' => 'Your thoughts about Deluxe Blog Tips',
			'id' => 'thoughts',
			'type' => 'wysiwyg',					// WYSIWYG editor
			'std' => '<b>It\'s great!</b>',
			'desc' => 'Do you think so?'
		),
		array(
			'name' => 'Upload your source code',
			'desc' => 'Any modified code, or extending code',
			'id' => 'code',
			'type' => 'file'						// file upload
		),
		array(
			'name' => 'Screenshots',
			'desc' => 'Screenshots of problems, warnings, etc.',
			'id' => 'screenshot',
			'type' => 'image'						// image upload
		)
	)
);

// third meta section
$meta_sections[] = array(
	'id' => 'survey',
	'title' => 'Survey',
	'taxonomies' => array('category', 'post_tag'),

	'fields' => array(
		array(
			'name' => 'Your favorite color',
			'id' => 'color',
			'type' => 'color'						// color
		),
		array(
			'name' => 'Your hobby',
			'id' => 'hobby',
			'type' => 'checkbox_list',				// checkbox list
			'options' => array(						// options of checkbox, in type key => value (key cannot contain space)
				'reading' => 'Books, Magazines',
				'sport' => 'Gym, Boxing'
			),
			'desc' => 'What do you do in free time?'
		),
		array(
			'name' => 'When do you get up?',
			'id' => 'getup',
			'type' => 'time',						// time
			'format' => 'hh:mm:ss'					// time format, default hh:mm. Optional. See more formats here: http://goo.gl/hXHWz
		)
	)
);

foreach ($meta_sections as $meta_section) {
	$my_section = new RW_Taxonomy_Meta($meta_section);
}

/********************* END DEFINITION OF META SECTIONS ***********************/

/********************* BEGIN VALIDATION ***********************/

/**
 * Validation class
 * Define ALL validation methods inside this class
 * Use the names of these methods in the definition of meta boxes (key 'validate_func' of each field)
 */
class RW_Taxonomy_Meta_Validate {
	function check_name($text) {
		if ($text == 'Anh Tran') {
			return 'He is Rilwis';
		}
		return $text;
	}
}

/********************* END VALIDATION ***********************/
?>
