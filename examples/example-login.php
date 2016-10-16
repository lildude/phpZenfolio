<?php
/* Last updated with phpZenfolio 2.0.0
 *
 * This example file shows you how to login and display all the images of your
 * first photoset or collection, regardless of whether it's public or not.
 *
 * You'll need to set the following:
 *
 * - $appname to your application name, version and URL
 * - $username to your Zenfolio username
 * - $password to your user's password
 *
 * The application name and version is required, but there is no required format.
 * See the README.md for a suggested format.
 *
 * NOTE: If you set a username that is not yours, your access to their content will
 * be limited by the access they have granted. This may present itself as a `E_NOSUCHOBJECT`
 * error for method calls like LoadGroupHierarchy() and LoadPublicProfile().
 */

$appname = 'YOUR_APP_NAME/VER (URL)';
$username = 'A_USERNAME';
$password = 'A_PASSWORD';
?>
<html>
<head>
  <title>phpZenfolio First Album With Login Example</title>
  <style type="text/css">
    body { background-color: #fff; color: #444; font-family: sans-serif; }
    div { width: 750px; margin: 0 auto; text-align: center; }
    img { border: 0;}
  </style>
</head>
<body>
  <div>
    <a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
    <h2>phpZenfolio First Album With Login Example</h2>
<?php
require_once 'vendor/autoload.php';

try {
    $client = new phpZenfolio\Client($appname);
    // Login. As Plaintext is not passed, the challenge-response authentication method is used.
    $client->login($username, $password);
    // Load the User's hierachy
    $h = $client->LoadGroupHierarchy($username);
    // Load the photos for the first set/collection
    $photos = $client->LoadPhotoSetPhotos($h->Elements[0]->Id, 0, $h->Elements[0]->PhotoCount);
    // Display the 60x60 cropped thumbnails and link to the image page for each image in the first gallery/collection.
    foreach ($photos as $photo) {
        echo '<a href="'.$photo->PageUrl.'"><img src="'.phpZenfolio\Client::imageUrl($photo, 1).'" title="'.$photo->Title.'" alt="'.$photo->Id.'" width="60" height="60" /></a>';
    }
} catch (Exception $e) {
    echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
  </div>
</body>
</html>
