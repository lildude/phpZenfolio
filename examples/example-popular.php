<?php
/* Last updated with phpZenfolio 2.0.0
 *
 * This example file shows you how to get a list of the 96 most popular public
 * galleries created on Zenfolio.
 *
 * You'll need to set:
 *
 * - $appname to your application name, version and URL
 *
 * The application name and version is required, but there is no required format.
 * See the README.md for a suggested format.
 */
$appname = 'YOUR_APP_NAME/VER (URL)';
?>
<html>
  <head>
    <title>phpZenfolio Popular Sets Example</title>
    <style type="text/css">
      body { background-color: #fff; color: #444; font-family: sans-serif; }
      div { width: 750px; margin: 0 auto; text-align: center; }
      img { border: 0;}
    </style>
  </head>
<body>
  <div>
    <a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
    <h2>phpZenfolio Popular Sets Example</h2>
<?php
require_once 'vendor/autoload.php';

try {
    $client = new phpZenfolio\Client($appname);
    // Get list of recent galleries
    $galleries = $client->GetPopularSets('Gallery', 0, 96);
    // Display the 60x60 cropped thumbnails and link to the gallery page for each.
    foreach ($galleries as $gallery) {
        echo '<a href="'.$gallery->PageUrl.'"><img src="'.phpZenfolio\Client::imageUrl($gallery->TitlePhoto, 1).'" title="'.$gallery->Title.'" alt="'.$gallery->Id.'" width="60" height="60" /></a>';
    }
} catch (Exception $e) {
    echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
  </div>
</body>
</html>
