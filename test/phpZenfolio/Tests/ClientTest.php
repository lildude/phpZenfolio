<?php

namespace phpZenfolio\Tests;

use phpZenfolio\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Setup a few variables for use in later tests.
     */
    public function setup()
    {
        $this->AppName = 'Testing phpZenfolio';
        $this->user = 'random-user';
        $this->fauxAuthToken = 'this-is-the-auth-token';
        $this->fauxKeyring = 'this-is-the-keyring';
        $this->fauxGoodResponse = '{"error":null,"id":"'.sha1('TestMethod').'","result":{"foo":"bar"}}';
        $this->fauxBadIdResponse = '{"error":null,"id":"I-am-a-unique-id","result":{"foo":"bar"}}';
        $this->fauxErrorResponse = '{"result":null,"error":{"code":"E_DUMMYERROR","message":"This is a dummy error."},"id":"'.sha1('TestMethod').'"}';
        $this->fauxBadMethodResponse = '{"result":null,"error":{"code":"E_INVALIDPARAM","message":"No such method"},"id":"'.sha1('BogusMethod').'"}';
        $this->fauxUnexpectedErrorResponse = '{"result":null,"error":{"message":"An unexpected error has occurred. Please try again later. If this problem persists, contact Support.","error":null},"id":"'.sha1('LoadPhotoSet').'"}';

        $this->fauxChallengeResponse = '{"result":{"$type":"AuthChallenge","PasswordSalt":[0,9,8,7,6,5],"Challenge":[0,1,2,3,4,5,6,7,8,9,0]},"error":null,"id":"'.sha1('GetChallenge').'"}';
        $this->fauxAuthenticateResponse = '{"result":"'.$this->fauxAuthToken.'","error":null,"id":"'.sha1('Authenticate').'"}';
        $this->fauxAuthenticatePlainResponse = '{"result":"'.$this->fauxAuthToken.'","error":null,"id":"'.sha1('AuthenticatePlain').'"}';
        $this->fauxKeyringResponse = '{"result":"'.$this->fauxKeyring.'","error":null,"id":"'.sha1('KeyringAddKeyPlain').'"}';
        // The photoObject has been cutdown to just the fields we need for the URL generation.
        $this->photoObject = json_decode('{"Sequence": "","UrlCore": "/img/s/v-2/p1234567890","UrlHost": "'.$this->user.'.zenfolio.com","UrlToken": "this-is-the-url-token"}');
        // The photoSetObject has been cutdown to just the fields we need for obtaining the upload URL.
        $this->fauxPhotoSetObjectResponse = '{"result":{"UploadUrl":"http://up.zenfolio.com/'.$this->user.'/p123456789/upload2.ushx","VideoUploadUrl":"http://up.zenfolio.com/'.$this->user.'/p123456789/video.ushx","RawUploadUrl":"http://up.zenfolio.com/'.$this->user.'/p123456789/raw.ushx"},"error":null,"id":"'.sha1('LoadPhotoSet').'"}';
        $this->photoSize = '11';  // Large thumbnail
        $this->fauxDeleteResponse = '';
    }
    /**
     * @test
     */
    public function shouldNotHaveToPassHttpClientToConstructorWithDefaultOptionsSet()
    {
        $client = new Client($this->AppName);
        $this->assertInstanceOf('GuzzleHttp\Client', $client->getHttpClient());
        $options = $client->getDefaultOptions();

        $this->assertEquals('https://api.zenfolio.com/', $options['base_uri']);
        $this->assertEquals($client->AppName.' using phpZenfolio/'.$client::VERSION, $options['headers']['User-Agent']);
        $this->assertEquals($client->AppName.' using phpZenfolio/'.$client::VERSION, $options['headers']['X-Zenfolio-User-Agent']);
        $this->assertEquals('1.8', $options['api_version']);
        $this->assertEquals('application/json', $options['headers']['Accept']);
        $this->assertEquals(30, $options['timeout']);
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\InvalidArgumentException
     * @expectedExceptionMessage An application name is required for all Zenfolio interactions.
     */
    public function shouldThrowExceptionIfNoAppName()
    {
        $client = new Client();
    }

    /**
     * @test
     */
    public function shouldSetApiVer()
    {
        $client = new Client($this->AppName, ['api_version' => '1.6']);
        $this->assertInstanceOf('GuzzleHttp\Client', $client->getHttpClient());
        $options = $client->getDefaultOptions();
        $this->assertEquals('1.6', $options['api_version']);
    }

    /**
     * @test
     * TODO: Start a proxy and ensure it's actually used.
     */
    public function shouldSetProxy()
    {
        $client = new Client($this->AppName, ['proxy' => 'http://proxy.foo:8080']);
        $options = $client->getDefaultOptions();
        $this->assertEquals('http://proxy.foo:8080', $options['proxy']);
    }

    /**
     * @test
     */
    public function shouldGetReasonPhrase()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $client->TestMethod();
        $this->assertEquals('OK', $client->getReasonPhrase());
    }

    /**
     * @test
     */
    public function shouldGetHeaders()
    {
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $r = $client->TestMethod();

        $this->assertArrayHasKey('X-Foo', $client->getHeaders());
    }

    /**
     * @test
     */
    public function shouldGetStatusCode()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $r = $client->TestMethod();

        $this->assertEquals('200', $client->getStatusCode());
    }

    /**
     * @test
     */
    public function shouldReturnUntouchedResponse()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);
        $client->TestMethod();
        $decoded_response = (json_decode((string) $client->getResponse()->getBody()));
        $this->assertNotNull($decoded_response->result);
        $this->assertNotNull($decoded_response->id);
        $this->assertNull($decoded_response->error);
        $this->assertEquals('bar', $decoded_response->result->foo);
        $this->assertEquals(sha1('TestMethod'), $decoded_response->id);
    }

    /**
     * @test
     */
    public function shouldReturnResultObject()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->TestMethod();

        $this->assertObjectHasAttribute('foo', $response);
        $this->assertEquals('bar', $response->foo);
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\UnexpectedValueException
     * @expectedExceptionMessage Incorrect response ID. (request ID: 181f23563bbfb826c0321f586cfafa64680620af, response ID: I-am-a-unique-id)
     */
    public function shouldThrowExceptionIfResponseIdDoesntMatchRequestId()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxBadIdResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->TestMethod();
    }

    /**
     * @test
     */
    public function shouldLoginAndGetAuthTokenUsingChallenge()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxChallengeResponse),
            new Response(200, [], $this->fauxAuthenticateResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->login($this->user, 'secret');
        $this->assertEquals($this->fauxAuthToken, $response);
        $this->assertEquals($client->getAuthToken(), $response);
    }

    /**
     * @test
     */
    public function shouldLoginAndGetAuthTokenUsingPlaintext()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxAuthenticatePlainResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->login($this->user, 'secret', true);
        $this->assertEquals($this->fauxAuthToken, $response);
        $this->assertEquals($client->getAuthToken(), $response);
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\RuntimeException
     * @expectedExceptionMessage E_DUMMYERROR: This is a dummy error.
     */
    public function shouldThrowExceptionOnErrorFromZenfolio()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxErrorResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->TestMethod();
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\RuntimeException
     * @expectedExceptionMessage An unexpected error has occurred. Please try again later. If this problem persists, contact Zenfolio Support.
     */
    public function shouldThrowExceptionOnUnexpectedErrorFromZenfolio()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxUnexpectedErrorResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        // This is a valid call that results in an unexpected error because of the missing "loadphotos" bool.
        $response = $client->LoadPhotoSet(12345, 'Level1');
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\BadMethodCallException
     * @expectedExceptionMessage E_INVALIDPARAM: No such method
     */
    public function shouldThrowBadMethodCallExceptionForBogusMethod()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxBadMethodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $response = $client->BogusMethod();
    }

    /**
     * @test
     */
    public function shouldSetXZenfolioTokenHeader()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);
        $client->setAuthToken($this->fauxAuthToken);

        $client->TestMethod();

        $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('X-Zenfolio-Token', $request_options['headers']);
        $this->assertEquals($this->fauxAuthToken, $request_options['headers']['X-Zenfolio-Token']);
    }

    /**
     * @test
     */
    public function shouldSetXZenfolioKeyringHeader()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxKeyringResponse),
            new Response(200, [], $this->fauxGoodResponse),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);

        $client->KeyringAddKeyPlain('the-keyring', '1234567890', 'the-password');
        $client->TestMethod();

        $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('X-Zenfolio-Keyring', $request_options['headers']);
        $this->assertEquals($this->fauxKeyring, $request_options['headers']['X-Zenfolio-Keyring']);
    }

    /**
     * @test
     */
    public function shouldReturnImgUrl()
    {
        $photo_url = \phpZenfolio\Client::imageUrl($this->photoObject, $this->photoSize);

        $this->assertEquals("http://{$this->user}.zenfolio.com/img/s/v-2/p1234567890-{$this->photoSize}.jpg?sn=&tk=this-is-the-url-token", $photo_url);
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\InvalidArgumentException
     * @expectedExceptionMessage File not found: /path/to/non/existant/file.jpg
     */
    public function shouldThrowExceptionIfUploadFileNotFound()
    {
        $client = new Client($this->AppName);
        $client->upload(123456789, '/path/to/non/existant/file.jpg');
    }

    /**
     * @test
     */
    public function shouldUploadToPhotoSet()
    {
        $mock = new MockHandler([
          new Response(200, []),  // Upload using photoset object
          new Response(200, [], $this->fauxPhotoSetObjectResponse), // LoadPhotoSet() called when using photoset ID for upload
          new Response(200, []), // Upload using photoset ID
          new Response(200, []), // Upload raw for non-image type using photoset object
          new Response(200, []), // Upload using upload URL
      ]);

        $handler = HandlerStack::create($mock);
        $container = [];
      // Add the history middleware to the handler stack.
      $history = Middleware::history($container);
        $handler->push($history);

        $client = new Client($this->AppName, ['handler' => $handler]);
        $client->setAuthToken($this->fauxAuthToken);

      // Upload by photoSet object with type=video, even though it's not a video ;-)
      $client->upload(json_decode($this->fauxPhotoSetObjectResponse)->result, './examples/phpZenfolio-logo.png', ['type' => 'video']);
      // Confirm our request options
      $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('Content-Type', $request_options['headers']);
        $this->assertEquals('image/png', $request_options['headers']['Content-Type']);
        $this->assertArrayHasKey('Content-Length', $request_options['headers']);
        $this->assertEquals(filesize('./examples/phpZenfolio-logo.png'), $request_options['headers']['Content-Length']);
        $this->assertArrayHasKey('filename', $request_options['query']);
        $this->assertEquals('phpZenfolio-logo.png', $request_options['query']['filename']);

      // Upload by photoset ID, with filename, modified and type=raw
      $mod_date = gmdate(DATE_RFC2822, time());
        $client->upload(123456789, './examples/phpZenfolio-logo.png', ['filename' => 'newfilename.png', 'modified' => $mod_date, 'type' => 'raw']);
      // Confirm out request options
      $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('modified', $request_options['query']);
        $this->assertEquals($mod_date, $request_options['query']['modified']);

      // Upload raw for non-image type using photoset object
      $client->upload(json_decode($this->fauxPhotoSetObjectResponse)->result, './README.md', ['type' => 'raw']);
      // Confirm the content type
      $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('Content-Type', $request_options['headers']);
        $this->assertEquals('text/plain', $request_options['headers']['Content-Type']);

      // Upload by photoset URL
      $client->upload(json_decode($this->fauxPhotoSetObjectResponse)->result->UploadUrl, './examples/phpZenfolio-logo.png');
        $request_options = $client->getRequestOptions();

      // Confirm the options are actually used
      foreach ($container as $key => $transaction) {
          $url = $transaction['request']->getUri();
          $query = $url->getQuery();
          switch ($key) {
              case 0:
                  // Video upload url
                  $this->assertEquals(json_decode($this->fauxPhotoSetObjectResponse)->result->VideoUploadUrl, $url->getScheme().'://'.$url->getHost().$url->getPath());
                  $this->assertEquals('filename=phpZenfolio-logo.png', $query);
              break;
              case 1:
                  // Skip as this is the LoadPhotoSet call
              break;
              case 2:
                  // Raw upload URL
                  $this->assertEquals(json_decode($this->fauxPhotoSetObjectResponse)->result->RawUploadUrl, $url->getScheme().'://'.$url->getHost().$url->getPath());
                  $this->assertEquals('filename=newfilename.png&modified='.rawurlencode($mod_date), $query);
              break;
              case 3:
                  // Raw upload URL for non-photo file
                  $this->assertEquals(json_decode($this->fauxPhotoSetObjectResponse)->result->RawUploadUrl, $url->getScheme().'://'.$url->getHost().$url->getPath());
                  $this->assertEquals('filename=README.md', $query);
              break;
              case 4:
                  // Upload url
                  $this->assertEquals(json_decode($this->fauxPhotoSetObjectResponse)->result->UploadUrl, $url->getScheme().'://'.$url->getHost().$url->getPath());
                  $this->assertEquals('filename=phpZenfolio-logo.png', $query);
              break;
          }
      }
    }

    /**
     * @test
     */
    public function shouldUploadToPhotoSetUsingKeyringAuth()
    {
        $mock = new MockHandler([
            new Response(200, [], $this->fauxKeyringResponse),
            new Response(200, []),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client($this->AppName, ['handler' => $handler]);
        $client->KeyringAddKeyPlain('the-keyring', '1234567890', 'the-password');

        $client->upload(json_decode($this->fauxPhotoSetObjectResponse)->result->UploadUrl, './examples/phpZenfolio-logo.png');

        $request_options = $client->getRequestOptions();
        $this->assertArrayHasKey('X-Zenfolio-Keyring', $request_options['headers']);
        $this->assertEquals($this->fauxKeyring, $request_options['headers']['X-Zenfolio-Keyring']);
    }

    /**
     * @test
     */
    public function shouldSetAndGetKeyring()
    {
        $client = new Client($this->AppName);
        $client->setKeyring($this->fauxKeyring);
        $this->assertEquals($this->fauxKeyring, $client->getKeyring());
    }

    /**
     * @test
     * @expectedException \phpZenfolio\Exception\BadMethodCallException
     * @expectedExceptionMessage Invalid method: badmethod
     */
    public function shouldThrowBadMethodCallException()
    {
        $this->markTestSkipped('Skipping as Zenfolio currently has a bug where it throws a 500 error when querying using a bogus method. This only affects json queries.');

        $client = new Client($this->AppName);
        $client->BogusMethod();
    }
}
