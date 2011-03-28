<?php 
/** 
 * phpZenfolio - phpZenfolio is a PHP wrapper class for the Zenfolio API. The intention
 *		         of this class is to allow PHP application developers to quickly
 *			     and easily interact with the Zenfolio API in their applications,
 *			     without having to worry about the finer details of the API.
 *
 * @author Colin Seymour <lildood@gmail.com>
 * @version 1.1
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
 * We define our own exception so application developers can differentiate these
 * from other exceptions.
 */
class PhpZenfolioException extends Exception {}

/**
 * phpZenfolio - all of the phpZenfolio functionality is provided in this class
 *
 * @package phpZenfolio
 **/
class phpZenfolio {
	var $version = '1.1';
	private $cacheType = FALSE;
	private $cache_expire = 3600;
	private $keyring;
	private $id;
	protected $authToken;
	private $adapter = 'curl';

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
		if ( ! isset( $args['AppName'] ) ) {
			throw new PhpZenfolioException( 'Application name missing.', -10001 );
		}
		$this->AppName = $args['AppName'];
        // All calls to the API are done via POST using my own constructed httpRequest class
		$this->req = new httpRequest();
		$this->req->setConfig( array( 'adapter' => $this->adapter, 'follow_redirects' => TRUE, 'max_redirects' => 3, 'ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE, 'connect_timeout' => 5 ) );
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
		$out = '';
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
	 * phpZenfolio uses the PEAR MDB2 module to interact with the database. You will
	 * need to install PEAR, the MDB2 module and corresponding database driver yourself
	 * in order to use database caching. 
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
				throw new PhpZenfolioException( $result );
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
		$request['authToken'] = ''; // Unset authToken
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
					// TODO: Create unit test for this
					throw new PhpZenfolioException( $result );
				}
				return $result;
			} elseif ( $this->cacheType == 'fs' ) {
				$file = $this->cache_dir . '/' . $reqhash . '.cache';
				$fstream = fopen( $file, 'w' );
				$result = fwrite( $fstream,$response );
				fclose( $fstream );
				return $result;
			}
		}
        return TRUE;
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
	 * @throws PhpZenfolioException
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
		
		// To keep things unique, we set the ID to the md5 sum of the method
		$this->id = md5( $command );
		$args = array( 'method' => $command, 'params' => $args, 'id' => $this->id );

		if ( !( $this->response = $this->getCached( $args ) ) ) {
			$this->req->setBody( json_encode( $args ) );
			$this->req->execute();
			$this->response = $this->req->getBody();
		}

		$this->parsed_response = json_decode( $this->response, true );

		if ( $this->parsed_response['id'] != $this->id ) {
			// TODO: Create unit test for this - maybe create a mock object or a private function that modifies the id
			$this->error_msg = "Incorrect response ID. (request ID: {$this->id}, response ID: {$this->parsed_response['id']} )";
			$this->parsed_response = FALSE;
			throw new PhpZenfolioException( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		}
		if ( ! is_null( $this->parsed_response['error'] ) ) {
			$this->error_code = ( isset( $this->parsed_response['error']['code'] ) ) ? self::errCode( $this->parsed_response['error']['code'] ) : -2;
            $this->error_msg = ( isset( $this->parsed_response['error']['code'] ) ) ? $this->parsed_response['error']['code'] : '' . ' : '.$this->parsed_response['error']['message'];
			$this->parsed_response = FALSE;
			throw new PhpZenfolioException( "Zenfolio API Error for method {$command}: {$this->error_msg}", $this->error_code );
		} else {
			$this->error_code = FALSE;
            $this->error_msg = FALSE;
			$this->cache( $args, $this->response );
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
	 * @param string	$auth_scheme (Optional) Proxy authentication scheme.
	 *					Defaults to "basic". Other supported option is "digest".
	 * @return void
	 **/
    public function setProxy()
	{
		$args = phpZenfolio::processArgs(func_get_args());
		$this->proxy['server'] = $args['server'];
		$this->proxy['port'] = $args['port'];
		$this->proxy['username'] = ( isset( $args['username'] ) ) ? $args['username'] : '';
		$this->proxy['password'] = ( isset( $args['password'] ) ) ? $args['password'] : '';
		$this->proxy['auth_scheme'] = ( isset( $args['auth_scheme'] ) ) ? $args['auth_scheme'] : 'basic';
		$this->req->setConfig( array( 'proxy_host' => $this->proxy['server'],
							          'proxy_port' => $this->proxy['port'],
									  'proxy_user' => $this->proxy['username'],
									  'proxy_password' => $this->proxy['password'],
									  'proxy_auth_scheme' => $this->proxy['auth_scheme'] ) );
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
		if ( isset( $args['Plaintext'] ) ) {
			$this->authToken = $this->AuthenticatePlain( $args['Username'], $args['Password'] );
		} else {
			$cr = $this->GetChallenge( $args['Username'] );
			$salt = self::byteArrayDecode( $cr['PasswordSalt'] );
			$challenge = self::byteArrayDecode( $cr['Challenge'] );
			$password = utf8_encode( $args['Password'] );

			$passHash = hash( 'sha256', $salt.$password, TRUE );
			$chalHash = hash( 'sha256', $challenge.$passHash, TRUE );
			$proof = array_values( unpack( 'C*', $chalHash ) );
			$this->setAuthToken( $this->Authenticate( $cr['Challenge'] , $proof ) );
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
		if ( ! isset( $args['PhotoSetId'] ) && ! isset( $args['UploadUrl'] ) ) {
			throw new PhpZenfolioException ( 'No PhotoSetId or UploadUrl specified.', -10002 );
		}
		if ( ! array_key_exists( 'File', $args ) ) {
			throw new PhpZenfolioException( 'No upload file specified.', -10003 );
		}

		// Set FileName, if one isn't provided in the method call
		if ( ! array_key_exists( 'filename', $args ) ) {
			$args['filename'] = basename( $args['File'] );
		}

		if ( is_file( $args['File'] ) ) {
			$fileinfo = getimagesize($args['File'] );	// We need this to get the content type. mime_content_type is deprecated and rarely included in most installations.
			$fp = fopen( $args['File'], 'rb' );
			$data = fread( $fp, filesize( $args['File'] ) );
			fclose( $fp );
		} else {
			throw new PhpZenfolioException( "File doesn't exist: {$args['File']}", -10004 );
		}

		// Create a new object as we still need the other request object
		$upload_req = new httpRequest();
		$upload_req->setConfig( array( 'adapter' => $this->adapter, 'follow_redirects' => TRUE, 'max_redirects' => 3, 'ssl_verify_peer' => FALSE, 'ssl_verify_host' => FALSE, 'connect_timeout' => 60 ) );
		$upload_req->setMethod( 'post' );
		$upload_req->setHeader( array( 'User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									   'X-Zenfolio-User-Agent' => "{$this->AppName} using phpZenfolio/{$this->version}",
									   'Content-Type' => $fileinfo['mime'],
									   'Content-Length' => filesize( $args['File'] ) ) );

		if ( ! is_null( $this->authToken ) ) {
			$upload_req->setHeader( 'X-Zenfolio-Token', $this->authToken );
		}

		if ( ! is_null( $this->keyring ) ) {
			// TODO: Create unit test for this
			$upload_req->setHeader( 'X-Zenfolio-Keyring', $this->keyring );
		}

		if ( isset( $this->proxy ) && is_array( $this->proxy ) ) {
			$upload_req->setConfig( array( 'proxy_host' => $this->proxy['server'],
							          'proxy_port' => $this->proxy['port'],
									  'proxy_user' => $this->proxy['username'],
									  'proxy_password' => $this->proxy['password'] ) );
		}

		// Create the upload URL based on the information provided in the arguments.
		if ( isset ( $args['PhotoSetId'] ) ) {
			if ( $this->APIVer == '1.0' || $this->APIVer == '1.1' ) {
				$photoset = $this->LoadPhotoSet( $args['PhotoSetId'] );
				$UploadUrl = 'http://up.zenfolio.com' . $photoset['UploadUrl'];
			} else {
				$photoset = ( $this->APIVer == '1.4' ) ? $this->LoadPhotoSet( $args['PhotoSetId'], 'Level1', FALSE ) : $this->LoadPhotoSet( $args['PhotoSetId'] );
				$UploadUrl = $photoset['UploadUrl'];
			}
		}
		if ( isset( $args['UploadUrl'] ) ) {
			$UploadUrl = $args['UploadUrl'];
		}

		$params = array();
		foreach( $args as $name => $value ) {
			if ( ! in_array( $name, array( 'UploadUrl', 'PhotoSetId', 'File' ) ) ) {
				// The values passed should be urlencoded, but just in case they're not, we'll urldecode and then re-encode to be safe
				$value = urldecode( $value );
				$value = urlencode( $value );
				$params[$name] = $value;
			}
		}

		$upload_req->setURL( $UploadUrl );
		$upload_req->setParams( $params );
		$upload_req->setBody( $data );
		$upload_req->execute();
		$this->response = $upload_req->getBody();

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
	 * Get the authToken.  This is only valid for just over 24 hours.
	 *
	 * @return string
	 */
	public function getAuthToken()
	{
		return $this->authToken;
	}

	/**
	 * Set authToken.  This is useful for those who want to reuse the same authentication
	 * token within a 24 hour period.
	 * @param string	$token Token returned from login() method. Set to an empty string to unset.
	 * @return void
	 */
	public function setAuthToken( $token )
	{
		$this->authToken = $token;
	}

	/**
	 * Set the adapter.  Allowed options are 'curl' or 'socket'. Default is 'curl'
	 */
	public function setAdapter( $adapter )
	{
		$adapter = strtolower( $adapter );
		if ( $adapter == 'curl' || $adapter == 'socket' ) {
			$this->adapter = $adapter;
			$this->req->setAdapter( $adapter );
		}
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
		return call_user_func_array( 'pack', array_merge( array( 'C*' ),(array) $array ) );
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
	 * @return int
	 */
	private static function errCode( $string )
	{
		$errCodes = array( 'E_ACCOUNTLOCKED' => 90001,
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
		return $errCodes[$string];

	}
}



/****************** Custom HTTP Request Classes *******************************
 *
 * The classes below could be put into individual files, but to keep things simple
 * I've included them in this file.
 *
 * The code below has been taken from the Habari project - http://habariproject.org
 * and modified to suit the needs of phpZenfolio.
 *
 * The original source is distributed under the Apache License Version 2.0
 */

class HttpRequestException extends Exception {}

interface PhpZenfolioRequestProcessor
{
	public function execute( $method, $url, $headers, $body, $config );
	public function getBody();
	public function getHeaders();
}

class httpRequest
{
	private $method = 'POST';
	private $url;
	private $params = array();
	private $headers = array();
	private $postdata = array();
	private $files = array();
	private $body = '';
	private $processor = NULL;
	private $executed = FALSE;

	private $response_body = '';
	private $response_headers = '';

	private $user_agent = "Unknown application using phpZenfolio/1.0";

	/**
    * Adapter Configuration parameters
    * @var  array
    * @see  setConfig()
    */
    protected $config = array(
		'adapter'			=> 'curl',
        'connect_timeout'   => 5,
        'timeout'           => 0,
        'buffer_size'       => 16384,

        'proxy_host'        => '',
        'proxy_port'        => '',
        'proxy_user'        => '',
        'proxy_password'    => '',
        'proxy_auth_scheme' => 'basic',

		// TODO: These don't apply to SocketRequestProcessor yet
        'ssl_verify_peer'   => FALSE,
        'ssl_verify_host'   => FALSE,
        'ssl_cafile'        => NULL,
        'ssl_capath'        => NULL,
        'ssl_local_cert'    => NULL,
        'ssl_passphrase'    => NULL,

        'follow_redirects'  => FALSE,
        'max_redirects'     => 5
    );

	/**
	 * @param string	$url URL to request
	 * @param string	$method Request method to use (default 'POST')
	 * @param int		$timeout Timeout in seconds (default 30)
	 */
	public function __construct( $url = NULL, $method = 'POST', $timeout = 30 )
	{
		$this->method = strtoupper( $method );
		$this->url = $url;
		$this->setTimeout( $timeout );
		$this->setHeader( array( 'User-Agent' => $this->user_agent ) );

		// can't use curl's followlocation in safe_mode with open_basedir, so fallback to socket for now
		if ( function_exists( 'curl_init' ) && ( $this->config['adapter'] == 'curl' )
			 && ! ( ini_get( 'safe_mode' ) || ini_get( 'open_basedir' ) ) ) {
			$this->processor = new PhpZenfolioCurlRequestProcessor;
		}
		else {
			$this->processor = new PhpZenfolioSocketRequestProcessor;
		}
	}

	/**
	 * Set adapter configuration options
	 *
	 * @param mixed			$config An array of options or a string name with a
	 *						corresponding $value
	 * @param mixed			$value
	 * @return httpRequest
	 */
	public function setConfig( $config, $value = null )
    {
        if ( is_array( $config ) ) {
            foreach ( $config as $name => $value ) {
                $this->setConfig( $name, $value );
            }

        } else {
            if ( !array_key_exists( $config, $this->config ) ) {
				// We only trigger an error here as using an unknow config param isn't fatal
				trigger_error( "Unknown configuration parameter '{$config}'", E_USER_WARNING );
            } else {
				$this->config[$config] = $value;
			}
        }
        return $this;
    }

	/**
     * Set http method
     *
     * @param string HTTP method to use (GET, POST or PUT)
     * @return void
     */
    public function setMethod( $method )
	{
		$method = strtoupper( $method );
        if ( $method == 'GET' || $method == 'POST' || $method == 'PUT' ) {
            $this->method = $method;
		}
    }

	/**
	 * Set the request query parameters (i.e., the URI's query string).
	 * Will be merged with existing query info from the URL.
	 *
	 * @param array $params
	 * @return void
	 */
	public function setParams( $params )
	{
		if ( ! is_array( $params ) ) {
			$params = parse_str( $params );
		}
		$this->params = $params;
	}

	/**
	 * Add a request header.
	 *
	 * @param mixed $header		The header to add, either as an associative array
	 *							'name'=>'value' or as part of a $header $value
	 *							string pair.
	 * @param mixed $value		The value for the header if passing the header as
	 *							two arguments.
	 * @return void
	 */
	public function setHeader( $header, $value = NULL )
	{
		if ( is_array( $header ) ) {
			$this->headers = array_merge( $this->headers, $header );
		}
		else {
			$this->headers[$header] = $value;
		}
	}

	/**
	 * Return the response headers. Raises a warning and returns if the request wasn't executed yet.
	 *
	 * @return mixed
	 */
	public function getHeaders()
	{
		if ( !$this->executed ) {
			return 'Trying to fetch response headers for a pending request.';
		}
		return $this->response_headers;
	}

	/**
	 * Set the timeout. This is independent of the connect_timeout.
	 *
	 * @param int $timeout Timeout in seconds
	 * @return void
	 */
	public function setTimeout( $timeout )
	{
		$this->config['timeout'] = $timeout;
	}

	/**
	 * Set the adapter to use.  Accepted values are "curl" and "socket"
	 *
	 * @param string $adapter
	 * @return void
	 */
	public function setAdapter( $adapter )
	{
		$adapter = strtolower( $adapter );
		if ( $adapter == 'curl' || $adapter == 'socket' ) {
			$this->config['adapter'] = $adapter;
		}
	}

	/**
	 * Get the currently selected adapter. This is more for unit testing purposes
	 *
	 * @return string
	 */
	public function getAdapter()
	{
		return $this->config['adapter'];
	}

	/**
	 * Get the params
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}

	/**
	 * Set the destination url
	 *
	 * @param string $url Destination URL
	 * @return void
	 */
	public function setUrl( $url )
	{
		if ( $url ) {
            $this->url = $url;
		}
	}

	/**
	 * Set request body
	 *
	 * @param mixed
	 * @return void
	 */
	public function setBody( $body )
	{
		if ( $this->method === 'POST' || $this->method === 'PUT' ) {
			$this->body = $body;
		}
	}

	/**
	 * set postdata
	 *
	 * @access	public
	 * @param	mixed	$name
	 * @param	string	$value
	 * @return	void
	 */
	public function setPostData( $name, $value = null )
	{
		if ( is_array( $name ) ) {
			//$this->postdata = array_merge( $this->postdata, $name );
			$this->postdata = $name;
		}
		else {
			$this->postdata[$name] = $value;
		}
	}

	/**
	 * Return the response body. Raises a warning and returns if the request wasn't executed yet.
	 *
	 * @return mixed
	 */
	public function getBody()
	{
		if ( !$this->executed ) {
			return 'Trying to fetch response body for a pending request.';
		}
		return $this->response_body;
	}

	/**
	 * Actually execute the request.
	 *
	 * @return mixed	On success, returns TRUE and populates the response_body
	 *					and response_headers fields.
	 *					On failure, throws error.
	 */
	public function execute()
	{
		$this->prepare();
		$result = $this->processor->execute( $this->method, $this->url, $this->headers, $this->body, $this->config );
		$this->body = ''; // We need to do this as we reuse the same object for performance. Once we've executed, the body is useless anyway due to the changing params
		if ( $result ) {
			$this->response_headers = $this->processor->getHeaders();
			$this->response_body = $this->processor->getBody();
			$this->executed = true;
			return true;
		}
		else {
			$this->executed = false;
			return $result;
		}
	}

	/**
	 * Tidy things up in preparation of execution.
	 *
	 * @return void
	 */
	private function prepare()
	{
		// remove anchors (#foo) from the URL
		$this->url = preg_replace( '/(#.*?)?$/', '', $this->url );
		// merge query params from the URL with params given
		$this->url = $this->mergeQueryParams( $this->url, $this->params );

		if ( $this->method === 'POST' ) {
			if ( !isset( $this->headers['Content-Type'] ) ) {
				$this->setHeader( array( 'Content-Type' => 'application/x-www-form-urlencoded' ) );
			}
			if ( $this->headers['Content-Type'] == 'application/x-www-form-urlencoded' || $this->headers['Content-Type'] == 'application/json' ) {
				if( $this->body != '' && count( $this->postdata ) > 0 ) {
					$this->body .= '&';
				}
				$this->body .= http_build_query( $this->postdata, '', '&' );
			}
			$this->setHeader( array( 'Content-Length' => strlen( $this->body ) ) );
		}
	}

	/**
	 * Merge query params from the URL with given params.
	 *
	 * @param string $url The URL
	 * @param string $params An associative array of parameters.
	 * @return string
	 */
	private function mergeQueryParams( $url, $params )
	{
		$urlparts = parse_url( $url );

		if ( ! isset( $urlparts['query'] ) ) {
			$urlparts['query'] = '';
		}

		if ( ! is_array( $params ) ) {
			parse_str( $params, $params );
		}

		if ( $urlparts['query'] != '' ) {
			$parts = array_merge( parse_str( $qparts ) , $params );
		} else {
			$parts = $params;
		}
		$urlparts['query'] = http_build_query( $parts, '', '&' );
		return ( $urlparts['query'] != '' ) ? $url .'?'. $urlparts['query'] : $url;
	}

}

?>
<?php 

class PhpZenfolioCurlRequestProcessor implements PhpZenfolioRequestProcessor
{
	private $response_body = '';
	private $response_headers = '';
	private $executed = FALSE;
	private $can_followlocation = TRUE;
	private $_headers = '';

	public function __construct()
	{
		if ( ini_get( 'safe_mode' ) || ini_get( 'open_basedir' ) ) {
			$this->can_followlocation = FALSE;
		}
	}

	public function execute( $method, $url, $headers, $body, $config )
	{
		$merged_headers = array();
		foreach ( $headers as $k => $v ) {
			$merged_headers[] = $k . ': ' . $v;
		}

		$ch = curl_init();

		$options = array(
			CURLOPT_URL				=> $url,
			CURLOPT_HEADERFUNCTION	=> array( &$this, '_headerfunction' ),
			CURLOPT_MAXREDIRS		=> $config['max_redirects'],
			CURLOPT_CONNECTTIMEOUT	=> $config['connect_timeout'],
			CURLOPT_TIMEOUT			=> $config['timeout'],
			CURLOPT_SSL_VERIFYPEER	=> $config['ssl_verify_peer'],
			CURLOPT_SSL_VERIFYHOST	=> $config['ssl_verify_host'],
			CURLOPT_BUFFERSIZE		=> $config['buffer_size'],
			CURLOPT_HTTPHEADER		=> $merged_headers,
			CURLOPT_FOLLOWLOCATION	=> TRUE,
			CURLOPT_RETURNTRANSFER	=> TRUE,
		);

		if ( $this->can_followlocation ) {
			$options[CURLOPT_FOLLOWLOCATION] = TRUE; // Follow 302's and the like.
		}

		if ( $method === 'POST' ) {
			$options[CURLOPT_POST] = TRUE; // POST mode.
			$options[CURLOPT_POSTFIELDS] = $body;
		}
		else if ( $method === 'PUT' ) {
			$options[CURLOPT_CUSTOMREQUEST] = 'PUT'; // PUT mode
			$options[CURLOPT_POSTFIELDS] = $body; // The file to put
		}
		else {
			$options[CURLOPT_CRLF] = TRUE; // Convert UNIX newlines to \r\n
		}

		// set proxy, if needed
        if ( $host = $config['proxy_host'] ) {
            if ( ! ( $port = $config['proxy_port'] ) ) {
                throw new HttpRequestException( 'Proxy port not provided' );
            }
            curl_setopt( $ch, CURLOPT_PROXY, $host . ':' . $port );
            if ( $user = $config['proxy_user'] ) {
                curl_setopt( $ch, CURLOPT_PROXYUSERPWD, $user . ':' . $config['proxy_password'] );
                switch ( strtolower( $config['proxy_auth_scheme'] ) ) {
                    case 'basic':
                        curl_setopt( $ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC );
                        break;
                    case 'digest':
                        curl_setopt( $ch, CURLOPT_PROXYAUTH, CURLAUTH_DIGEST );
                }
            }
        }
		curl_setopt_array($ch, $options);

		$body = curl_exec( $ch );

		if ( curl_errno( $ch ) !== 0 ) {
			throw new HttpRequestException( sprintf( '%s: CURL Error %d: %s', __CLASS__, curl_errno( $ch ), curl_error( $ch ) ), curl_errno( $ch ) );
		}

		if ( substr( curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 0, 1 ) != 2 ) {
			throw new HttpRequestException( sprintf( 'Bad return code (%1$d) for: %2$s', curl_getinfo( $ch, CURLINFO_HTTP_CODE ), $url ), curl_errno( $ch ) );
		}

		curl_close( $ch );

		// this fixes an E_NOTICE in the array_pop
		$tmp_headers = explode( "\r\n\r\n", mb_substr( $this->_headers, 0, -4 ) );

		$this->response_headers = array_pop( $tmp_headers );
		$this->response_body = $body;
		$this->executed = true;

		return true;
	}

	public function _headerfunction( $ch, $str )
	{
		$this->_headers .= $str;
		return strlen( $str );
	}

	public function getBody()
	{
		if ( ! $this->executed ) {
			return 'Request has not executed yet.';
		}
		return $this->response_body;
	}

	public function getHeaders()
	{
		if ( ! $this->executed ) {
			return 'Request has not executed yet.';
		}
		return $this->response_headers;
	}
}

?>
<?php 

class PhpZenfolioSocketRequestProcessor implements PhpZenfolioRequestProcessor
{
	private $response_body = '';
	private $response_headers = '';
	private $executed = FALSE;
	private $redir_count = 0;

	public function execute( $method, $url, $headers, $body, $config )
	{
		$result = $this->_request( $method, $url, $headers, $body, $config );

		if ( $result ) {
			list( $response_headers, $response_body ) = $result;
			$this->response_headers = $response_headers;
			$this->response_body = $response_body;
			$this->executed = TRUE;

			return TRUE;
		}
		else {
			// TODO: Create unit test to test this
			return $result;
		}
	}

	private function _request( $method, $url, $headers, $body, $config )
	{
		$_errno = 0;
		$_errstr = '';
		$urlbits = parse_url( $url );

		if ( !isset( $urlbits['port'] ) || $urlbits['port'] == 0 ) {
			if ( $urlbits['scheme'] == 'https' ) {
				$urlbits['port'] = 443;
				$transport = 'ssl';
			}
			else {
				$urlbits['port'] = 80;
				$transport = 'tcp';
			}
		}

		if ( $config['proxy_host'] != '' ) {
			// TODO: Finish the implementation of proxy support for socket connections. Until then, only curl has proxy support.
			throw new HttpRequestException( 'The "socket" adapter type does NOT currently support connecting via a proxy. Please use the "curl" adapter type.', -1 );
			$fp = @fsockopen( $transport . '://' . $config['proxy_host'], $config['proxy_port'], $_errno, $_errstr, $config['connect_timeout'] );
		} else {
			$fp = @fsockopen( $transport . '://' . $urlbits['host'], $urlbits['port'], $_errno, $_errstr, $config['connect_timeout'] );
		}

		if ( $fp === FALSE ) {
			throw new HttpRequestException( sprintf( '%s: Error %d: %s while connecting to %s:%d', __CLASS__, $_errno, $_errstr, $urlbits['host'], $urlbits['port'] ), $_errno );
		}

		stream_set_timeout( $fp, $config['timeout'] );

		// fix headers
		$headers['Host'] = $urlbits['host'];
		$headers['Connection'] = 'close';

		// merge headers into a list
		$merged_headers = array();
		foreach ( $headers as $k => $v ) {
			$merged_headers[] = $k . ': ' . $v;
		}

		// build the request
		$request = array();
		$resource = $urlbits['path'];
		if ( isset( $urlbits['query'] ) ) {
			$resource .= '?' . $urlbits['query'];
		}

		$request[] = "{$method} {$resource} HTTP/1.1";
		$request = array_merge( $request, $merged_headers );
		$request[] = '';

		if ( $method === 'POST' || $method === 'PUT' ) {
			$request[] = $body;
		}

		$request[] = '';

		$out = implode( "\r\n", $request );

		if ( ! fwrite( $fp, $out, strlen( $out ) ) ) {
			throw new HttpRequestException( 'Error writing to socket.' );
		}

		$in = stream_get_contents( $fp );

		fclose( $fp );

		list( $header, $body ) = explode( "\r\n\r\n", $in, 2 );

		// to make the following REs match $ correctly and thus not break parse_url
		$header = str_replace( "\r\n", "\n", $header );

		preg_match( '#^HTTP/1\.[01] ([1-5][0-9][0-9]) ?(.*)#', $header, $status_matches );

		if ( ( $status_matches[1] == '301' || $status_matches[1] == '302' ) && $config['follow_redirects'] ) {
			if ( preg_match( '|^Location: (.+)$|mi', $header, $location_matches ) ) {
				$redirect_url = $location_matches[1];
				$this->redir_count++;
				if ( $this->redir_count > $this->config['max_redirects'] ) {
					throw new HttpRequestException( 'Maximum number of redirections exceeded.' );
				}
				return $this->_request( $method, $redirect_url, $headers, $body, $config );
			}
			else {
				throw new HttpRequestException( 'Redirection response without Location: header.' );
			}
		}

		if ( preg_match( '|^Transfer-Encoding:.*chunked.*|mi', $header ) ) {
			$body = $this->_unchunk( $body );
		}

		return array( $header, $body );
	}

	public function getBody()
	{
		if ( ! $this->executed ) {
			return 'Request has not executed yet.';
		}
		return $this->response_body;
	}

	public function getHeaders()
	{
		if ( ! $this->executed ) {
			return 'Request has not executed yet.';
		}
		return $this->response_headers;
	}

	private function _unchunk( $body )
	{
		/* see <http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html> */
		$result = '';
		$chunk_size = 0;

		do {
			$chunk = explode( "\r\n", $body, 2 );
			list( $chunk_size_str, )= explode( ';', $chunk[0], 2 );
			$chunk_size = hexdec( $chunk_size_str );

			if ( $chunk_size > 0 ) {
				$result .= mb_substr( $chunk[1], 0, $chunk_size );
				$body = mb_substr( $chunk[1], $chunk_size+1 );
			}
		}
		while ( $chunk_size > 0 );
		// this ignores trailing header fields

		return $result;
	}
}

?>
