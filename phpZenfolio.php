<?php 
/** 
 * phpZenfolio - phpZenfolio is a PHP wrapper class for the Zenfolio API. The intention
 *		         of this class is to allow PHP application developers to quickly
 *			     and easily interact with the Zenfolio API in their applications,
 *			     without having to worry about the finer details of the API.
 *
 * @author Colin Seymour <lildood@gmail.com>
 * @version 0.1
 * @package phpZenfolio
 * @license GPL 3 {@link http://www.gnu.org/copyleft/gpl.html}
 *
 * Released under GNU General Public License Version 3({@link http://www.gnu.org/copyleft/gpl.html})
 *
 * For more information about the class and upcoming tools and toys using it,
 * visit {@link http://phpzenfolio.com/}.
 *
 * For installation and usage instructions, open the README.txt file 
 * packaged with this class. If you don't have a copy, you can refer to the
 * documentation at:
 * 
 *          {@link http://phpzenfolio.com/docs/}
 * 
 * phpZenfolio is based on the worked I have done in phpSmug ({@link http://phpsmug.com}).
 **/

/** 
 * Decide which include path delimiter to use.  Windows should be using a semi-colon
 * and everything else should be using a colon.  If this isn't working on your system,
 * comment out this if statement and manually set the correct value into $path_delimiter.
 * 
 * @var string
 **/
$path_delimiter = ( strpos( __FILE__, ':' ) !== false ) ? ';' : ':';

/**
 *  This will add the packaged PEAR files into the include path for PHP, allowing you
 * to use them transparently.  This will prefer officially installed PEAR files if you
 * have them.  If you want to prefer the packaged files (there shouldn't be any reason
 * to), swap the two elements around the $path_delimiter variable.  If you don't have
 * the PEAR packages installed, you can leave this like it is and move on.
 **/
//ini_set( 'include_path', ini_get( 'include_path' ) . $path_delimiter . dirname( __FILE__ ) . '/PEAR' );
ini_set( 'include_path', dirname( __FILE__ ) . '/PEAR' . $path_delimiter . ini_get( 'include_path' ) );

/**
 * Forcing a level of logging to the highest level. We want phpZenfolio to not
 * report a single error or warning.
 **/
error_reporting( E_STRICT );

/**
 * phpZenfolio - all of the phpZenfolio functionality is provided in this class
 *
 * @package phpZenfolio
 **/
class phpZenfolio {
	var $version = '0.1';
	var $cacheType = FALSE;
	var $cache_expire = 3600;
	var $authToken;
	var $id;

	/**
	 * phpZenfolio uses the HTTP::Request2 module for communication with Zenfolio.
	 * This PEAR module supports 3 adapters: socket (default), curl and mock.
	 * This option allows application developers to easily over-ride this and
	 * select their own adapter.
	 *
	 * @var string
	 **/
	var $adapter = 'socket';
	
	/**
     * When your database cache table hits this many rows, a cleanup
     * will occur to get rid of all of the old rows and cleanup the
     * garbage in the table.  For most personal apps, 1000 rows should
     * be more than enough.  If your site gets hit by a lot of traffic
     * or you have a lot of disk space to spare, bump this number up.
     * You should try to set it high enough that the cleanup only
     * happens every once in a while, so this will depend on the growth
     * of your table.
     * 
     * @var integer
     **/
    var $max_cache_rows = 1000;
	
