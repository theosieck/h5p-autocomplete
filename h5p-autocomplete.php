<?php
/*
 * Plugin Name: H5P Autocomplete
 * Version: 0.2
 * Author: Jorie Sieck
 * Author URI: https://www.joriesieck.com
 * Description: Adds setting to LearnDash topics to toggle autocompletion on H5P completion
 * Text Domain: ta-h5p-autocomplete
 * License: GPLv3
*/

// no direct access
defined( 'ABSPATH' ) or die( 'No direct access!' );

/**
 * add option to autocomplete on h5p completion to LD topic settings
*/
add_filter( 'learndash_settings_fields', 'ta_add_autocomplete_option', 10, 2 );
function ta_add_autocomplete_option($setting_option_fields) {
	global $post;
	if($post->post_type==='sfwd-topic') {
		// get the current ld post settings for the given post
		$current_val = learndash_get_setting($post,'ta_h5p_auto_complete');
		// preserve current value, if it exists
		$value = '';
		if(isset($current_val) && !empty($current_val)) {
			$value = $current_val;
		}

		// set up the option
		$setting_option_fields['ta_h5p_auto_complete'] = array(
			'name'      => 'ta_h5p_auto_complete',
			'label'     => 'H5P Auto Complete',
			'type'      => 'select',
			'help_text' => 'Automatically complete topic on successful H5P completion',
			'options'   => array(
				'disabled'    => 'Disabled',
				'enabled'     => 'Enabled',
			),
			'default'   => 'disabled',
			'value'     => $value,
		);
	}
	return $setting_option_fields;
}

/**
 * save h5p autocomplete settings to post metadata
*/
add_filter( 'learndash_metabox_save_fields','ta_save_autocomplete_setting',60,3);
function ta_save_autocomplete_setting($settings) {
	global $post;

	// check for the value and if it's there, update the post meta
	$topic_settings = $_POST['learndash-topic-display-content-settings'];
	if (isset($topic_settings) && isset($topic_settings['ta_h5p_auto_complete'])) {
		$value = sanitize_text_field($topic_settings['ta_h5p_auto_complete']);
		learndash_update_setting($post,'ta_h5p_auto_complete',$value);
	}

	// we don't use this value but bc it's a filter we have to return it
	return $settings;
}

/**
 * if autocomplete turned on, remove mark complete button & call scripts function
*/
add_filter('learndash_mark_complete','ta_remove_mark_complete',99,2);
function ta_remove_mark_complete($return,$post) {
	// check topic settings
	$h5p_autocomplete = learndash_get_setting($post,'ta_h5p_auto_complete');
	if(isset($h5p_autocomplete) && $h5p_autocomplete==='enabled') {
		// enqueue scripts
		ta_h5p_completion();

		// remove html markup for mark complete button
		return '';
	}
	// not enabled, so just return the regular markup
	return $return;
}

/**
 * enqueue scripts
*/
function ta_h5p_completion() {
	wp_enqueue_script(
			'tahc-main-js',
			plugins_url('./main.js', __FILE__),
			['jquery'],
			time(),
			true
	);

	// send ajax url & nonce
	wp_localize_script('tahc-main-js','ajaxData',array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('ta_autocomplete_nonce'),
		'action' => 'ta_mark_complete'
	));
}

/**
 * handle ajax request
*/
add_action('wp_ajax_ta_mark_complete','ta_mark_complete');
function ta_mark_complete() {
	check_ajax_referer('ta_autocomplete_nonce');

	global $current_user;
	global $post;
	// get the topic post id
	$topic_url = $_POST['url'];
	$topic_title = $_POST['title'];
	// $topic_slug = substr($topic_url,strpos($topic_url,"=")+1); // local
	$topic_slug = substr($topic_url,strpos($topic_url,"topic/")+6);	// live
	$topic_id = learndash_get_page_by_path($topic_slug,'sfwd-topic')->ID;
	$course_id = learndash_get_course_id($topic_id);

	// mark the corresponding topic completed
	$result = learndash_process_mark_complete($current_user->ID,$topic_id,false,$course_id);

	// make sure it got marked complete - possible future edit save to local storage if completion fails?
	$is_complete = learndash_is_topic_complete($current_user->ID,$topic_id,$course_id);

	// send response back to js
	$response['type'] = $is_complete;
	$response = json_encode($response);
	echo $response;
	die;
}
