<?php
/*
Plugin Name: WAOW
Plugin URI:  
Description: Web Applications on Wordpress.
Version:     0.1-alpha
Author:      Jason Sultana
Author URI:  http://wordpress.org/extend/plugins/health-check/
Text Domain: 
Domain Path: /
 */
	function autoload() {
		spl_autoload_register(function($class) {
			$dir = dirname(__FILE__) . '/core/' . $class . '.class.php';

			if (file_exists( $dir )) 
				require_once( $dir );
		});
	}
	autoload();

	function admin_render_settings() {
		require_once(dirname(__FILE__) . '/core/settings/load-plugin.php');
		require_once(dirname(__FILE__) . '/core/settings/options/settings_page.php');
	}
	admin_render_settings();

	function waow_get_options() {
		$options = array();
		$names = ['waow_source-uri', 'waow_position'];

		foreach($names as $name) {
			$option = get_option($name);
			if(is_admin() && !$option)
				die("WAOW: $name was not defined. Add this option under settings or deactivate this plugin. This message is not shown to users.");
			else
				$options[$name] = $option;
		}

		return $options;
	}

	function waow_activate() {
		global $wpdb;
		$wpdb->show_errors();

		Pages::initDatabase();

		$options = waow_get_options();
		$themeDir = get_template_directory();
		$sourceDir = $themeDir . $options['waow_source-uri'];

		//Get the files in the source directory
		$files = scandir($sourceDir);
		foreach($files as $file) {
			$fileExt = pathinfo($file, PATHINFO_EXTENSION);
			if($fileExt != 'php')
				continue;

			$fileWithoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file);
			$postId = -1;

			//Does the file exist in WP?
			if(Pages::WPPageExists($fileWithoutExt)) {
				//If the page exists, get its ID. Re-use it.
				$postId = Pages::getWPPageByWAOWPageName("ID", $fileWithoutExt);
			}
			else {
				//If the page doesn't exist, create it and keep the ID.
				$post = array(
					'post_title' => ucfirst(str_replace("-", " ", $fileWithoutExt)), //the post title should have a capital letter and replace - with a space.
					'post_name' => $fileWithoutExt,
					'post_content' => 'You can edit this text using Wordpress.',
					'post_status' => 'publish',
					'post_date' => date('Y-m-d H:i:s'),
					'post_author' => get_current_user_id(),
					'post_type' => 'page',
					'post_category' => array(0)
				);
				$postId = wp_insert_post($post);
			}

			//With each Post ID, add the page to the database.
			if($post === 0 && is_admin()) {
				die("WAOW: Couldn't insert page. Name: $fileWithoutExt. This message is not shown to users.");
			}
			else {
				$xml = simplexml_load_file($sourceDir . 'config.xml');
				$pageXml = Pages::getConfigPageElement($xml->asXml(), $fileWithoutExt);

				//Does the WAOW Page already exist?
				if(Pages::WAOWPageExists($fileWithoutExt)) {
					//The page exists. Update it's info to make sure its current.
					$wpdb->update(
						$wpdb->prefix . 'waow_pages',
						array(
							'wp_id' => $postId,
							'loginRequired' => $pageXml['loginRequired'][0] === 'true',
							'role' => $pageXml['role'][0]
						),
						array(
							'name' => $fileWithoutExt
						)
					);
				}
				else {
					//The page doesn't exist. Insert it.
					$wpdb->insert(
						$wpdb->prefix . 'waow_pages',
						array(
							'name' => $post['post_name'][0],
							'wp_id' => $postId,
							'loginRequired' => $pageXml['loginRequired'][0] === 'true',
							'role' => $pageXml['role'][0]
						)
					);
				}
			}
		}
	}
	register_activation_hook(__FILE__, 'waow_activate');

	function waow_deactivate() {
		global $wpdb;
		$table_name = $wpdb->prefix . "waow_pages";
		$sql = "DROP TABLE ". $table_name;
		$wpdb->query($sql);
	}
	//register_deactivation_hook(__FILE__, 'waow_deactivate');

	function waow_the_content_filter($content, $request) {
		waow_activate();

		$target = "";
		$post = get_post();

		if(!empty($request['target'])) {
			$target = $request['target'];
			$postId = Pages::getPostIdByWAOWPageName($target);
			$post = get_post($postId);
		}
		else {
			$target = Pages::getWAOWPageNameByPostId($post->ID);			
		}

		$options = waow_get_options();

		//Optionally output the content
		if($options['waow_position'] == 'top')
			echo $post->post_content;

		//Get the source directory
		$sourceDir = get_template_directory() . $options['waow_source-uri'];
		$targetUri = $sourceDir . $target . '.php';
		if(file_exists($targetUri))
			require_once($targetUri);

		if(get_option('waow_position') == 'bottom')
			echo $post->post_content;
	}
	add_filter( 'the_content', function( $content ) use ( $_REQUEST ) {
		//http://wordpress.stackexchange.com/questions/45901/passing-a-parameter-to-filter-and-action-functions
		waow_the_content_filter($content, $_REQUEST);
	});