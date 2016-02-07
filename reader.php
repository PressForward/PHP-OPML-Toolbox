<?php

class OPML_reader {

	/**
	 * OPML file url/path
	 * @var string
	 */
	public $file_url = '';

	/**
	 * Parsed OPML file data
	 * @var SimpleXMLObject
	 */
	public $opml_file;

	/**
	 * Construct an OPML_Reader object
	 *
	 * Accepts a file url string and opens the OPML file for reading
	 *
	 * @param string $file url
	 */
	function __construct( $url = '' ) {
		$this->file_url = $url;

		$this->open_file();
		// $this->get_OPML_obj();
	}


	/**
	 * Opens the OPML file
	 *
	 * Uses the object's $url property to pull down and get the
	 * OPML file data, assigned to $opml_file
	 */
	function open_file() {
		if ( empty( $this->file_url ) ) {
			return;
		}

		if ( 1 == ini_get( 'allow_url_fopen' ) ) {
			$file = simplexml_load_file( $this->file_url );
		} else {
			$response = file_get_contents( $this->file_url);

			if ( is_wp_error( $response ) ) {
				$this->opml_file = false;
				return;
			}

			$file = simplexml_load_string( wp_remote_retrieve_body( $response ) );
		}

		if ( empty( $file ) ) {
			$file = false;
		}

		$this->opml_file = $file;
	}

	/**
	 * Retrieves the OPML_Object from the provided url
	 *
	 * @return OPML_Object
	 */
	public function get_OPML_obj() {

		$this->opml = new OPML_Object( $this->file_url );
		$this->opml->set_title( (string) $this->opml_file->head->title );

		foreach ( $this->opml_file->body->outline as $folder ) {
			$this->make_OPML_obj( $folder );
		}

		return $this->opml;
	}

	/**
	 * Recursively builds the OPML_Object for each folder
	 *
	 * @param  SimpleXMLObject         $entry
	 * @param  boolean|SimpleXMLObject $parent
	 */
	public function make_OPML_obj( $entry, $parent = false ) {

		$entry_a = $this->get_opml_properties($entry);
		if ( isset($entry_a['xmlUrl']) ){
			$feed_obj = $this->opml->make_a_feed_obj($entry_a);
			$this->opml->set_feed($feed_obj, $parent);
		} else {
			$folder_obj = $this->opml->make_a_folder_obj($entry_a);
			$this->opml->set_folder($folder_obj);
			foreach ($entry as $feed){
				$this->make_OPML_obj($feed, $folder_obj);
			}
		}
	}

	/**
	 * Builds the SimpleXMLObject's attributes into an array
	 *
	 * @param  SimpleXMLObject $simple_xml_obj
	 * @return array
	 */
	public function get_opml_properties( $simple_xml_obj ) {
		$obj = $simple_xml_obj->attributes();
		$array = array();
		foreach ($obj as $key=>$value){
			$array[$key] = (string) $value;
		}
		return $array;
	}

	function open_OPML($file) {
		if(1 == ini_get('allow_url_fopen')){
            $file = simplexml_load_file($file);
		} else {
			$ch = curl_init();
			$timeout = 5;
			curl_setopt($ch, CURLOPT_URL, $file);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			$data = curl_exec($ch);
			curl_close($ch);
			$file = simplexml_load_string($data);
		}
		if (empty($file)) {
            pf_log('Received an empty file.');
			return false;
		} else {
            pf_log('Received:');
            pf_log($file);
			$opml_data = $file;
			return $opml_data;
		}
	}


	# Pass the URL and if you want to return an array of objects or of urls.
	# @todo remove this function
	function get_OPML_data($url, $is_array = true){
		$opml_data = $this->open_OPML($url);

		if (!$opml_data || empty($opml_data)){

			return false;
		}

		//Site data
		$a = array();
		//Feed URI
		$b = array();
		$c = 0;

		/** Get XML data:
		  * supplies:
		  * [text] - Text version of title
		  * [text] - Text version of title
		  * [type] - Feed type (should be rss)
		  * [xmlUrl] - location of the RSS feed on the site.
		  * [htmlUrl] - The site home URI.
		**/
		foreach ($opml_data->body->outline as $folder){

			# Check if there are no folders.
			if (isset($folder['xmlUrl'])){
				$b[] = $folder['xmlUrl']->__toString();
			}

			foreach ($folder->outline as $data){
				$a[] = reset($data);
			}
			// Pulls out the feed location.
			foreach ($a as $outline) {
				$b[] = $outline['xmlUrl'];
			}

		}
		if ($is_array){
			return $b;
		} else {
			return $a;
		}

	}

}