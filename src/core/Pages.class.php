<?php
	class Pages {
	 	public static function xmlToArray($str) {
	    	return Pages::parseHelper(new SimpleXmlIterator($str, null));
	  	}

	  	private static function parseHelper($iter) {
	    	foreach($iter as $key=>$val)
	      		$arr[$key][] = ($iter->hasChildren())?
	        		Pages::parseHelper($val) : strval($val);

	    	return $arr;
	  	}	

		public static function initDatabase() {
			global $wpdb;
			$table_name = $wpdb->prefix . "waow_pages";
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			  id mediumint(9) NOT NULL AUTO_INCREMENT,
			  name tinytext NOT NULL,
			  wp_id int(11) NOT NULL,
			  loginRequired tinyint(1),
			  role text,

			  UNIQUE KEY id (id)
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		
		public static function getConfigPageElement($xml, $name) {
			$arr = Pages::xmlToArray($xml);
			$pages = $arr['pages'][0]['page'];

			foreach($pages as $page) {
				if($page['name'][0] == $name)
					return $page;
			}

			return null;
		}

		public static function getWAOWPageNameByPostId($postId) {
			global $wpdb;
			$table_name = $wpdb->prefix . "waow_pages";

			return $wpdb->get_var("SELECT name FROM $table_name WHERE wp_id = '$postId'");
		}

		public static function getPostIdByWAOWPageName($page) {
			global $wpdb;
			$table_name = $wpdb->prefix . "waow_pages";

			return $wpdb->get_var("SELECT wp_id FROM $table_name WHERE name = '$page'");		
		}

		public static function WPPageExists($page) {
			global $wpdb;
			$table_name = $wpdb->prefix . "posts";

			return $wpdb->get_var("SELECT COUNT(post_title) FROM $table_name WHERE post_name = '$page'") > 0;					
		}

		public static function WAOWPageExists($page) {
			global $wpdb;
			$table_name = $wpdb->prefix . "waow_pages";

			return $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE name = '$page'") > 0;					
		}

		public static function getWPPageByWAOWPageName($attr, $name) {
			global $wpdb;
			$table_name = $wpdb->prefix . "posts";

			return $wpdb->get_var("SELECT $attr FROM $table_name WHERE post_name = '$name'");		
		}
	}