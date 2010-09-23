<?php

/**
 * This is a very simple class to deal with HTTP Requests without the need for PEAR.
 *
 * This code comes from Habari and the http class I found at http://www.phpfour.com/blog/2008/01/php-http-class/
 *
 * @author Colin Seymour
 */

interface RequestProcessor
{
	public function execute( $method, $url, $headers, $body, $config );
	public function getBody();
	public function getHeaders();
}

class httpRequest
{
	private $method = 'GET';
	private $url;
	private $params = array();
	private $headers = array();
	private $postdata = array();
	private $files = array();
	private $body = '';
	private $processor = NULL;
	private $executed = FALSE;
	private $adapter = 'curl';

	private $response_body = '';
	private $response_headers = '';

	private $user_agent = "Unknown application using phpZenfolio/1.0";

	/**
    * Adapter Configuration parameters
    * @var  array
    * @see  setConfig()
    */
    protected $config = array(
        'connect_timeout'   => 10,
        'timeout'           => 0,
        'buffer_size'       => 16384,

        'proxy_host'        => '',
        'proxy_port'        => '',
        'proxy_user'        => '',
        'proxy_password'    => '',
        'proxy_auth_scheme' => 'basic',

        'ssl_verify_peer'   => FALSE,
        'ssl_verify_host'   => FALSE,
        'ssl_cafile'        => null,
        'ssl_capath'        => null,
        'ssl_local_cert'    => null,
        'ssl_passphrase'    => null,

        'follow_redirects'  => false,
        'max_redirects'     => 5
    );

	/**
	 * @param string	$url URL to request
	 * @param string	$method Request method to use (default 'GET')
	 * @param int		$timeout Timeout in seconds (default 180)
	 */
	public function __construct( $url, $method = 'GET', $timeout = 180 )
	{
		$this->method = strtoupper( $method );
		$this->url = $url;
		$this->setTimeout( $timeout );
		$this->setHeader( array( 'User-Agent' => $this->user_agent ) );

		// can't use curl's followlocation in safe_mode with open_basedir, so
		// fallback to socket for now
		if ( function_exists( 'curl_init' ) && ( $this->adapter == 'curl' )
			 && ! ( ini_get( 'safe_mode' ) && ini_get( 'open_basedir' ) ) ) {
			$this->processor = new CurlRequestProcessor;
		}
		else {
			$this->processor = 'SOCKET';
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
				// TODO Define my own exceptions.
                throw new Exception(
                    "Unknown configuration parameter '{$config}'"
                );
            }
            $this->config[$config] = $value;
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
        if ( $method == 'GET' || $method == 'POST' || $method == 'PUT' )
            $this->method = $method;
    }

	/**
	 * Add a request header.
	 * @param mixed $header The header to add, either as a string 'Name: Value' or an associative array 'name'=>'value'
	 */
	public function setHeader( $header )
	{
		if ( is_array( $header ) ) {
			$this->headers = array_merge( $this->headers, $header );
		}
		else {
			list( $k, $v )= explode( ': ', $header );
			$this->headers[$k] = $v;
		}
	}

	/**
	 * Set the timeout.
	 *
	 * @param int $timeout Timeout in seconds
	 * @return void
	 */
	public function setTimeout( $timeout )
	{
		$this->config['timeout'] = $this->config['connect_timeout'] = $timeout;
	}

	/**
	 * Set the adapter to use.  Accepted values are "curl" and "socket"
	 *
	 * @param string $adapter
	 * @return void
	 */
	public function setAdapter( $adapter )
	{
		if ( $adapter == 'curl' || $adapter == 'socket' )
			$this->adapter = $adapter;
	}

	/**
	 * Set the destination url
	 *
	 * @param string $url Destination URL
	 */
	public function setUrl( $url )
	{
		if ( $url )
            $this->url = $url; 
	}

	/**
	 * Set request body
	 *
	 * @param
	 */
	public function setBody( $body )
	{
		if ( $this->method === 'POST' )
			$this->body = $body;
	}

