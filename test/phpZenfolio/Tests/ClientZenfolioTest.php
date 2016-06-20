<?php

namespace phpZenfolio\Tests;

use phpZenfolio\Client;

class ClientZenfolioTest extends \PHPUnit_Framework_TestCase
{
    public function setup()
    {
        $this->client = new \phpSmug\Client('phpZenfolio Unit Testing/'.phpZenfolio\Client::VERSION.' (https://phpzenfolio.com)');
        $this->client->setAuthToken(getenv('AUTH_TOKEN'));
    }

    /**
     * A quick handy feature to ensure we don't attempt to run any tests if these
     * env vars aren't set.
     */
    public function checkEnvVars()
    {
        if (empty(getenv('AUTH_SECRET'))) {
            $this->markTestSkipped("Environment variable $env_var not set.");

            return;
        }
    }
}
