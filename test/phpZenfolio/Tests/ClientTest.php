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
        $this->fauxGoodResponse = '{"error":null,"id":"745aa3524078c47a1c4bdfa4877f2529549795a2","result":{"foo":"bar"}}';
        $this->fauxBadIdResponse = '{"error":null,"id":"I-am-a-unique-id","result":{"foo":"bar"}}';
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
