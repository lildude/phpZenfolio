
# Other Notes

## Access Zenfolio via a Proxy

Accessing Zenfolio with phpZenfolio through a proxy is possible by passing the `proxy` option when instantiating the client:

```php
$client = new phpZenfolio\Client('My Cool App/1.0 (http://app.com)', ['proxy' => 'http://[proxy_address]:[port]']));
```

All your requests will pass through the specified proxy on the specified port.

If you need a username and password to access your proxy, you can include them in the URL in the form: `http://[username]:[password]@[proxy_address]:[port]`.

## Image URLs Helper

To make it easy to obtain the direct URL to an image, phpZenfolio supplies an `imageURL()` method that takes the Photo object as returned by methods like `LoadPhoto()` and `LoadPhotoSetPhotos()` and an integer for the desired photo size where the integer is one of those documented at http://www.zenfolio.com/zf/help/api/guide/download .

  For example:

  ```
  $client = new phpZenfolio\Client('My Cool App/1.0 (http://app.com)');
  $photos = $client->LoadPhotoSetPhotos(<photosetID>, <startingIndex>, <numberOfPhotos>);
  foreach ($photos as $photo) {
      echo '<img src="'.phpZenfolio\Client::imageUrl($photo, 1).'" />';
  }
  ```

## Examples

phpZenfolio comes with 3 examples to help get you on your way.

* `example-popular.php` illustrates how to obtain the 96 most popular galleries and display their title image linking to each individual gallery.
* `example-login.php` illustrates how to login and display the images in your first photoset or collection.
* `example-user.php` illustrates how to display the first 96 public photos of the specified user's first public photoset found.

## Getting Help or Have Questions

The best way to get help with implementing phpZenfolio into your projects is to open an [issue](https://github.com/lildude/phpZenfolio/issues).  This allows you to easily search for other issues where others may have asked to the same questions or hit the same problems and if they haven't, your issue will add to the resources available to others at a later date.

Don't be shy, feel free to ask any question.

## Contributing

Found a bug or want to make phpZenfolio even better? Please feel free to open a pull request with your changes, but be sure to check out the [CONTRIBUTING.md](CONTRIBUTING.md) first for some tips and guidelines. No pull request is too small.

## License

phpZenfolio is licensed under the MIT License - see the [LICENSE.md]() file for details
