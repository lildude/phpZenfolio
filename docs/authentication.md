
# Authentication

Many of the Zenfolio API methods are open to all users, whether they have Zenfolio accounts or not, however anything private or related to modification requires authentication.

The Zenfolio API provides [two methods of authentication](http://www.zenfolio.com/zf/help/api/guide/auth): Plain-Text and Challenge-Response.  Both are equally good with the slightly more secure method being the Challenge-Response method as your password never travels across the wire.

phpZenfolio allows you to use the API methods as documented, however to make things easy, a single `login()` method exists to allow you to authenticate using either of these authentication methods:

* Challenge-Response (default):

  ```
  $client->login("<username>", "<password>");
  ```

* Plain-Text:

  ```
  # Setting the third argument to `true` confirms you want to use plain-text
  $client->login("<username>", "<password>", true);
  ```

Both methods use HTTPS/SSL for the authentication step to ensure your username and password are encrypted when transmitted to Zenfolio.
