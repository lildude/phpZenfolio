<?php
/* Last updated with phpZenfolio 2.0.0
 *
 * This example file shows you how to get a list of the specified user's public
 * galleries and collections created on Zenfolio and display the first 96 images
 * in the first gallery or collection in the list.
 *
 * You'll need to set:
 *
 * - $appname to your application name, version and URL
 * - $username to your Zenfolio username
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
?>
<html>
  <head>
    <title>phpZenfolio First User Gallery/Collection Example</title>
    <style type="text/css">
      body { background-color: #fff; color: #444; font-family: sans-serif; }
      div { width: 750px; margin: 0 auto; text-align: center; }
      img { border: 0;}
    </style>
  </head>
<body>
  <div>
    <a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
    <h2>phpZenfolio First User Gallery/Collection Example</h2>
<?php
require_once 'vendor/autoload.php';

try {
    $client = new phpZenfolio\Client($appname);
    // Get list of recent galleries and collections
    $h = $client->LoadGroupHierarchy($username);
    // Now traverse the tree and locate the first public gallery and display it's first 96 photos
    array_walk($h->Elements, 'displayImgs', $client);
} catch (Exception $e) {
    echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}

function displayImgs(\stdClass $element, $_, phpZenfolio\Client $client)
{
    if ($element->{'$type'} == 'Group') {
        array_walk($element->Elements, 'displayImgs', $client);
    } else {
        if ($element->PhotoCount > 0) {
            $pictures = $client->LoadPhotoSetPhotos($element->Id, 0, 96);
            // Display the 60x60 cropped thumbnails and link to the photo page for each.
            foreach ($pictures as $picture) {
                echo '<a href="'.$picture->PageUrl.'"><img src="'.phpZenfolio\Client::imageUrl($picture, 1).'" title="'.$picture->Title.'" alt="'.$picture->Id.'" /></a>';
            }
        }
    }
}
?>
  </div>
</body>
</html>