	/**
	 * Constructor to set up a phpZenfolio instance.
	 * 
	 * The Application Name (AppName) is not obligatory, but it helps 
	 * Zenfolio diagnose any problems users of your application may encounter.
	 * If you're going to use this, please use a string and include your
	 * version number and URL as follows.
	 * For example "My Cool App/1.0 (http://my.url.com)"
	 *
     * 
     * By default phpZenfolio will use the latest stable API endpoint, but
     * you can over-ride this when instantiating the instance.
	 *
	 * @return void
	 * @param string $AppName (Optional) Name and version information of your application in the form "AppName/version (URI)" e.g. "My Cool App/1.0 (http://my.url.com)".  This isn't obligatory, but it helps Zenfolio diagnose any problems users of your application may encounter.
	 * @param string $APIVer (Optional) API endpoint you wish to use. Defaults to 1.4
	 **/
	function __construct()
	{
		$args = phpZenfolio::processArgs(func_get_args());
        //$this->APIKey = $args['APIKey'];
		$this->APIVer = ( array_key_exists( 'APIVer', $args ) ) ? $args['APIVer'] : '1.4';
		
		// Set the Application Name
		$this->AppName = ( array_key_exists( 'AppName', $args ) ) ?  $args['AppName'] : 'Unknown Application';

        // All calls to the API are done via the POST method using the PEAR::HTTP_Request2 package.
		require_once 'HTTP/Request2.php';
		$this->req = new HTTP_Request2();
		$this->req->setConfig( array( 'follow_redirects' => TRUE, 'max_redirects' => 3 ) );
        $this->req->setMethod( HTTP_Request2::METHOD_POST );
		$this->req->setHeader( array( 'User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									  'X-Zenfolio-User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									  'Content-Type' => 'application/json' ) );
		$this->req->setAdapter( $this->adapter );
    }
	
	/**
	 * General debug function used for testing and development of phpZenfolio.
	 *
	 * Feel free to use this in your own application development.
	 *
	 * @return string
	 * @param mixed $var Any string, object or array you want to display
	 * @static
	 **/
	public static function debug( $var )
	{
		echo '<pre>Debug:';
		if ( is_array( $var ) || is_object( $var ) ) { print_r( $var ); } else { echo $var; }
		echo '</pre>';	
	}
	
