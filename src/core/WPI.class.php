<?php
	//! Wordpress Interface
	/*!
		WPI is used to interface between Wordpress and the rest of Meggaflick. It's used to get system pages and process other system page information, such as authentication.
		DBI::setupDatabase() must be called before creating an instance of this class, since it will populate the database with tables needed for this class to function.
	*/
	class WPI {
		private $systemPages = null;
		private $authError = "";

		/**
			Set the system timezone and load WPI::systemPages from the database.
		*/
		public function __construct() {
			//date_default_timezone_set('Asia/Tokyo');

			$dbi = new DBI();
			$result = $dbi->select("Page", array("name", "wp_id", "loginRequired", "role"));
			$dbi->close();

			if($result->STATUS == DBI::RESULT_SUCCESS) {
				$this->systemPages = $result->VALUE;
			}
			else {
				die("Couldn't load Pages. Error: " . $result->VALUE);
			}
		}

		/**
			Returns a string containing the authentication error text. This will be an empty string if WPI::authenticate has not failed for this instance of WPI.
			@return String The Authentication error text.
		*/
		public function getAuthError() {
			return $this->authError;
		}

		/**
			Returns an array of system pages required by the web application.
			@return A nested array of system pages. Each element will contain (up to) three elements; name, title and loginRequired.
		*/
		public function getSystemPages() {
			return $this->systemPages;
		}

		/**
			Returns an array representing the requested system page, identified by its name.
			@param pageName String A string value indicating the name of the requested page.
			@return Array An array that describes the current page. Null if the page wasn't found.
		*/
		public function getSystemPageByName($pageName) {
			foreach($this->systemPages as $page) {
				if($page["name"] == $pageName)
					return $page;
			}

			return null;
		}

		/**
			Returns an array representing the requested system page, identified by its Wordpress ID.
			@param wp_id Int An int value indicating the ID of the requested page.
			@return Array An array that describes the current page. Null if the page wasn't found.
		*/
		public function getSystemPageById($wp_id) {
			foreach($this->systemPages as $page) {
				if($page["wp_id"] == $wp_id)
					return $page;
			}

			return null;
		}

		/**
			Returns an array, formatted for use with the wp_insert_post function.
			@param userID The ID of the user, retrieved by the Wordpress global $user_ID variable.
			@param page An element of WPI::getSystemPages to insert.
			@return An array to use with the wordpress wp_insert_post method. 
		*/
		public function getWPInsertObject($userID, $page) {
			return array(
				'post_title' => ucfirst(str_replace("-", " ", $page["name"])), //the post title should have a capital letter and replace - with a space.
				'post_name' => $page["name"],
				'post_content' => 'You can edit this text using Wordpress.',
				'post_status' => 'publish',
				'post_date' => date('Y-m-d H:i:s'),
				'post_author' => $userID,
				'post_type' => 'page',
				'post_category' => array(0)
			);
		}

		/**
			Returns a boolean value indicating whether or not $wp_id is a required system page.
			@param wp_id Int An int value indicating the Wordpress ID of the current page.
			@return A boolean indicating whether or not the page indicated by @wp_id is a system page.
		*/
		public function isSystemPage($wp_id) {
			foreach($this->systemPages as $page) {
				if($page["wp_id"] == $wp_id)
					return true;
			}

			return false;
		}

		/**
			Returns a boolean value indicating whether or not $wp_id requires the user to be logged in.
			@param wp_id Int An int value indicating the wp_id of the current page.
			@return A boolean value indicating whether or not the page indicated by @wp_id requires the user to be logged in to view it.
		*/
		public function isLoginRequired($wp_id) {
			//echo "WPI::isLoginRequired<br/>";

			foreach($this->systemPages as $page) {
				//echo "wp_id: " . $page["wp_id"] . ", loginRequired: " . $page["loginRequired"] . "<br/>";

				if($page["wp_id"] == $wp_id && $page["loginRequired"])
					return true;
			}

			return false;
		}

		/**
			Determines if the user can access a given system page, given the page wp_id and their type.
			@param userType Int One of User::USER_TYPE_CUSTOMER or User::USER_TYPE_PRODUCER. 
			@param wp_id Int An int value indicating the wp_id of the page.
			@return boolean False if the user role cannot access the page. True otherwise, including if the page was not found or if no role is set for the page.
		*/
		public function canUserAccessPage($userType, $wp_id) {
			foreach($this->systemPages as $page) {
				if($page["wp_id"] == $wp_id) {
					return $page["role"] == $userType || $page["role"] == User::USER_TYPE_ANY;
				}
			}

			return true;
		}

		/**
			This method displays an error message, back button and kills the script execution.
			@param errorText String An error to display to the user.
		*/
		public function showError($errorText) {
			echo "<html><body>
					$errorText
					<br/><br/>

					<a href = 'javascript:window.history.back();'>Back</a>
				";

			exit;
		}

		/**
			Performs authentication and access control checks against the current Wordpress page for the specified 
			user. Use WPI::getAuthText to retrieve the error message associated with a failed call to this method.

			@param user User A User object (or one of its children). This object does not need to be pre-loaded. Null may be passed to represent a non-logged in user.
			@param post WP_POST A Wordpress post object (or a similar object which contains an ID and post_title attribute).
			@param rosetta rosetta A rosetta instance for dynamic text
			@return Boolean True if authentication passed. False otherwise.
		*/
		public function authenticate($user, $post, $rosetta) {
	    	$pageTitle = $post->post_title;

	    	//Login required
	    	if($this->isLoginRequired($post->ID)) {
	    		if($user == null) {
					$this->authError = $rosetta->getString("loginRequired", array($pageTitle));
	            	return false;
	         	}
	       	}

	       	//Role required
	       	if($user != null) {
	        	$user->load();

		        if(!$this->canUserAccessPage($user->getType(), $post->ID)) {
		           	$errorText = $rosetta->getString("accessDeniedForRole");

		            $page = $this->getSystemPageById($post->ID);

		            //echo "Required type: " . $page["role"];

		            if($page["role"] == User::USER_TYPE_CUSTOMER)
		            	$errorText .= $rosetta->getString("customers");
		            else if($page["role"] == User::USER_TYPE_PRODUCER)
		            	$errorText .= $rosetta->getString("producers");

		        	$this->authError = $errorText;
		        	return false;
		        }
	   		}

	   		$this->authError = "";	//if successful, clear any previous auth errors.
	   		return true;
		}
	}