	/**
	 * Return the response headers. Raises a warning and returns '' if the request wasn't executed yet.
	 */
	public function getHeaders()
	{
		if ( !$this->executed )
			return trigger_error( 'Trying to fetch response headers for a pending request.', E_USER_WARNING );

		return $this->response_headers;
	}

	/**
	 * Return the response body. Raises a warning and returns '' if the request wasn't executed yet.
	 */
	public function getBody()
	{
		if ( !$this->executed )
			return trigger_error( 'Trying to fetch response body for a pending request.', E_USER_WARNING );

		return $this->response_body;
	}
	
	/**
	 * Actually execute the request.
	 * On success, returns TRUE and populates the response_body and response_headers fields.
	 * On failure, throws error.
	 */
	public function execute()
	{
		$this->prepare();
		$result = $this->processor->execute( $this->method, $this->url, $this->headers, $this->body, $this->timeout, $this->config );

		//if ( $result && ! Error::is_error( $result ) ) { // XXX exceptions?
		if ( $result ) {
			$this->response_headers = $this->processor->getHeaders();
			$this->response_body = $this->processor->getBody();
			$this->executed = TRUE;

			return TRUE;
		}
		else {
			// actually, processor->execute should throw an Error which would bubble up
			// we need a new Error class and error handler for that, though
			$this->executed = FALSE;

			return $result;
		}
	}

