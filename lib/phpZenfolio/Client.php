<?php

namespace phpZenfolio;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use phpZenfolio\Exception\InvalidArgumentException;
use phpZenfolio\Exception\BadMethodCallException;
use phpZenfolio\Exception\UnexpectedValueException;

class Client
{
    /**
     * A few default variables.
     */
    const VERSION = '2.0.0';
    public $AppName = 'Unknown Application';
    protected $authToken;
    private $keyring;
    private $id;
    private $stack;
    private $client;

    /**
     * The Guzzle instance used to communicate with Zenfolio.
     */
    private $httpClient;

    /**
     * The response object for each request.
     */
    private $response;

    /**
     * @var array
     */
    private $default_options = array(
        'base_uri' => 'https://api.zenfolio.com/',
        'api_version' => '1.8',
        'query' => [],
        'headers' => [
                        'User-Agent' => 'phpZenfolio',
                        'Accept' => 'application/json',
                      ],
        'timeout' => 30,
    );

    /**
     * Instantiate a new Zenfolio client.
     *
     * @param string $AppName  The name of your application.
     * @param string $APIVer   The API endpoint. Defaults to 1.8.
     *
     * @return object
     */
    public function __construct($AppName = null, array $options = array())
    {
        $this->AppName = $AppName;
        if (is_null($AppName)) {
            throw new InvalidArgumentException('An application name is required for all Zenfolio interactions.');
        }

        $this->default_options['headers']['User-Agent'] = sprintf('%s using %s/%s', $this->AppName, $this->default_options['headers']['User-Agent'], self::VERSION);
        # Set the required X-Zenfolio-User-Agent to match the User-Agent
        $this->default_options['headers']['X-Zenfolio-User-Agent'] = $this->default_options['headers']['User-Agent'];

        if (isset($options['api_version'])) {
          $this->default_options['api_version'] = $options['api_version'];
        }

        # Setup the handler stack - we'll need this later.
        $this->stack = (isset($options['handler'])) ? $options['handler'] : HandlerStack::create();
        $this->default_options['handler'] = $this->stack;

        $this->httpClient = new GuzzleClient($this->default_options);
    }

    /**
     * Dynamic method handler.  This function handles all HTTP method calls
     * not explicitly implemented as separate functions by phpZenfolio.
     *
     * @param string $method HTTP method.
     * @param array  $args   Array of options for the HTTP method.
     *
     * @return object Decoded JSON response from Zenfolio.
     */
    public function __call($method, $args)
    {
        if (empty($args)) {
            throw new InvalidArgumentException('All methods need an argument.');
        }
        # Ensure the per-request options are empty
        $this->request_options = [];
        $this->client = self::getHttpClient();

        # TODO: Can I move this into the constructor?
        $url = '/api/'.$this->default_options['api_version'].'/zfapi.asmx';

        if (!is_null($this->authToken)) {
            $this->request_options['headers']['X-Zenfolio-Token'] = $this->authToken;
        }

        if (!is_null($this->keyring)) {
          $this->request_options['headers']['X-Zenfolio-Keyring'] = $this->keyring;
        }


        $this->performRequest($method, $url, $args);

        return $this->processResponse($method);
    }

    /**
     * Private function that performs the actual request to the Zenfolio API.
     *
     * @param string $method The HTTP method for the request.
     * @param string $url    The destination URL for the request.
     */
    private function performRequest($method, $url, $args)
    {
        # To keep things unique, we set the ID to the sha1 of the method
        $this->id = sha1($method . implode($args));

        $this->request_options['json'] = array('method' => $method, 'params' => $args, 'id' => $this->id);

        # Merge the request and default options
        $this->request_options = array_merge($this->default_options, $this->request_options);

        # Perform the API request
        $this->response = $this->client->request('POST', $url, $this->request_options);
    }

    /**
     * Private function to process the response from SmugMug and return it in a nice
     * user-friendly manner.
     *
     * This is in a single function so we don't repeat the same steps for each method.
     *
     * @param string|null $method The method we're expecting the output for.
     *
     * @return mixed
     */
    private function processResponse($method = null)
    {
        $body = json_decode((string) $this->response->getBody());

        # Bail early if the ID returned doesn't match that sent.
        if ($body->id != $this->id) {
            throw new UnexpectedValueException("Incorrect response ID. (request ID: {$this->id}, response ID: {$body->id})");
        }

        switch ($method) {
            case 'AuthenticatePlain':
                $this->authToken = $result;
            break;
            case 'KeyringAddKeyPlain':
                $this->keyring = $result;
            break;
            default:
                return $body->result;
            break;
        }
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
     *					the plaintext (true) or the challenge-response authentication
     *					method (false). Defaults to false.
     * @return string
     */
    public function login($username, $password, $plaintext = false)
    {
        if ($plaintext === true) {
            $this->authToken = $this->AuthenticatePlain($username, $password);
        } else {
            $cr = $this->GetChallenge($username);
            $salt = self::byteArrayDecode($cr['PasswordSalt']);
            $challenge = self::byteArrayDecode($cr['Challenge']);
            $password = utf8_encode($password);

            $passHash = hash('sha256', $salt.$password, true);
            $chalHash = hash('sha256', $challenge.$passHash, true);
            $proof = array_values(unpack('C*', $chalHash));
            $this->setAuthToken($this->Authenticate($cr['Challenge'] , $proof));
        }
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
     * Get the authToken.  This is only valid for just over 24 hours.
     *
     * @return string
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }


    /**
     * @return object HttpClient object instantiated with this class.
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return interval HTTP status code for the last request
     */
    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return array HTTP headers as an array for the last request
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * @return string HTTP status message for the last request
     */
    public function getReasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * @return array Default options instantiated with this class.
     */
    public function getDefaultOptions()
    {
        return $this->default_options;
    }

    /**
     * @return object Full json_decoded response from Zenfolio without any phpZenfolio touches.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array Request options that are set just before a request is made and cleared before every request.
     */
    public function getRequestOptions()
    {
        return $this->request_options;
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
    private static function byteArrayDecode($array)
    {
        return call_user_func_array('pack', array_merge(array('C*'),(array) $array));
    }
}