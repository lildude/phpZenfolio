<?php 
/** 
 * phpZenfolio - phpZenfolio is a PHP wrapper class for the Zenfolio API. The intention
 *		         of this class is to allow PHP application developers to quickly
 *			     and easily interact with the Zenfolio API in their applications,
 *			     without having to worry about the finer details of the API.
 *
 * @author Colin Seymour <lildood@gmail.com>
 * @version 1.0r54
 * @package phpZenfolio
 * @license GNU General Public License version 3 {@link http://www.gnu.org/licenses/gpl.html}
 * @copyright Copyright (c) 2010 Colin Seymour
 *
 * This file is part of phpZenfolio.
 *
 * phpZenfolio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * phpZenfolio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with phpZenfolio.  If not, see <http://www.gnu.org/licenses/>.
 *
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
 *
 * Please help support the maintenance and development of phpZenfolio by making
 * a donation ({@link http://phpzenfolio.com/donate}).
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
 * This will add the packaged PEAR files into the include path for PHP, allowing you
 * to use them transparently.  This will prefer the phpZenfolio suplied PEAR files.
 * If you want to prefer the system installed files, swap the two elements around
 * the $path_delimiter variable.  If you don't have, the PEAR packages installed,
 * you can leave this like it is and move on.
 **/
ini_set( 'include_path', dirname( __FILE__ ) . '/PEAR' . $path_delimiter . ini_get( 'include_path' ) );

/**
 * We can't have this E_STRICT as PEAR is still not 100% PHP5 E_STRICT compliant yet.
 **/
error_reporting( E_ERROR );

/**
 * phpZenfolio - all of the phpZenfolio functionality is provided in this class
 *
 * @package phpZenfolio
 **/
class phpZenfolio {
	var $version = '1.0r54';
	var $cacheType = FALSE;
	var $cache_expire = 3600;
	var $authToken;
	var $keyring;
	var $id;

