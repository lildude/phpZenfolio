<?php

namespace phpZenfolio;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
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
