<?php
/* Last updated with phpZenfolio 2.0.0
 *
 * This example file shows you how to login, create a new gallery photoset in
 * the authenticated user's root photoset group, and upload an image to this
 * gallery.
 *
 * You'll need to set the following:
 *
 * - $appname to your application name, version and URL
 * - $username to your Zenfolio username
 * - $password to your user's password
 * - $file to the path to a local file you wish to upload to the new gallery
 *
 * The application name and version is required, but there is no required format.
 * See the README.md for a suggested format.
 */

$appname = 'YOUR_APP_NAME/VER (URL)';
$username = 'A_USERNAME';
$password = 'A_PASSWORD';
$file = '/PATH/TO/A/FILE/TO/UPLOAD.EXT';
?>
<html>
<head>
  <title>phpZenfolio Create Gallery and Upload Example</title>
  <style type="text/css">
    body { background-color: #fff; color: #444; font-family: sans-serif; }
    div { width: 750px; margin: 0 auto; text-align: center; }
    img { border: 0;}
  </style>
</head>
<body>
  <div>
    <a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
    <h2>phpZenfolio Create Gallery and Upload Example</h2>
<?php
require_once 'vendor/autoload.php';

try {
    $client = new phpZenfolio\Client($appname);
    // Login. As Plaintext is not passed, the challenge-response authentication method is used.
    $client->login($username, $password);
    // Load the User's hierachy.
    $h = $client->LoadGroupHierarchy($username);
    // Create the photoSetUpdater object. This can be either an associative array
    // or standard class object. This example uses an array.
    $photoSetUpdater = [
        'Title' => 'phpZenfolio-created Gallery',
        'Caption' => 'This gallery was created by the phpZenfolio example-create-photoset.php example',
        'Keywords' => ['phpZenfolio', 'example'],
        'CustomReference' => 'phpzenfolio/example-gallery',
    ];
    // Create the gallery in the root photoset group
    $photoSet = $client->CreatePhotoSet($h->Id, 'Gallery', $photoSetUpdater);
    // Upload the file
    $photo = $client->upload($photoSet, $file);
    // Create the photoUpdater object. As with all updater objects, this can be
    // an associative array or a standard class object. This example uses an array.
    $photoUpdater = [
        'Title' => 'phpZenfolio-uploaded photo',
        'Caption' => 'This photo was uploaded by the phpZenfolio example-create-photoset.php example',
        'Keywords' => ['phpZenfolio', 'example'],
    ];
    // Set the title, caption and keywords on the image just uploaded.
    $photo = $client->UpdatePhoto($photo, $photoUpdater);

    // Send a message confirming the upload and display the medium-sized image.
    // NOTE: this can take a while to display whilst Zenfolio generates the various sizes.
    echo '<p>"'.$photoSetUpdater['Title'].'" successfully created.</p>';
    echo '<p><code>'.$file.'</code> successfully uploaded to it and displayed below:</p>';
    echo '<a href="'.$photo->PageUrl.'"><img src="'.phpZenfolio\Client::imageUrl($photo, 3).'" title="'.$photo->Title.'" alt="'.$photo->Id.'" /></a>';
} catch (Exception $e) {
    echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
  </div>
</body>
</html>
