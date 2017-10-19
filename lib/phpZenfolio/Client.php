<?php

namespace phpZenfolio;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use phpZenfolio\Exception\InvalidArgumentException;
use phpZenfolio\Exception\BadMethodCallException;
use phpZenfolio\Exception\UnexpectedValueException;
use phpZenfolio\Exception\RuntimeException;

class Client
{
    /**
     * A few default variables.
     */
    const VERSION = '2.0.1';
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
        'debug' => false,
    );

    /**
     * Instantiate a new Zenfolio client.
     *
     * @param string $AppName The name of your application
     * @param string $APIVer  The API endpoint. Defaults to 1.8
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
        // Set the required X-Zenfolio-User-Agent to match the User-Agent
        $this->default_options['headers']['X-Zenfolio-User-Agent'] = $this->default_options['headers']['User-Agent'];

        $this->default_options = array_merge($this->default_options, $options);

        // Setup the handler stack - we'll need this later.
        $this->stack = (isset($options['handler'])) ? $options['handler'] : HandlerStack::create();
        $this->default_options['handler'] = $this->stack;

        $this->httpClient = new GuzzleClient($this->default_options);
    }

    /**
     * Dynamic method handler.  This function handles all HTTP method calls
     * not explicitly implemented as separate functions by phpZenfolio.
     *
     * @param string $method HTTP method
     * @param array  $args   Array of options for the HTTP method
     *
     * @return object Decoded JSON response from Zenfolio
     */
    public function __call($method, $args)
    {
        // Ensure the per-request options are empty
        $this->request_options = [];
        $this->client = self::getHttpClient();

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
     * @param string $method The HTTP method for the request
     * @param string $url    The destination URL for the request
     */
    private function performRequest($method, $url, $args = null)
    {
        if ($method != 'upload') {
            // To keep things unique, we set the ID to the sha1 of the method
            $this->id = sha1($method);
            $this->request_options['json'] = array('method' => $method, 'params' => $args, 'id' => $this->id);
        }

        // Merge the request and default options
        $this->request_options = array_merge($this->default_options, $this->request_options);
        // Perform the API request
        $this->response = $this->client->request('POST', $url, $this->request_options);
    }

    /**
     * Private function to process the response from SmugMug and return it in a nice
     * user-friendly manner.
     *
     * This is in a single function so we don't repeat the same steps for each method.
     *
     * @param string|null $method The method we're expecting the output for
     *
     * @return mixed
     */
    private function processResponse($method = null)
    {
        $body = json_decode((string) $this->response->getBody());

        // Bail if the ID returned doesn't match that sent for non-upload methods.
        if ($method != 'upload') {
            if ($body->id != $this->id) {
                throw new UnexpectedValueException("Incorrect response ID. (request ID: {$this->id}, response ID: {$body->id})");
            }
        }

        // Bail if there is an error
        if (isset($body->error) && !is_null($body->error)) {
            if ($body->error->message == 'No such method') {
                throw new BadMethodCallException("{$body->error->code}: {$body->error->message}");
            } else {
                // If the message contains "contact Support" it's referring to Zenfolio support, so lets make that clear.
              $msg = ((isset($body->error->code)) ? $body->error->code.': ' : '').str_replace('contact Support', 'contact Zenfolio Support', $body->error->message);
                throw new RuntimeException($msg);
            }
        }

        if ($method == 'KeyringAddKeyPlain') {
            $this->keyring = $body->result;
        }

        return ($method == 'upload') ? $body : $body->result;
    }

    /**
     * Single login function for all login methods.
     *
     * I've created this function to make it easy to login to Zenfolio using either
     * the plaintext authentication method, or the more secure challenge-response (default)
     * authentication method.
     *
     * @param string $username  The Zenfolio username
     * @param string $password  The Zenfolio username's password
     * @param bool   $plaintext (Optional) Set whether the login should use
     *                          the plaintext (true) or the challenge-response authentication
     *                          method (false). Defaults to false
     *
     * @return string
     */
    public function login($username, $password, $plaintext = false)
    {
        if ($plaintext === true) {
            $authToken = $this->AuthenticatePlain($username, $password);
        } else {
            $cr = $this->GetChallenge($username);
            $salt = self::byteArrayDecode($cr->PasswordSalt);
            $challenge = self::byteArrayDecode($cr->Challenge);
            $password = utf8_encode($password);

            $passHash = hash('sha256', $salt.$password, true);
            $chalHash = hash('sha256', $challenge.$passHash, true);
            $proof = array_values(unpack('C*', $chalHash));
            $authToken = $this->Authenticate($cr->Challenge, $proof);
        }
        $this->setAuthToken($authToken);

        return $this->authToken;
    }

    /**
     * To make life easy for phpZenfolio users, I've created a single method that
     * can be used to upload files.  This uses the Simplified HTTP POST method as
     * detailed at {@link http://www.zenfolio.com/zf/help/api/guide/upload}.
     *
     * @param string $photoSet The ID, object or URL of the PhotoSet into which
     *                         you wish the image to be uploaded
     * @param string $file     The path to the local file that is being uploaded
     * @param array  $args     An array of optional arguments for the upload. The
     *                         only supported options are `filename` to specify the filename
     *                         to use, `modified` to specify the modification date and `type`
     *                         to specify the upload type of `video` or `raw`. `type`
     *                         defaults to `photo`
     *
     * @return string
     *
     * @link http://www.zenfolio.com/zf/help/api/guide/upload
     **/
    public function upload($photoSet, $file, $args = array())
    {
        if (is_file($file)) {
            $fp = fopen($file, 'rb');
            $data = fread($fp, filesize($file));
            fclose($fp);
        } else {
            throw new InvalidArgumentException('File not found: '.$file);
        }

        $type_url = ((isset($args['type']) && ($args['type'] == 'video' || $args['type'] == 'raw')) ? ucfirst($args['type']) : '').'UploadUrl';
        if (is_object($photoSet)) {
            $upload_url = $photoSet->$type_url;
        } elseif (is_long($photoSet)) {
            $photo_set = $this->LoadPhotoSet($photoSet, 'Level1', false);
            $upload_url = $photo_set->$type_url;
        } else {
            // Assumes this is the correct upload URL
            $upload_url = $photoSet;
        }

        // Ensure the per-request options are empty
        $this->request_options = [];
        $this->client = self::getHttpClient();

        // Required headers
        $this->request_options['headers']['Content-Type'] = mime_content_type($file);
        $this->request_options['headers']['Content-Length'] = filesize($file);

        if (!is_null($this->authToken)) {
            $this->request_options['headers']['X-Zenfolio-Token'] = $this->authToken;
        }

        if (!is_null($this->keyring)) {
            $this->request_options['headers']['X-Zenfolio-Keyring'] = $this->keyring;
        }

        $this->request_options['query']['filename'] = (isset($args['filename'])) ? $args['filename'] : basename($file);
        if (isset($args['modified'])) {
            $this->request_options['query']['modified'] = $args['modified'];
        }

        $this->request_options['body'] = $data;

        $this->performRequest('upload', $upload_url);

        return $this->processResponse('upload');
    }

    /**
     * Public function that returns the image url for an image. This is only for
     * sizes other than the original size.
     *
     * @param array $photo The Photo object of the photo you with to obtain the url for
     * @param int   $size  The Zenfolio supplied image size
     *
     * @see http://www.zenfolio.com/zf/help/api/guide/download
     *
     * @return string
     */
    public static function imageUrl(\stdClass $photo, $size)
    {
        return "http://{$photo->UrlHost}{$photo->UrlCore}-{$size}.jpg?sn={$photo->Sequence}&tk={$photo->UrlToken}";
    }

    /**
     * Set authToken.  This is useful for those who want to reuse the same authentication
     * token within a 24 hour period.
     *
     * @param string $token Token returned from login() method. Set to an empty string to unset
     */
    public function setAuthToken($token)
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
     * Set keyring.
     *
     * @param string $keyring Keyring returned from login() method. Set to an empty string to unset
     */
    public function setKeyring($keyring)
    {
        $this->keyring = $keyring;
    }

    /**
     * Get the authToken.
     *
     * @return string
     */
    public function getKeyring()
    {
        return $this->keyring;
    }

    /**
     * @return object HttpClient object instantiated with this class
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
     * @return array Default options instantiated with this class
     */
    public function getDefaultOptions()
    {
        return $this->default_options;
    }

    /**
     * @return object Full json_decoded response from Zenfolio without any phpZenfolio touches
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array Request options that are set just before a request is made and cleared before every request
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
     * @param array
     *
     * @return string
     */
    private static function byteArrayDecode($array)
    {
        return call_user_func_array('pack', array_merge(array('C*'), (array) $array));
    }
}
