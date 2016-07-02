
# Uploading & Replacing Images

Uploading is very easy.  You can either upload an image from your local system using the phpZenfolio supplied `upload()` method, or from a location on the web using the API's `CreatePhotoFromUrl()` method.

## Upload a Local File:

To upload from your local filesystem using the phpZenfolio `upload()` method, you will need to have logged into Zenfolio via the API using the `login()` method and have the photoset object, the `PhotoSetId`, or it's `UploadUrl` as returned by the API.

Then it's a matter of calling the method with the various optional parameters.

* Upload using the photoset object:

  ```php
  <?php
  $client->upload($photoset, "/path/to/image.jpg");
    ```

* Upload using the PhotoSetId:

  ```php
  <?php
  $client->upload(123456, "/path/to/image.jpg");
  ```

* Upload using the UploadUrl:

  ```php
  <?php
  $client->upload('http://up.zenfolio.com/....', '/path/to/image.jpg');
  ```

At this time, the only supported options you can pass at the time of uploading are a `filename`, the `type` and the `modified` parameter which takes a RFC2822 formatted date string...

```php
<?php
$client->upload(123456, '/path/to/image.mpg',
    ['filename' => 'newfilename.mpg',
     'modified' => 'Thu, 14 Jan 2010 13:08:07 +0000',
     'type' => 'video']);
```

If you don't specify a filename, the original filename is used.


## Upload an Image from a URL:

Uploading to Zenfolio using a URL is done purely by the Zenfolio `CreatePhotoFromUrl()` API method:

```php
<?php
$client->CreatePhotoFromUrl(12344, 'http://www.example.com/images/image.jpg');
```

You can find full details on the options this method accepts in the [CreatePhotoFromUrl](http://www.zenfolio.com/zf/help/api/ref/methods/createphotofromurl) documentation.

Unfortunately, there is no way to pass things like the photo title etc at the time of upload. You will need to set these later using the `UpdatePhoto()` method.


## Replacing Images

In order to replace a photo, you will need to upload a new photo and then replace the old photo with the new using the Zenfolio [`ReplacePhoto()`](http://www.zenfolio.com/zf/help/api/ref/methods/replacephoto) API method.
