<?php

namespace phpZenfolio\Tests;

use phpZenfolio\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;
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
        $this->fauxGoodResponse = '{"error":null,"id":"'.sha1('TestMethod').'","result":{"foo":"bar"}}';
        $this->fauxBadIdResponse = '{"error":null,"id":"I-am-a-unique-id","result":{"foo":"bar"}}';
        $this->fauxErrorResponse = '{"result":null,"error":{"code":"E_DUMMYERROR","message":"This is a dummy error."},"id":"'.sha1('TestMethod').'"}';
        $this->fauxChallengeResponse = '{"result":{"$type":"AuthChallenge","PasswordSalt":[0,9,8,7,6,5],"Challenge":[0,1,2,3,4,5,6,7,8,9,0]},"error":null,"id":"'.sha1('GetChallenge').'"}';
        $this->fauxAuthenticateResponse = '{"result":"'.$this->fauxAuthToken.'","error":null,"id":"'.sha1('Authenticate').'"}';
        $this->fauxAuthenticatePlainResponse = '{"result":"'.$this->fauxAuthToken.'","error":null,"id":"'.sha1('AuthenticatePlain').'"}';
        $this->fauxAuthdResponse = 'TBC';
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
     * @expectedException phpZenfolio\Exception\InvalidArgumentException
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
        $this->assertEquals('181f23563bbfb826c0321f586cfafa64680620af', $decoded_response->id);
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
     * @expectedException phpZenfolio\Exception\UnexpectedValueException
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
     * @expectedException phpZenfolio\Exception\RuntimeException
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