	/**
	 * Function enables caching.
	 *
	 * @access public
	 * @return TRUE|string Returns TRUE is caching is enabled successfully, else returns an error and disable caching.
	 * @param string $type The type of cache to use. It must be either "db" (for database caching) or "fs" (for filesystem).
	 * @param string $dsn When using type "db", this must be a PEAR::DB connection string eg. "mysql://user:password@server/database".  When using type "fs", this must be a folder that the web server has write access to. Use absolute paths for best results.  Relative paths may have unexpected behavior when you include this.  They'll usually work, you'll just want to test them.
	 * @param string $cache_dir When using type "fs". this is the directory to use for caching. This directory must exist.
	 * @param integer $cache_expire Cache timeout in seconds. This defaults to 3600 seconds (1 hour) if not specified.
	 * @param string $table If using type "db", this is the database table name that will be used.  Defaults to "smugmug_cache".
	 **/
	public function enableCache()
	{
		$args = phpZenfolio::processArgs(func_get_args());
		$this->cacheType = $args['type'];
        
		$this->cache_expire = (array_key_exists('cache_expire', $args)) ? $args['cache_expire'] : '3600';
		$this->cache_table  = (array_key_exists('table', $args)) ? $args['table'] : 'smugmug_cache';

        if ($this->cacheType == 'db') {
    		require_once 'DB.php';
	        $db = DB::connect($args['dsn']);
			if (PEAR::isError($db)) {
				$this->cacheType = FALSE;
				return "CACHING DISABLED: {$db->getMessage()} ({$db->getCode()})";
			}
			$this->cache_db = $db;
            
            /*
             * If high performance is crucial, you can easily comment
             * out this query once you've created your database table.
             */
            $db->query("
                CREATE TABLE IF NOT EXISTS `$this->cache_table` (
                    `request` CHAR( 35 ) NOT NULL ,
                    `response` LONGTEXT NOT NULL ,
                    `expiration` DATETIME NOT NULL ,
                    INDEX ( `request` )
                ) TYPE = MYISAM");

            if ($db->getOne("SELECT COUNT(*) FROM $this->cache_table") > $this->max_cache_rows) {
                $db->query("DELETE FROM $this->cache_table WHERE expiration < DATE_SUB(NOW(), INTERVAL $this->cache_expire SECOND)");
                $db->query('OPTIMIZE TABLE ' . $this->cache_table);
            }

        } elseif ($this->cacheType ==  'fs') {
			if (file_exists($args['cache_dir']) && (is_dir($args['cache_dir']))) {
				$this->cache_dir = realpath($args['cache_dir']).'/phpZenfolio/';
				if (is_writeable(realpath($args['cache_dir']))) {
					if (!is_dir($this->cache_dir)) {
						mkdir($this->cache_dir, 0755);
					}
					$dir = opendir($this->cache_dir);
                	while ($file = readdir($dir)) {
                    	if (substr($file, -2) == '.cache' && ((filemtime($this->cache_dir . '/' . $file) + $this->cache_expire) < time()) ) {
                        	unlink($this->cache_dir . '/' . $file);
                    	}
                	}
				} else {
					$this->cacheType = FALSE;
					return "CACHING DISABLED: Cache Directory \"".$args['cache_dir']."\" is not writeable.";
				}
			} else 	{
				$this->cacheType = FALSE;
				return "CACHING DISABLED: Cache Directory \"".$args['cache_dir']."\" doesn't exist, is a file or is not readable.";
			}
		}
		return TRUE;
    }

	/**
	 * 	Checks the database or filesystem for a cached result to the request.
	 *
	 * @access private
	 * @return string|FALSE Unparsed serialized PHP, or FALSE
	 * @param array $request Request to the SmugMug created by one of the later functions in phpZenfolio.
	 **/
    private function getCached( $request )
	{
		$request['authToken']       = ''; // Unset authToken
		$request['oauth_nonce']     = '';     // --\
		$request['oauth_signature'] = '';  //    |-Unset OAuth info
		$request['oauth_timestamp'] = ''; // --/
       	$reqhash = md5(serialize($request));
		$expire = (strpos($request['method'], 'login.with')) ? 21600 : $this->cache_expire;
        if ($this->cacheType == 'db') {
            $result = $this->cache_db->getOne('SELECT response FROM ' . $this->cache_table . ' WHERE request = ? AND DATE_SUB(NOW(), INTERVAL ' . (int) $expire . ' SECOND) < expiration', $reqhash);
			if (!empty($result)) {
                return $result;
            }
        } elseif ($this->cacheType == 'fs') {
            $file = $this->cache_dir . '/' . $reqhash . '.cache';
			if (file_exists($file) && ((filemtime($file) + $expire) > time()) ) {
					return file_get_contents($file);
            }
        }
    	return FALSE;
    }

	/**
	 * Caches the unparsed serialized PHP of a request. 
	 *
	 * @access private
	 * @return null|false
	 * @param array $request Request to the SmugMug created by one of the later functions in phpZenfolio.
	 * @param string $response Response from a successful request() method call.
	 **/
    private function cache( $request, $response )
	{
		$request['authToken']       = ''; // Unset authToken
		$request['oauth_nonce']     = ''; // --\
		$request['oauth_signature'] = ''; //    |-Unset OAuth info
		$request['oauth_timestamp'] = ''; // --/
		if (! strpos($request['method'], '.auth.')) {
			$reqhash = md5(serialize($request));
			if ($this->cacheType == 'db') {
				if ($this->cache_db->getOne("SELECT COUNT(*) FROM {$this->cache_table} WHERE request = '$reqhash'")) {
					$sql = 'UPDATE ' . $this->cache_table . ' SET response = ?, expiration = ? WHERE request = ?';
					$this->cache_db->query($sql, array($response, strftime('%Y-%m-%d %H:%M:%S'), $reqhash));
				} else {
					$sql = "INSERT INTO " . $this->cache_table . " (request, response, expiration) VALUES ('$reqhash', '" . strtr($response, "'", "\'") . "', '" . strftime("%Y-%m-%d %H:%M:%S") . "')";
					$this->cache_db->query($sql);
				}
			} elseif ($this->cacheType == 'fs') {
				$file = $this->cache_dir . '/' . $reqhash . '.cache';
				$fstream = fopen($file, 'w');
				$result = fwrite($fstream,$response);
				fclose($fstream);
				return $result;
			}
		}
        return FALSE;
    }

	/**
	 *  Forcefully clear the cache.
	 *
	 * This is useful if you've made changes to your SmugMug galleries and want
	 * to ensure the changes are reflected by your application immediately.
	 *
	 * @access public
	 * @param boolean $delete Set to TRUE to delete the cache after clearing it
	 * @return string|TRUE
	 * @since 1.1.7
	 **/
    public function clearCache( $delete = FALSE )
	{
   		if ($this->cacheType == 'db') {
			if ($delete) {
				$result = $this->cache_db->query('DROP TABLE ' . $this->cache_table);
			} else {
				$result = $this->cache_db->query('TRUNCATE ' . $this->cache_table);
			}
	   	} elseif ($this->cacheType == 'fs') {
            $dir = opendir($this->cache_dir);
	       	if ($dir) {
				foreach (glob($this->cache_dir."/*.cache") as $filename) {
					$result = unlink($filename);
				}
	       	}
			closedir($dir);
			if ($delete) {
				$result = rmdir($this->cache_dir);
			}
	   	}
		return (bool) $result;
	}

	/**
	 * 	Sends a request to Zenfolio's API endpoint via POST. If we're calling
	 *  one of the authenticate* methods, we'll use the HTTPS end point to ensure
	 *  things are secure by default
	 *
	 * @access private
	 * @return string JSON response from Zenfolio, or an error.
	 * @param string $command Zenfolio API method to call in the request
	 * @param array $args optional Array of arguments that form the API call
	 * @param boolean $nocache Set whether the call should be cached or not. This isn't actually used, so may be deprecated in the future.
	 **/
	private function request( $command, $args = array(), $nocache = FALSE )
	{
		if ( $command == 'AuthenticatePlain' ) {
			$proto = "https";
		} else {
			$proto = "http";
		}

		$this->req->setURL( "$proto://www.zenfolio.com/api/{$this->APIVer}/zfapi.asmx" );

		if ( ! is_null( $this->authToken ) ) {
			$this->req->setHeader( 'X-Zenfolio-Token', $this->authToken );
		}
		
		// To keep things unique, we set the ID to a base32 figure of the string concat of the method and all arguments
		$str = $command . '.' . join( '.', $args );
		$this->id = intval( $str, 32 );
		$args = array( 'method' => $command, 'params' => $args, 'id' => $this->id );

        if ( !( $this->response = $this->getCached( $args ) ) || $nocache ) {
			$this->req->setBody( json_encode( $args ) );
			try {
				$response = $this->req->send();
				if ( 200 == $response->getStatus() ) {
					$this->response = $response->getBody();
					$this->cache( $args, $this->response );
				} else {
					$msg = 'Request failed. HTTP Reason: '.$this->req->getReasonPhrase();
					$code = $this->req->getStatus();
					throw new Exception( $msg, $code );
				}
			}
			catch ( HTTP_Request2_Exception $e ) {
				throw new Exception( $e );
			}
		}
		$this->parsed_response = json_decode( $this->response, true );
		if ( $this->parsed_response['id'] != $this->id ) {
			$this->error_msg = "Incorrect response ID. (request ID: {$this->id}, response ID: {$this->parsed_response['id']}";
			$this->parsed_response = FALSE;
			throw new Exception( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		}
		if ( ! is_null( $this->parsed_response['error'] ) ) {
			$this->error_code = $this->parsed_response['code'];
            $this->error_msg = $this->parsed_response['error']['message'];
			$this->parsed_response = FALSE;
			throw new Exception( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		} else {
			$this->error_code = FALSE;
            $this->error_msg = FALSE;
		}

		return $this->response;
    }
	
	/**
	 * Set a proxy for all phpZenfolio calls
	 *
	 * @access public
	 * @return void
	 * @param string $server Proxy server
	 * @param integer $port Proxy server port
	 **/
    public function setProxy()
	{
		$args = phpZenfolio::processArgs(func_get_args());
		$this->proxy['server'] = $args['server'];
		$this->proxy['port'] = $args['port'];
		$this->req->setProxy($args['server'], $args['port']);
    }
 
	/**
	 * Single login function for all login methods.
	 * 
	 * I've created this function to make it easy to login to Zenfolio using either
	 * the plaintext authentication method, or the more secure challenge-response (default)
	 * authentication method.
	 *
	 * @access public
	 * @return string
	 * @param string $username The Zenfolio username
	 * @param string $password The Zenfolio username's password
	 * @param boolean $plaintext (Optional) Set whether the login should use the plaintext (TRUE) the challenge-response authentication method (FALSE). Defaults to FALSE.
	 * @uses request
	 */
	public function login( $username, $password, $plaintext = FALSE )
	{
		if ( $plaintext ) {
			$this->authToken = $this->AuthenticatePlain( $username, $password );
		} else {
			$cr = $this->GetChallenge( $username );
			$salt = self::byteArrayDecode( $cr['PasswordSalt'] );
			$challenge = self::byteArrayDecode( $cr['Challenge'] );
			$password = utf8_encode( $password );

			$passHash = hash( 'sha256', $salt.$password, TRUE );
			$chalHash = hash( 'sha256', $challenge.$passHash, TRUE );
			$proof = array_values( unpack( 'C*', $chalHash ) );
			$this->authToken = $this->Authenticate( $cr['Challenge'] , $proof );
		}
		return $this->authToken;
	}
	
	/**
	 * 	I break away from the standard API here as recommended by SmugMug at
	 * {@link http://wiki.smugmug.com/display/SmugMug/smugmug.images.upload+1.2.0}.
	 *
	 * I've chosen to go with the HTTP PUT method as it is quicker, simpler
	 * and more reliable than using the API or POST methods.
	 * 
	 * @access public
	 * @return array|false
	 * @param integer $AlbumID The AlbumID the image is to be uploaded to
	 * @param string $File The path to the local file that is being uploaded
	 * @param string $FileName (Optional) The filename to give the file on upload
	 * @param mixed $arguments (Optional) Additional arguments. See SmugMug API documentation.
	 * @uses request
	 * @link http://wiki.smugmug.com/display/SmugMug/Uploading 
	 **/
	public function images_upload()
	{
		$args = phpZenfolio::processArgs(func_get_args());
		if (!array_key_exists('File', $args)) {
			throw new Exception('No upload file specified.');
		}
		
		// Set FileName, if one isn't provided in the method call
		if (!array_key_exists('FileName', $args)) {
			$args['FileName'] = basename($args['File']);
		}

		// Ensure the FileName is phpZenfolio::urlencodeRFC3986 encoded - caters for stange chars and spaces
		$args['FileName'] = phpZenfolio::urlencodeRFC3986($args['FileName']);

		// OAuth Stuff
		if ($this->OAuthSecret) {
			$sig = $this->generate_signature('Upload', array('FileName' => $args['FileName']));
		}
		
		if (is_file($args['File'])) {
			$fp = fopen($args['File'], 'r');
			$data = fread($fp, filesize($args['File']));
			fclose($fp);
		} else {
			throw new Exception("File doesn't exist: {$args['File']}");
		}

		$upload_req = new HTTP_Request();
        $upload_req->setMethod(HTTP_REQUEST_METHOD_PUT);
		$upload_req->setHttpVer(HTTP_REQUEST_HTTP_VER_1_1);
		
		// Set the proxy if one has been set earlier
		if (isset($this->proxy) && is_array($this->proxy)) {
			$upload_req->setProxy($this->proxy['server'], $this->proxy['port']);
		}
		$upload_req->clearPostData();

		$upload_req->addHeader('User-Agent', "{$this->AppName} using phpZenfolio/{$this->version}");
		$upload_req->addHeader('Content-MD5', md5_file($args['File']));
		$upload_req->addHeader('Connection', 'keep-alive');

		if ($this->loginType == 'authd') { 
			$upload_req->addHeader('X-Zenfolio-Token', $this->authToken);
		} else {
			$upload_req->addHeader('Authorization', 'OAuth realm="http://api.smugmug.com/",
				oauth_consumer_key="'.$this->APIKey.'",
				oauth_token="'.$this->oauth_token.'",
				oauth_signature_method="'.$this->oauth_signature_method.'",
				oauth_signature="'.urlencode($sig).'",
				oauth_timestamp="'.$this->oauth_timestamp.'",
				oauth_version="1.0",
				oauth_nonce="'.$this->oauth_nonce.'"');
		}
			
		$upload_req->addHeader('X-Smug-Version', $this->APIVer);
		$upload_req->addHeader('X-Smug-ResponseType', 'PHP');
		$upload_req->addHeader('X-Smug-AlbumID', $args['AlbumID']);
		$upload_req->addHeader('X-Smug-Filename', basename($args['FileName'])); // This is actually optional, but we may as well use what we're given
		
		/* Optional Headers */
		(isset($args['ImageID'])) ? $upload_req->addHeader('X-Smug-ImageID', $args['ImageID']) : false;
		(isset($args['Caption'])) ? $upload_req->addHeader('X-Smug-Caption', $args['Caption']) : false;
		(isset($args['Keywords'])) ? $upload_req->addHeader('X-Smug-Keywords', $args['Keywords']) : false;
		(isset($args['Latitude'])) ? $upload_req->addHeader('X-Smug-Latitude', $args['Latitude']) : false;
		(isset($args['Longitude'])) ? $upload_req->addHeader('X-Smug-Longitude', $args['Longitude']) : false;
		(isset($args['Altitude'])) ? $upload_req->addHeader('X-Smug-Altitude', $args['Altitude']) : false;

		$proto = ($this->oauth_signature_method == 'PLAINTEXT') ? 'https' : 'http';
		$upload_req->setURL($proto . '://upload.smugmug.com/'.$args['FileName']);

		$upload_req->setBody($data);

        //Send Requests - HTTP::Request doesn't raise Exceptions, so we must
		$response = $upload_req->sendRequest();
		if(!PEAR::isError($response) && ($upload_req->getResponseCode() == 200)) {
			$this->response = $upload_req->getResponseBody();
		} else {
			if ($upload_req->getResponseCode() && $upload_req->getResponseCode() != 200) {
				$msg = 'Upload failed. HTTP Reason: '.$upload_req->getResponseReason();
				$code = $upload_req->getResponseCode();
			} else {
				$msg = 'Upload failed: '.$response->getMessage();
				$code = $response->getCode();
			}
			throw new Exception($msg, $code);
		}
		
		// For some reason the return string is formatted with \n and extra space chars.  Remove these.
		$replace = array('\n', '\t', '  ');
		$this->response = str_replace($replace, '', $this->response);
		$this->parsed_response = unserialize($this->response);
		
		if ($this->parsed_response['stat'] == 'fail') {
			$this->error_code = $this->parsed_response['code'];
            $this->error_msg = $this->parsed_response['message'];
			$this->parsed_response = FALSE;
			throw new Exception("SmugMug API Error for method image_upload: {$this->error_msg}", $this->error_code);
		} else {
			$this->error_code = FALSE;
            $this->error_msg = FALSE;
		}
		return $this->parsed_response ? $this->parsed_response['Image'] : FALSE;
	}
	
	/**
	 * Dynamic method handler.  This function handles all SmugMug method calls
	 * not explicitly implemented by phpZenfolio.
	 * 
 	 * @access public
	 * @return array|string|TRUE
	 * @uses request
	 * @param string $method The SmugMug method you want to call, but with "." replaced by "_"
	 * @param mixed $arguments The params to be passed to the relevant API method. See SmugMug API docs for more details.
	 **/
	public function __call( $method, $arguments )
	{
		//$args = phpZenfolio::processArgs( $arguments );
		$args = $arguments;
		$this->request( $method, $args );
		$result = $this->parsed_response['result'];
		if ( $method == 'AuthenticatePlain' ) {
			$this->authToken = $result;
		}
		return $result;
	}
 
	 /**
	  * Process arguments passed to method
	  *
	  * @static
	  * @return array
	  * @param array Arguments taken from a function by func_get_args()
	  * @access private
	  **/
	 private static function processArgs( $arguments )
	 {
		$args = array();
		foreach ( $arguments as $arg ) {
			if (is_array( $arg ) ) {
				$args = array_merge( $args, $arg );
			} else {
				$args[] = $arg;
			}
		}
		return $args;
	  }

	 /**
	  * Private function that converts the JSON array we recieve in response to
	  * GetChallenge to a string.
	  *
	  * The JSON data returned is actually a byte array, but as JSON has no concept
	  * of a byte array, it's returned as a normal array.  This function converts
	  * this normal array to a string.
	  */
	 public static function byteArrayDecode( $array )
	 {
		return call_user_func_array( pack, array_merge( array( 'C*' ),(array) $array ) );
	 }
}
?>