	/**
	 * A little housekeeping.
	 */
	private function prepare()
	{
		// remove anchors (#foo) from the URL
		//$this->url = preg_replace( '/(#.*?)?$/', '', $this->url );
		// merge query params from the URL with params given
		// WEDONTUSE: $this->url = $this->merge_query_params( $this->url, $this->params );

		if ( $this->method === 'POST' ) {
			if ( !isset( $this->headers['Content-Type'] ) || ( $this->headers['Content-Type'] == 'application/json' ) ) {
				$this->setHeader( array( 'Content-Type' => 'application/json' ) );
				/* WEDONTUSE
				if( $this->body != '' && count( $this->postdata ) > 0 ) {
					$this->body .= '&';
				}
				$this->body .= http_build_query( $this->postdata, '', '&' );*/
			}
			elseif ( $this->headers['Content-Type'] == 'multipart/form-data' ) {
				$boundary = md5( Utils::nonce() );
				$this->headers['Content-Type'] .= '; boundary=' . $boundary;

				$parts = array();
				if ( $this->postdata && is_array( $this->postdata ) ) {
					reset( $this->postdata );
					while ( list( $name, $value ) = each( $this->postdata ) ) {
						$parts[] = "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
					}
				}

				if ( $this->files && is_array( $this->files ) ) {
					reset( $this->files );
					while ( list( $name, $fileinfo ) = each( $this->files ) ) {
						$filename = basename( $fileinfo['filename'] );
						if ( !empty( $fileinfo['override_filename'] ) ) {
							$filename = $fileinfo['override_filename'];
						}
						$part = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n";
						$part .= "Content-Type: {$fileinfo['content_type']}\r\n\r\n";
						$part .= file_get_contents( $fileinfo['filename'] ) . "\r\n";
						$parts[] = $part;
					}
				}

				if ( !empty( $parts ) ) {
					$this->body = "--{$boundary}\r\n" . join("--{$boundary}\r\n", $parts) . "--{$boundary}--\r\n";
				}
			}
			$this->setHeader( array( 'Content-Length' => strlen( $this->body ) ) );
		}
	}

	
}
/*

class SocketRequestProcessor implements RequestProcessor
{
	private $response_body = '';
	private $response_headers = '';
	private $executed = FALSE;

	// Maximum number of redirects to follow.
	private $max_redirs = 5;

	private $redir_count = 0;

	public function execute( $method, $url, $headers, $body, $timeout )
	{
		$result = $this->_request( $method, $url, $headers, $body, $timeout );

		if ( $result && ! Error::is_error( $result ) ) {
			list( $response_headers, $response_body )= $result;
			$this->response_headers = $response_headers;
			$this->response_body = $response_body;
			$this->executed = TRUE;

			return TRUE;
		}
		else {
			return $result;
		}
	}

	private function _request( $method, $url, $headers, $body, $timeout )
	{
		$urlbits = InputFilter::parse_url( $url );

		return $this->_work( $method, $urlbits, $headers, $body, $timeout );
	}

	//@todo Does not honor timeouts on the actual request, only on the connect() call.

	private function _work( $method, $urlbits, $headers, $body, $timeout )
	{
		$_errno = 0;
		$_errstr = '';

		if ( !isset( $urlbits['port'] ) || $urlbits['port'] == 0 ) {
			if ( array_key_exists( $urlbits['scheme'], Utils::scheme_ports() ) ) {
				$urlbits['port'] = Utils::scheme_ports($urlbits['scheme']);
			}
			else {
				// todo: Error::raise()?
				$urlbits['port'] = 80;
			}
		}

		if ( !in_array( $urlbits['scheme'], stream_get_transports() ) ) {
			$transport = ( $urlbits['scheme'] == 'https' ) ? 'ssl' : 'tcp';
		}
		else {
			$transport = $urlbits['scheme'];
		}

		$fp = @fsockopen( $transport . '://' . $urlbits['host'], $urlbits['port'], $_errno, $_errstr, $timeout );

		if ( $fp === FALSE ) {
			return Error::raise( sprintf( _t('%s: Error %d: %s while connecting to %s:%d'), __CLASS__, $_errno, $_errstr, $urlbits['host'], $urlbits['port'] ),
				E_USER_WARNING );
		}

		// timeout to fsockopen() only applies for connecting
		stream_set_timeout( $fp, $timeout );

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
			$resource.= '?' . $urlbits['query'];
		}

		$request[] = "{$method} {$resource} HTTP/1.1";
		$request = array_merge( $request, $merged_headers );

		$request[] = '';

		if ( $method === 'POST' ) {
			$request[] = $body;
		}

		$request[] = '';

		$out = implode( "\r\n", $request );

		if ( ! fwrite( $fp, $out, strlen( $out ) ) ) {
			return Error::raise( _t('Error writing to socket.') );
		}

		$in = '';

		while ( ! feof( $fp ) ) {
			$in.= fgets( $fp, 1024 );
		}

		fclose( $fp );

		list( $header, $body )= explode( "\r\n\r\n", $in );

		// to make the following REs match $ correctly
		// and thus not break parse_url
		$header = str_replace( "\r\n", "\n", $header );

		preg_match( '#^HTTP/1\.[01] ([1-5][0-9][0-9]) ?(.*)#', $header, $status_matches );

		if ( $status_matches[1] == '301' || $status_matches[1] == '302' ) {
			if ( preg_match( '|^Location: (.+)$|mi', $header, $location_matches ) ) {
				$redirect_url = $location_matches[1];

				$redirect_urlbits = InputFilter::parse_url( $redirect_url );

				if ( !isset( $redirect_url['host'] ) ) {
					$redirect_urlbits['host'] = $urlbits['host'];
				}

				$this->redir_count++;

				if ( $this->redir_count > $this->max_redirs ) {
					return Error::raise( _t('Maximum number of redirections exceeded.') );
				}

				return $this->_work( $method, $redirect_urlbits, $headers, $body, $timeout );
			}
			else {
				return Error::raise( _t('Redirection response without Location: header.') );
			}
		}

		if ( preg_match( '|^Transfer-Encoding:.*chunked.*|mi', $header ) ) {
			$body = $this->_unchunk( $body );
		}

		return array( $header, $body );
	}

	private function _unchunk( $body )
	{
		// see <http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html>
		$result = '';
		$chunk_size = 0;

		do {
			$chunk = explode( "\r\n", $body, 2 );
			list( $chunk_size_str, )= explode( ';', $chunk[0], 2 );
			$chunk_size = hexdec( $chunk_size_str );

			if ( $chunk_size > 0 ) {
				$result .= MultiByte::substr( $chunk[1], 0, $chunk_size );
				$body = MultiByte::substr( $chunk[1], $chunk_size+1 );
			}
		}
		while ( $chunk_size > 0 );
		// this ignores trailing header fields

		return $result;
	}

	public function get_response_body()
	{
		if ( ! $this->executed ) {
			return Error::raise( _t('Request did not yet execute.') );
		}

		return $this->response_body;
	}

	public function get_response_headers()
	{
		if ( ! $this->executed ) {
			return Error::raise( _t('Request did not yet execute.') );
		}

		return $this->response_headers;
	}
}


*/

