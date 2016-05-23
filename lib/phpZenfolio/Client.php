<?php

namespace phpZenfolio;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use phpZenfolio\Exception\InvalidArgumentException;
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

