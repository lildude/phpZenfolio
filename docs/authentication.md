
# Authentication

Many of the Zenfolio API methods are open to all users, whether they have Zenfolio accounts or not, however anything private or related to modification requires authentication.

The Zenfolio API provides [two methods of authentication](https://www.zenfolio.com/zf/help/api/guide/auth): Plain-Text and Challenge-Response.  Both are equally good with the slightly more secure method being the Challenge-Response method as your password never travels across the wire.

phpZenfolio allows you to use the API methods as documented, however to make things easy, a single `login()` method exists to allow you to authenticate using either of these authentication methods:

* Challenge-Response (default):

  ```php
  <?php
  $client->login('[USERNAME]', '[PASSWORD]');
  ```

* Plain-Text:

  ```php
  <?php
  // Setting the third argument to `true` confirms you want to use plain-text
  $client->login('[USERNAME]', '[PASSWORD]', true);
  ```

Both methods use HTTPS/SSL for the authentication step to ensure your username and password are encrypted when transmitted to Zenfolio.

The `login()` method returns the authentication token.  You can store this and re-use it in future requests using phpZenfolio's `setAuthToken()` method:

```php
<?php
$client = new phpZenfolio\Client('My Cool App/1.0 (https://app.com)'));
$client->setAuthToken('[AUTH_TOKEN]');
```

Keep in mind that the authentication token is only valid for slightly more than 24 hours. If you expect your application to run for longer than 24 hours, it needs to periodically reauthenticate to obtain a fresh authentication token.

Additionally, keep in mind that when authenticating, you will gain full access to _your_ content and only gain access to the albums, collections and photos of other users granted to you by that user. This means methods like `[LoadGroupHierarchy()](https://www.zenfolio.com/zf/help/api/ref/methods/loadgrouphierarchy)` and `[LoadPublicProfile()](https://www.zenfolio.com/zf/help/api/ref/methods/loadpublicprofile)` will only return information if the user in question has explicitly granted you access to their root photoset group or if they have made their root photoset group public.
