
# Other Notes

## Access Zenfolio via a Proxy

Accessing Zenfolio with phpZenfolio through a proxy is possible by passing the `proxy` option when instantiating the client:

```php
<?php
$client = new phpZenfolio\Client('My Cool App/1.0 (http://app.com)', ['proxy' => 'http://[PROXY_ADDRESS]:[PORT]']));
```

All your requests will pass through the specified proxy on the specified port.

If you need a username and password to access your proxy, you can include them in the URL in the form: `http://[USERNAME]:[PASSWORD]@[PROXY_ADDRESS]:[PORT]`.

## Image URLs Helper

To make it easy to obtain the direct URL to an image, phpZenfolio supplies an `imageURL()` method that takes the Photo object as returned by methods like `LoadPhoto()` and `LoadPhotoSetPhotos()` and an integer for the desired photo size where the integer is one of those documented at <http://www.zenfolio.com/zf/help/api/guide/download>.

For example:

```php
<?php
$client = new phpZenfolio\Client('My Cool App/1.0 (http://app.com)');
$photos = $client->LoadPhotoSetPhotos([PHOTOSETID], [STARTINGINDEX], [NUMBEROFPHOTOS]);
foreach ($photos as $photo) {
    echo '<img src="'.phpZenfolio\Client::imageUrl($photo, 1).'" />';
}
```

## Examples

phpZenfolio comes with four examples to help get you on your way.

* `example-popular.php` illustrates how to obtain the 96 most popular galleries and display their title image linking to each individual gallery.
* `example-login.php` illustrates how to login and display the images in your first photoset or collection.
* `example-user.php` illustrates how to display the first 96 public photos of the specified user's first public photoset found.
* `example-create-photoset.php` illustrates how to create a new gallery photoset in the authenticated user's root photoset group, and upload an image to this gallery.

## Need Help or Have Questions?

The best way to get help with implementing phpZenfolio into your projects is to open an [issue](https://github.com/lildude/phpZenfolio/issues).  This allows you to easily search for other issues where others may have asked to the same questions or hit the same problems and if they haven't, your issue will add to the resources available to others at a later date.

Please don't be shy. If you've got a question, problem or are just curious about something, there's a very good chance someone else is too, so go ahead and open an issue and ask.

## Contributing

Found a bug or want to make phpZenfolio even better? Please feel free to open a pull request with your changes, but be sure to check out the [CONTRIBUTING.md](CONTRIBUTING.md) first for some tips and guidelines. No pull request is too small.

## Changes

All notable changes to this project are documented in [CHANGELOG.md](CHANGELOG.md).

## License

All code is licensed under the [MIT License](https://opensource.org/licenses/MIT) and all documentation is licensed under the [CC BY 4.0 license](https://creativecommons.org/licenses/by/4.0/).
