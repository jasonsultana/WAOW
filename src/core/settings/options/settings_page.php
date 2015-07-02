<?php namespace PaintCloud\WP\Settings;

$page = new Page('WAOW', array('type' => 'settings'));

$settings = array();

// Section One
// ------------------------//
$settings['General'] = array('info' => 'General settings for WAOW. The source URL is relative to the template directory root.');

$fields = array();
$fields[] = array(
	'type' 	=> 'text',
	'name' 	=> 'waow_source-uri',
	'label' => 'Source Folder'
);

$fields[] = array(
	'type' 	=> 'radio',
	'name' 	=> 'waow_position',
	'label' => 'Content Position',
	'value' => 'top', // (optional, will default to '')
	'radio_options' => array(
		array('value'=>'top', 'label' => 'Top (before script output)'),
		array('value'=>'bottom', 'label' => 'Bottom (after script output)')
	)			
);

$settings['General']['fields'] = $fields;

new OptionPageBuilderSingle($page, $settings);