	/**
	 * The Zenfolio API returns error codes as strings.  PHP does NOT support the
	 * use of strings for error codes at this time (http://bugs.php.net/bug.php?id=39615)
	 *
	 * To get around this, I've created the following array of Zenfolio API error
	 * strings to numbers. I've made up these numbers as Zenfolio don't have an
	 * official list.
	 *
	 * The error string is prepended to the message so it can easily be identified.
	 *
	 * @access private
	 */
	private $errCode = array( 'E_ACCOUNTLOCKED' => 90001,
						  'E_CONNECTIONISNOTSECURE' => 90002,
						  'E_DUPLICATEEMAIL' => 90003,
						  'E_DUPLICATELOGINNAME' => 90004,
						  'E_INVALIDCREDENTIALS' => 90005,
						  'E_INVALIDFILEFORMAT' => 90006,
						  'E_INVALIDPARAM' => 90007,
						  'E_FILESIZEQUOTAEXCEEDED' => 90008,
						  'E_NOSUCHOBJECT' => 90009,
						  'E_NOTAUTHENTICATED' => 90010,
						  'E_NOTAUTHORIZED' => 90011,
						  'E_STORAGEQUOTAEXCEEDED' => 90012,
						  'E_UNSPECIFIEDERROR' => 90013 );
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
	 * The Application Name (AppName) is obligatory as it helps Zenfolio identify
	 * your application and diagnose any problems users of your application may encounter.
	 *
	 * Please use a string and include your version number and URL, for example:
	 *
	 *  "My Cool App/1.0 (http://my.url.com)"
	 *
     * By default phpZenfolio will use the latest stable API endpoint, but you
	 * can over-ride this when instantiating the instance.
	 *
	 * @access public
	 * @param string	$AppName Name and version information of your application
	 *					in the form "AppName/version (URI)"
	 *					e.g. "My Cool App/1.0 (http://my.url.com)".
	 * @param string	$APIVer (Optional) API endpoint you wish to use.
	 *					Defaults to 1.4
	 * @return void
	 **/
	public function __construct()
	{
		$args = phpZenfolio::processArgs( func_get_args() );
		$this->APIVer = ( array_key_exists( 'APIVer', $args ) ) ? $args['APIVer'] : '1.4';
		// Set the Application Name
		if ( ! $args['AppName'] ) {
			throw new Exception( 'Application name missing.', -10001 );
		}
		$this->AppName = $args['AppName'];
        // All calls to the API are done via the POST method using the PEAR::HTTP_Request2 package.
		require_once 'HTTP/Request2.php';
		$this->req = new HTTP_Request2();
		$this->req->setConfig( array( 'adapter' => $this->adapter, 'follow_redirects' => TRUE, 'max_redirects' => 3, 'ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE, 'connect_timeout' => 30 ) );
        $this->req->setMethod( HTTP_Request2::METHOD_POST );
		$this->req->setHeader( array( 'User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									  'X-Zenfolio-User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									  'Content-Type' => 'application/json' ) );
    }
	
	/**
	 * General debug function used for testing and development of phpZenfolio.
	 *
	 * Feel free to use this in your own application development.
	 *
	 * @access public
	 * @static
	 * @param mixed		$var Any string, object or array you want to display
	 * @param boolean	$echo Print the output or not.  This is only really used
	 *					for unit testing.
	 * @return string
	 **/
	public static function debug( $var, $echo = TRUE )
	{
		ob_start();
		echo '<pre>Debug:';
		if ( is_array( $var ) || is_object( $var ) ) { print_r( $var ); } else { echo $var; }
		echo '</pre>';
		if ( $echo ) { ob_end_flush(); } else { $out = ob_get_clean(); }
		return $out;
	}
	
	/**
	 * Function enables caching.
	 *
	 * Params can be passed as an associative array or a set of param=value strings.
	 *
	 * phpZenfolio only provides copies of the MDB2_Driver_sqlite (sqlite2) and
	 * MDB2_Driver_mysql drivers for MDB2.  If you need to use a different database
	 * you will need to install the appropriate MDB2 driver.
	 *
	 * @access public
	 * @param string		$type The type of cache to use. It must be either
	 *						"db" (for database caching) or "fs" (for filesystem).
	 * @param string		$dsn When using type "db", this must be a PEAR::MDB2
	 *						connection string eg. "mysql://user:password@server/database".
	 *						This option is not used for type "fs".
	 * @param string		$cache_dir When using type "fs", this is the directory
	 *						to use for caching. This directory must exist and be
	 *						writable by the web server. Use absolute paths for
	 *						best results.  Relative paths may have unexpected
	 *						behavior when you include this.  They'll usually work,
	 *						you'll just want to test them.
	 * @param integer		$cache_expire Cache timeout in seconds. This defaults
	 *						to 3600 seconds (1 hour) if not specified.
	 * @param string		$table If using type "db", this is the database table
	 *						name that will be used.  Defaults to "phpZenfolio_cache".
	 * @return mixed		Returns TRUE if caching is enabled successfully, else
	 *						returns an error and disables caching.
	 **/
	public function enableCache()
	{
		$args = phpZenfolio::processArgs( func_get_args() );
		$this->cacheType = $args['type'];
        
		$this->cache_expire = ( array_key_exists( 'cache_expire', $args ) ) ? $args['cache_expire'] : '3600';
		$this->cache_table  = ( array_key_exists( 'table', $args ) ) ? $args['table'] : 'phpzenfolio_cache';

        if ( $this->cacheType == 'db' ) {
    		require_once 'MDB2.php';

			$db =& MDB2::connect( $args['dsn'] );
			if ( PEAR::isError( $db ) ) {
				$this->cacheType = FALSE;
				return "CACHING DISABLED: {$db->getMessage()} {$db->getUserInfo()} ({$db->getCode()})";
			}
			$this->cache_db = $db;

			$options = array( 'comment' => 'phpZenfolio cache', 'charset' => 'utf8', 'collate' => 'utf8_unicode_ci' );
			$fields = array( 'request' => array( 'type' => 'text', 'length' => '35', 'notnull' => TRUE ),
							 'response' => array( 'type' => 'clob', 'notnull' => TRUE ),
							 'expiration' => array( 'type' => 'integer', 'notnull' => TRUE )
						   );
			$db->loadModule('Manager');
			$db->createTable( $this->cache_table, $fields, $options );
			$db->setOption('idxname_format', '%s'); // Make sure index name doesn't have the prefix
			$db->createIndex( $this->cache_table, 'request', array( 'fields' => array( 'request' => array() ) ) );

            if ( $db->queryOne( "SELECT COUNT(*) FROM $this->cache_table") > $this->max_cache_rows ) {
				$diff = time() - $this->cache_expire;
                $db->exec( "DELETE FROM {$this->cache_table} WHERE expiration < {$diff}" );
                $db->query( 'OPTIMIZE TABLE ' . $this->cache_table );
            }
        } elseif ( $this->cacheType ==  'fs' ) {
			if ( file_exists( $args['cache_dir'] ) && ( is_dir( $args['cache_dir'] ) ) ) {
				$this->cache_dir = realpath( $args['cache_dir'] ).'/phpZenfolio/';
				if ( is_writeable( realpath( $args['cache_dir'] ) ) ) {
					if ( !is_dir( $this->cache_dir ) ) {
						mkdir( $this->cache_dir, 0755 );
					}
					$dir = opendir( $this->cache_dir );
                	while ( $file = readdir( $dir ) ) {
                    	if ( substr( $file, -6 ) == '.cache' && ( ( filemtime( $this->cache_dir . '/' . $file ) + $this->cache_expire ) < time() ) ) {
                        	unlink( $this->cache_dir . '/' . $file );
                    	}
                	}
				} else {
					$this->cacheType = FALSE;
					return 'CACHING DISABLED: Cache Directory "'.$args['cache_dir'].'" is not writeable.';
				}
			} else 	{
				$this->cacheType = FALSE;
				return 'CACHING DISABLED: Cache Directory "'.$args['cache_dir'].'" doesn\'t exist, is a file or is not readable.';
			}
		}
		return (bool) TRUE;
    }

	/**
	 * Checks the database or filesystem for a cached result to the request.
	 *
	 * @access private
	 * @param array				$request Request to the Zenfolio API created by
	 *							one of the later functions in phpZenfolio.
	 * @return mixed			Unparsed serialized PHP, or FALSE if there is no
	 *							cached data for this query.
	 **/
    private function getCached( $request )
	{
		$request['authToken']       = ''; // Unset authToken
       	$reqhash = md5( serialize( $request ) );
		$expire = ( strpos( $request['method'], 'login' ) ) ? 21600 : $this->cache_expire;
		$diff = time() - $expire;

		if ( $this->cacheType == 'db' ) {
			$result = $this->cache_db->queryOne( 'SELECT response FROM ' . $this->cache_table . ' WHERE request = ' . $this->cache_db->quote( $reqhash ) . ' AND ' . $this->cache_db->quote( $diff ) . ' < expiration' );
			if ( PEAR::isError( $result ) ) {
				throw new Exception( $result );
			}
			if ( !empty( $result ) ) {
                return $result;
            }
        } elseif ( $this->cacheType == 'fs' ) {
            $file = $this->cache_dir . '/' . $reqhash . '.cache';
			if ( file_exists( $file ) && ( ( filemtime( $file ) + $expire ) > time() ) ) {
					return file_get_contents( $file );
            }
        }
    	return FALSE;
    }

	/**
	 * Caches the unparsed serialized PHP of a request. 
	 *
	 * @access private
	 * @param array			$request Request to the Zenfolio API created by one
	 *						of the later functions in phpZenfolio.
	 * @param string		$response Response from a successful request() method call.
	 * @return null|TRUE
	 **/
    private function cache( $request, $response )
	{
		$request['authToken']       = ''; // Unset authToken
		if ( ! strpos( $request['method'], 'Authenticate' ) ) {
			$reqhash = md5( serialize( $request ) );
			if ( $this->cacheType == 'db' ) {
				if ( $this->cache_db->queryOne( "SELECT COUNT(*) FROM {$this->cache_table} WHERE request = '$reqhash'" ) ) {
					$sql = 'UPDATE ' . $this->cache_table . ' SET response = '. $this->cache_db->quote( $response ) . ', expiration = ' . $this->cache_db->quote( time() ) . ' WHERE request = ' . $this->cache_db->quote( $reqhash ) ;
					$result = $this->cache_db->exec( $sql );
				} else {
					$sql = 'INSERT INTO ' . $this->cache_table . ' (request, response, expiration) VALUES (' . $this->cache_db->quote( $reqhash ) .', ' . $this->cache_db->quote( strtr( $response, "'", "\'" ) ) . ', ' . $this->cache_db->quote( time() ) . ')';
					$result = $this->cache_db->exec( $sql );
				}
				if ( PEAR::isError( $result ) ) {
					throw new Exception( $result );
				}
			} elseif ( $this->cacheType == 'fs' ) {
				$file = $this->cache_dir . '/' . $reqhash . '.cache';
				$fstream = fopen( $file, 'w' );
				$result = fwrite( $fstream,$response );
				fclose( $fstream );
			}
		}
        return $result;
    }

	/**
	 * Forcefully clear the cache.
	 *
	 * This is useful if you've made changes to your Zenfolio galleries and want
	 * to ensure the changes are reflected by your application immediately.
	 *
	 * @access public
	 * @param boolean	$delete Set to TRUE to delete the cache after clearing it
	 * @return boolean
	 **/
    public function clearCache( $delete = FALSE )
	{
   		if ( $this->cacheType == 'db' ) {
			if ( $delete ) {
				$result = $this->cache_db->exec( 'DROP TABLE ' . $this->cache_table );
			} else {
				$result = $this->cache_db->exec( 'DELETE FROM ' . $this->cache_table );
			}
			if ( ! PEAR::isError( $result ) ) {
				$result = TRUE;
			}
	   	} elseif ( $this->cacheType == 'fs' ) {
            $dir = opendir( $this->cache_dir );
	       	if ( $dir ) {
				foreach ( glob( $this->cache_dir."/*.cache" ) as $filename ) {
					$result = unlink( $filename );
				}
	       	}
			closedir( $dir );
			if ( $delete ) {
				$result = rmdir( $this->cache_dir );
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
	 * @param string	$command Zenfolio API method to call in the request
	 * @param array		$args optional Array of arguments that form the API call
	 * @return string	JSON response from Zenfolio, or an Exception is thrown
	 **/
	private function request( $command, $args = array() )
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

		if ( ! is_null( $this->keyring ) ) {
			$this->req->setHeader( 'X-Zenfolio-Keyring', $this->keyring );
		}
		
		// To keep things unique, we set the ID to a base32 figure of the string concat of the method and all arguments
		$str = $command . '.' . join( '.', $args );
		$this->id = intval( $str, 32 );
		$args = array( 'method' => $command, 'params' => $args, 'id' => $this->id );

		if ( !( $this->response = $this->getCached( $args ) ) ) {
			$this->req->setBody( json_encode( $args ) );
			try {
				$response = $this->req->send();
				if ( 200 == $response->getStatus() ) {
					$this->response = $response->getBody();
					$this->cache( $args, $this->response );
				} else {
					$msg = 'Request failed. HTTP Reason: '.$response->getReasonPhrase();
					$code = $response->getStatus();
					throw new Exception( $msg, $code );
				}
			}
			catch ( HTTP_Request2_Exception $e ) {
				throw new Exception( $e );
			}
		}

		$this->parsed_response = json_decode( $this->response, true );

		if ( $this->parsed_response['id'] != $this->id ) {
			$this->error_msg = "Incorrect response ID. (request ID: {$this->id}, response ID: {$this->parsed_response['id']} )";
			$this->parsed_response = FALSE;
			throw new Exception( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		}
		if ( ! is_null( $this->parsed_response['error'] ) ) {
			$this->error_code = $this->errCode[$this->parsed_response['error']['code']];
            $this->error_msg = $this->parsed_response['error']['code'] . ' : '.$this->parsed_response['error']['message'];
			$this->parsed_response = FALSE;
			throw new Exception( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		} else {
			$this->error_code = FALSE;
            $this->error_msg = FALSE;
		}

		return $this->response;
    }
	
	/**
	 * Set a proxy for all phpZenfolio calls.
	 *
	 * Params can be passed as an associative array or a set of param=value strings.
	 *
	 * @access public
	 * @param string	$server Proxy server
	 * @param string	$port Proxy server port
	 * @param string	$username (Optional) Proxy username
	 * @param string	$password (Optional) Proxy password
	 * @return void
	 **/
    public function setProxy()
	{
		$args = phpZenfolio::processArgs(func_get_args());
		$this->proxy['server'] = $args['server'];
		$this->proxy['port'] = $args['port'];
		$this->proxy['username'] = $args['username'];
		$this->proxy['password'] = $args['password'];
		$this->req->setConfig( array( 'proxy_host' => $args['server'],
							          'proxy_port' => $args['port'],
									  'proxy_user' => $args['username'],
									  'proxy_password' => $args['password'] ) );
    }
 
	/**
	 * Single login function for all login methods.
	 * 
	 * I've created this function to make it easy to login to Zenfolio using either
	 * the plaintext authentication method, or the more secure challenge-response (default)
	 * authentication method.
	 *
	 * Params can be passed as an associative array or a set of param=value strings.
	 *
	 * @access public
	 * @uses request
	 * @param string	$username The Zenfolio username
	 * @param string	$password The Zenfolio username's password
	 * @param boolean	$plaintext (Optional) Set whether the login should use
	 *					the plaintext (TRUE) or the challenge-response authentication
	 *					method (FALSE). Defaults to FALSE.
	 * @return string
	 */
	public function login()
	{
		$args = phpZenfolio::processArgs( func_get_args() );
		if ( $args['Plaintext'] ) {
			$this->authToken = $this->AuthenticatePlain( $args['Username'], $args['Password'] );
		} else {
			$cr = $this->GetChallenge( $args['Username'] );
			$salt = self::byteArrayDecode( $cr['PasswordSalt'] );
			$challenge = self::byteArrayDecode( $cr['Challenge'] );
			$password = utf8_encode( $args['Password'] );

			$passHash = hash( 'sha256', $salt.$password, TRUE );
			$chalHash = hash( 'sha256', $challenge.$passHash, TRUE );
			$proof = array_values( unpack( 'C*', $chalHash ) );
			$this->authToken = $this->Authenticate( $cr['Challenge'] , $proof );
		}
		return $this->authToken;
	}
	
	/**
	 * To make life easy for phpZenfolio users, I've created a single method that
	 * can be used to upload files.  This uses the Simplified HTTP POST method as
	 * detailed at {@link http://www.zenfolio.com/zf/help/api/guide/upload}
	 *
	 * @access public
	 * @param string	$PhotoSetId The ID of the PhotoSet into which you wish
	 *					the image to be uploaded. Use this OR $UploadUrl.
	 * @param string	$UploadUrl The UploadUrl of the PhotoSet into which you
	 *					wish the image to be uploaded. Use this OR $PhotoSetId.
	 * @param string	$File The path to the local file that is being uploaded.
	 * @return string
	 * @link http://www.zenfolio.com/zf/help/api/guide/upload
	 **/
	public function upload()
	{
		$args = phpZenfolio::processArgs( func_get_args() );
		if ( ! array_key_exists( 'PhotoSetId', $args ) && ! array_key_exists( 'UploadUrl', $args ) ) {
			throw new Exception ( 'No PhotoSetId or UploadUrl specified.', -10002 );
		}
		if ( ! array_key_exists( 'File', $args ) ) {
			throw new Exception( 'No upload file specified.', -10003 );
		}
		
		// Set FileName, if one isn't provided in the method call
		if ( ! array_key_exists( 'filename', $args ) ) {
			$args['filename'] = basename( $args['File'] );
		}

		// Ensure the FileName is phpZenfolio::urlencodeRFC3986 encoded - caters for stange chars and spaces
		$args['filename'] = phpZenfolio::urlencodeRFC3986( $args['filename'] );

		if ( is_file( $args['File'] ) ) {
			$fileinfo = getimagesize($args['File'] );
			$fp = fopen( $args['File'], 'rb' );
			$data = fread( $fp, filesize( $args['File'] ) );
			fclose( $fp );
		} else {
			throw new Exception( "File doesn't exist: {$args['File']}", -10004 );
		}

		$upload_req = new HTTP_Request2();
		$upload_req->setConfig( array( 'adapter' => $this->adapter, 'follow_redirects' => TRUE, 'max_redirects' => 3, 'ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE ) );
        $upload_req->setMethod( HTTP_Request2::METHOD_POST );
		$upload_req->setHeader( array( 'User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									   'X-Zenfolio-User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									   'Content-type' => $fileinfo['mime'],
									   'Content-Length' => filesize( $args['File'] ),
									   'Connection' => 'keep-alive' ) );

		if ( ! is_null( $this->authToken ) ) {
			$upload_req->setHeader( 'X-Zenfolio-Token', $this->authToken );
		} else {
			throw new Exception( 'No authentication token found. Please login before uploading.', -10005 );
		}

		// Set the proxy if one has been set earlier
		if ( isset( $this->proxy ) && is_array( $this->proxy ) ) {
			$upload_req->setConfig( array( 'proxy_host' => $this->proxy['server'],
							          'proxy_port' => $this->proxy['port'],
									  'proxy_user' => $this->proxy['username'],
									  'proxy_password' => $this->proxy['password'] ) );
		}

		// Create the upload URL based on the information provided in the arguments.
		if ( $args['PhotoSetId'] ) {
			if ( $this->APIVer == '1.0' || $this->APIVer == '1.1' ) {
				$photoset = $this->LoadPhotoSet( $args['PhotoSetId'] );
				$UploadUrl = 'http://up.zenfolio.com' . $photoset['UploadUrl'];
			} else {
				$photoset = ( $this->APIVer == '1.4' ) ? $this->LoadPhotoSet( $args['PhotoSetId'], 'Level1', FALSE ) : $this->LoadPhotoSet( $args['PhotoSetId'] );
				$UploadUrl = $photoset['UploadUrl'];
			}
		}
		if ( $args['UploadUrl'] ) {
			$UploadUrl = $args['UploadUrl'];
		}
		
		$opts = array();
		foreach( $args as $name => $value ) {
			if ( ! in_array( $name, array( 'UploadUrl', 'PhotoSetId', 'File' ) ) ) {
				// The values passed should be urlencoded, but just in case they're not, we'll urldecode and then re-encode to be safe
				$value = urldecode( $value );
				$value = urlencode( $value );
				$opts[] = "{$name}={$value}";
			}
		}

		$url = $UploadUrl . '?'. join( '&', $opts );
		$upload_req->setURL( $url );

		$upload_req->setBody( $data );

		try {
			$response = $upload_req->send();
			if ( 200 == $response->getStatus() ) {
				$this->response = $response->getBody();
			} else {
				$msg = 'Request failed. HTTP Reason: '.$response->getReasonPhrase();
				$code = $response->getStatus();
				throw new Exception( $msg, $code );
			}
		}
		catch ( HTTP_Request2_Exception $e ) {
			throw new Exception( $e );
		}

		return $this->response;
	}
	
	/**
	 * Dynamic method handler.  This function handles all Zenfolio method calls
	 * not explicitly implemented by phpZenfolio.
	 * 
 	 * @access public
	 * @uses request
	 * @param string	$method The Zenfolio method you want to call.
	 * @param mixed		$arguments The params to be passed to the relevant API.
	 *					method. See Zenfolio API docs for more details. Order and
	 *					case are important.
	 * @return mixed
	 **/
	public function __call( $method, $arguments )
	{
		$args = $arguments;
		$this->request( $method, $args );
		$result = $this->parsed_response['result'];
		if ( $method == 'AuthenticatePlain' ) {
			$this->authToken = $result;
		}
		if ( $method == 'KeyringAddKeyPlain' ) {
			$this->keyring = $result;
		}
		return $result;
	}

	 /**
	  * Static function to encode a string according to RFC3986.
	  *
	  * @static
	  * @access private
	  * @param string		$string The string requiring encoding
	  * @return string
	  **/
	 private static function urlencodeRFC3986($string)
	 {
		return str_replace('%7E', '~', rawurlencode($string));
	 }
 
	 /**
	  * Process arguments passed to method
	  *
	  * @access private
	  * @static
	  * @param array		Arguments taken from a function by func_get_args()
	  * @return array
	  **/
	 private static function processArgs( $arguments )
	 {
		$args = array();
		foreach ( $arguments as $arg ) {
			if ( is_array( $arg ) ) {
				$args = array_merge( $args, $arg );
			} else {
				if ( strpos( $arg, '=' ) !== FALSE ) {
					$exp = explode('=', $arg, 2);
					$args[$exp[0]] = $exp[1];
				} else {
					$args[] = $arg;
				}
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
	  *
	  * @access private
	  * @param array
	  * @return string
	  */
	 private static function byteArrayDecode( $array )
	 {
		return call_user_func_array( pack, array_merge( array( 'C*' ),(array) $array ) );
	 }

	 /**
	  * Public function that returns the image url for an image. This is only for
	  * sizes other than the original size.
	  *
	  * @access public
	  * @param array	$photo The Photo object of the photo you with to obtain
	  *					the url for.
	  * @param int		$size The Zenfolio supplied image size.
	  *					See http://www.zenfolio.com/zf/help/api/guide/download
	  *					for a list of sizes.
	  * @return string
	  */
	 public static function imageUrl( $photo, $size ) {
		 return "http://{$photo['UrlHost']}/{$photo['UrlCore']}-{$size}.jpg?sn={$photo['Sequence']}&tk={$photo['UrlToken']}";
	 }
}
?>