class CurlRequestProcessor implements RequestProcessor
{
	private $response_body = '';
	private $response_headers = '';
	private $executed = false;

	private $can_followlocation = true;

	/**
	 * Temporary buffer for headers.
	 */
	private $_headers = '';

	public function __construct()
	{
		if ( ini_get( 'safe_mode' ) || ini_get( 'open_basedir' ) ) {
			$this->can_followlocation = false;
		}

		if ( !defined( 'FILE_CACHE_LOCATION' ) ) {
			define( 'FILE_CACHE_LOCATION', '/tmp' );
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
			CURLOPT_RETURNTRANSFER	=> TRUE,
		);

		

		if ( $this->can_followlocation ) {
			$options[CURLOPT_FOLLOWLOCATION] = TRUE; // Follow 302's and the like.
		}

		if ( $method === 'POST' ) {
			$options[CURLOPT_POST] = TRUE; // POST mode.
			$options[CURLOPT_POSTFIELDS] = $body;
		}
		else {
			$options[CURLOPT_CRLF] = TRUE; // Convert UNIX newlines to \r\n
		}

		if ( $config['proxy_host'] != '' ) {
			$proxy_opts = array(
								CURLOPT_HTTPPROXYTUNNEL	=> TRUE,
								CURLOPT_PROXYAUTH		=> $config['proxy_auth_scheme'],
								CURLOPT_PROXY			=> $config['proxy_host'],
								CURLOPT_PROXYPORT		=> $config['proxy_port'],
								CURLOPT_PROXYUSERPWD	=> "{$config['proxy_user']}:{$config['proxy_password']}",
								);
			array_merge($options, $proxy_opts);
		}
		
		curl_setopt_array($ch, $options);


		/**
		 * @todo Possibly find a way to generate a temp file without needing the user
		 * to set write permissions on cache directory
		 *
		 * @todo Fallback to using the the old way if the cache directory isn't writable
		 */
		$tmp = tempnam( FILE_CACHE_LOCATION, 'RR' );
		if ( ! $tmp ) {
			trigger_error( sprintf( ' %s: CURL Error. Unable to create temporary file name.', array( __CLASS__ ) ), E_USER_WARNING );
		}

		$fh = @fopen( $tmp, 'w+b' );
		if ( ! $fh ) {
			trigger_error( sprintf( ' %s: CURL Error. Unable to open temporary file.', array( __CLASS__ ) ), E_USER_WARNING );
		}

		//curl_setopt( $ch, CURLOPT_FILE, $fh );

		$body = curl_exec( $ch );

		/*if( $success ) {
			rewind( $fh );
			$body = stream_get_contents( $fh );
		}
		fclose( $fh );
		unset( $fh );

		if ( isset( $tmp ) && file_exists ($tmp ) ) {
			unlink( $tmp );
		}*/

		if ( curl_errno( $ch ) !== 0 ) {
			return trigger_error( sprintf( '%s: CURL Error %d: %s', __CLASS__, curl_errno( $ch ), curl_error( $ch ) ), E_USER_WARNING );
		}

		if ( substr( curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 0, 1 ) != 2 ) {
			return trigger_error( sprintf( 'Bad return code (%1$d) for: %2$s',
				curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
				$url ),
				E_USER_WARNING
			);
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
		$this->_headers.= $str;

		return strlen( $str );
	}

	public function getBody()
	{
		if ( ! $this->executed ) {
			//return Error::raise( _t('Request did not yet execute.') );
			return 'Request did not yet execute.';
		}

		return $this->response_body;
	}

	public function getHeaders()
	{
		if ( ! $this->executed ) {
			//return Error::raise( _t('Request did not yet execute.') );
			return 'Request did not yet execute.';
		}

		return $this->response_headers;
	}
}

?>
