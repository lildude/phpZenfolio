<?php

namespace phpZenfolio\Tests;

use phpZenfolio\Client as Client;

class ClientZenfolioTest extends \PHPUnit_Framework_TestCase
{
    public function setup()
    {
        $this->client = new Client('phpZenfolio Unit Testing/'.Client::VERSION.' (https://phpzenfolio.com)', ['verify' => true]);
        if (getenv('USERNAME') && getenv('PASSWORD')) {
            $this->client->login(getenv('USERNAME'), getenv('PASSWORD'));
        }
    }

    /**
     * A quick handy feature to ensure we don't attempt to run any tests if these
     * env vars aren't set.
     */
    public function checkEnvVars()
    {
        foreach (['USERNAME', 'PASSWORD'] as $env_var) {
            if (getenv($env_var) === false) {
                $this->markTestSkipped("Environment variable $env_var not set.");

                return;
            }
        }
    }

    /**
     * @test
     *
     * Load user's private profile and return the global ID
     */
    public function shouldGetGroupHierarchy()
    {
        $this->checkEnvVars();

        $response = $this->client->LoadGroupHierarchy('colinseymour');
        $this->assertTrue(is_object($response));
        $this->assertEquals('Group', $response->{'$type'});
        $this->assertNotNull($response->Id);

        return $response->Id;
    }

    /**
     * @test
     * @depends shouldGetGroupHierarchy
     *
     * Tests creating a new PhotoSet
     */
    public function shouldCreateNewPhotoSet($id)
    {
        $uniqid = uniqid('UnitTesting-');

        // The photoSetUpdater can be an array or a stdObject.
        $photoSetUpdater = [
            'Title' => $uniqid,
            'Caption' => 'Gallery created via unit testing phpZenfolio',
            'Keywords' => ['foo', 'boo', 'goo'],
            'CustomReference' => "unittesting/$uniqid",
        ];

        $response = $this->client->CreatePhotoSet($id, 'Gallery', $photoSetUpdater);
        $this->assertTrue(is_object($response));
        $this->assertEquals('PhotoSet', $response->{'$type'});
        $this->assertEquals($photoSetUpdater['Title'], $response->Title);
        $this->assertEquals($photoSetUpdater['Caption'], $response->Caption);
        $this->assertTrue(is_array($response->Keywords));
        $this->assertEquals(3, count($response->Keywords));
        $this->assertEquals('http://colinseymour.zenfolio.com/'.$photoSetUpdater['CustomReference'], $response->PageUrl);

        return $response;
    }

    /**
     * @test
     * @depends shouldCreateNewPhotoSet
     *
     * Tests adding a photo & non-photo file to a photoset
     */
    public function shouldUploadToPhotoSet($photoSetObject)
    {
        $response = $this->client->upload($photoSetObject, 'README.md', ['type' => 'raw']);
        $this->assertTrue(empty($response));

        $response = $this->client->upload($photoSetObject, 'examples/phpZenfolio-logo.png');
        $this->assertTrue(is_int($response));

        // Return the photo ID
        return $response;
    }

    /**
     * @test
     * @depends shouldUploadToPhotoSet
     *
     * Tests setting the photo title etc
     */
    public function shouldUpdatePhotoDetails($photo_id)
    {
        $photoUpdater = [
            'Title' => uniqid('Photo-'),
            'Caption' => 'Photo uploaded via unit testing phpZenfolio',
            'Keywords' => ['foo', 'boo', 'goo'],
        ];
        $response = $this->client->UpdatePhoto($photo_id, $photoUpdater);
        $this->assertTrue(is_object($response));
        $this->assertEquals('Photo', $response->{'$type'});
        $this->assertEquals($photoUpdater['Title'], $response->Title);
        $this->assertEquals($photoUpdater['Caption'], $response->Caption);
        $this->assertTrue(is_array($response->Keywords));
        $this->assertEquals(3, count($response->Keywords));

        return $photo_id;
    }

    /**
     * @test
     * @depends shouldUpdatePhotoDetails
     *
     * Tests deleting a photo
     */
    public function shouldDeletePhoto($photo_id)
    {
        $response = $this->client->DeletePhoto($photo_id);
        $this->assertTrue(is_null($response));
    }

    /**
     * @test
     * @depends shouldCreateNewPhotoSet
     *
     * Tests deleting a photoset
     */
    public function shouldDeletePhotoSet($photoSetObject)
    {
        $response = $this->client->DeletePhotoSet($photoSetObject->Id);
        $this->assertTrue(is_null($response));
    }
}
