<?php

/**
 * This is a very simple class to deal with HTTP Requests without the need for PEAR.
 *
 * This code comes from Habari and the http class I found at http://www.phpfour.com/blog/2008/01/php-http-class/
 *
 * @author Colin Seymour
 */

class HttpRequestException extends Exception {}

interface RequestProcessor
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
	 * @param string	$method Request method to use (default 'POST')
	 * @param int		$timeout Timeout in seconds (default 30)
	 */
	public function __construct( $url, $method = 'POST', $timeout = 30 )
	{
		$this->method = strtoupper( $method );
		$this->url = $url;
		$this->setTimeout( $timeout );
		$this->setHeader( array( 'User-Agent' => $this->user_agent ) );

		// can't use curl's followlocation in safe_mode with open_basedir, so
		// fallback to socket for now
		if ( function_exists( 'curl_init' ) && ( $this->config['adapter'] == 'curl' )
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
	 * @param array $params
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
	 * @param mixed $header		The header to add, either as an associative array 'name'=>'value' or as part of a $header $value string pair
	 * @param mixed $value		The value for the header if passing the header as two arguments.
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
		$adapter = strtolower( $adapter );
		if ( $adapter == 'curl' || $adapter == 'socket' ) {
			$this->config['adapter'] = $adapter;
		}
	}

	/**
	 * Set the destination url
	 *
	 * @param string $url Destination URL
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
	 * @param
	 */
	public function setBody( $body )
	{
		if ( $this->method === 'POST' ) {
			$this->body = $body;
		}
	}

	/**
	 * Return the response headers. Raises a warning and returns '' if the request wasn't executed yet.
	 */
	public function getHeaders()
	{
		if ( !$this->executed ) {
			return 'Trying to fetch response headers for a pending request.';
		}
		return $this->response_headers;
	}

	/**
	 * Return the response body. Raises a warning and returns '' if the request wasn't executed yet.
	 */
	public function getBody()
	{
		if ( !$this->executed ) {
			return 'Trying to fetch response body for a pending request.';
		}
		return $this->response_body;
	}

	public function getParams()
	{
		return $this->params;
	}
	
	/**
	 * Actually execute the request.
	 * On success, returns TRUE and populates the response_body and response_headers fields.
	 * On failure, throws error.
	 */
	public function execute()
	{
		$this->prepare();
		$result = $this->processor->execute( $this->method, $this->url, $this->headers, $this->body, $this->config );

		if ( $result ) {
			$this->response_headers = $this->processor->getHeaders();
			$this->response_body = $this->processor->getBody();
			$this->executed = TRUE;
			return TRUE;
		}
		else {
			$this->executed = FALSE;
			return $result;
		}
	}

	/**
	 * Tidy things up in preparation of execution.
	 */
	private function prepare()
	{
		// remove anchors (#foo) from the URL
		$this->url = preg_replace( '/(#.*?)?$/', '', $this->url );
		// merge query params from the URL with params given
		$this->url = $this->mergeQueryParams( $this->url, $this->params );

		if ( $this->method === 'POST' ) {
			if ( !isset( $this->headers['Content-Type'] ) || ( $this->headers['Content-Type'] == 'application/json' ) ) {
				// Just being careful
				$this->setHeader( array( 'Content-Type' => 'application/json' ) );
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
	 * @param string $url The URL
	 * @param string $params An associative array of parameters.
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
/*

class SocketRequestProcessor implements RequestProcessor
{
	private $response_body = '';
	private $response_headers = '';
	private $executed = FALSE;
	private $redir_count = 0;

	public function execute( $method, $url, $headers, $body, $config )
	{
		$result = $this->_request( $method, $url, $headers, $body, $config );

		if ( $result ) {
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

	private function _request( $method, $url, $headers, $body, $config )
	{
		$urlbits = parse_url( $url );

		return $this->_work( $method, $urlbits, $headers, $body, $config );
	}

	//@todo Does not honor timeouts on the actual request, only on the connect() call.

	private function _work( $method, $urlbits, $headers, $body, $config )
	{
		$_errno = 0;
		$_errstr = '';

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

		$fp = @fsockopen( $transport . '://' . $urlbits['host'], $urlbits['port'], $_errno, $_errstr, $config['timeout'] );

		if ( $fp === FALSE ) {
			throw new HttpRequestException( sprintf( _t('%s: Error %d: %s while connecting to %s:%d'), __CLASS__, $_errno, $_errstr, $urlbits['host'], $urlbits['port'] ), $_errno );
		}

		// timeout to fsockopen() only applies for connecting
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

				$redirect_urlbits = parse_url( $redirect_url );

				if ( !isset( $redirect_url['host'] ) ) {
					$redirect_urlbits['host'] = $urlbits['host'];
				}

				$this->redir_count++;

				if ( $this->redir_count > $this->max_redirs ) {
					return Error::raise( _t('Maximum number of redirections exceeded.') );
				}

				return $this->_work( $method, $redirect_urlbits, $headers, $body, $config['timeout'] );
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
			return 'Request did not yet execute.';
		}

		return $this->response_body;
	}

	public function get_response_headers()
	{
		if ( ! $this->executed ) {
			return 'Request did not yet execute.';
		}

		return $this->response_headers;
	}
}


*/

class CurlRequestProcessor implements RequestProcessor
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


		/**
		 * @todo Possibly use this to write straight to cache
		 */
		/*
		$tmp = tempnam( FILE_CACHE_LOCATION, 'RR' );
		if ( ! $tmp ) {
			trigger_error( sprintf( ' %s: CURL Error. Unable to create temporary file name.', array( __CLASS__ ) ), E_USER_WARNING );
		}

		$fh = @fopen( $tmp, 'w+b' );
		if ( ! $fh ) {
			trigger_error( sprintf( ' %s: CURL Error. Unable to open temporary file.', array( __CLASS__ ) ), E_USER_WARNING );
		}

		curl_setopt( $ch, CURLOPT_FILE, $fh );

		$success = curl_exec( $ch );

		if( $success ) {
			rewind( $fh );
			$body = stream_get_contents( $fh );
		}
		fclose( $fh );
		unset( $fh );

		if ( isset( $tmp ) && file_exists ($tmp ) ) {
			unlink( $tmp );
		}*/

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
		$this->_headers.= $str;
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
