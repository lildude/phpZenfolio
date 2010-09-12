<html>
<head>
<title>phpZenfolio First User Gallery Example</title>
</head>
<body>
<?php
/* Last updated with phpZenfolio 1.0
 *
 * This example file shows you how to get a list of the specified user's public
 * galleries and collections created on Zenfolio and display the first 100 images
 * in the first gallery or collection
 *
 * You'll need to replace:
 * - <APP NAME/VER (URL)> with your application name, version and URL
 * - <USERNAME> with the Zenfolio username you wish to view
 *
 * The application name and version is required, but there is no required format.
 * See the README.txt for a suggested format.
 *
 * You can see this example in action at http://phpzenfolio.com/examples/
 */
require_once("../phpZenfolio.php");

try {
	$f = new phpZenfolio("AppName=<APP NAME/VER (URL)>");
	// Get list of recent galleries and collections
	$h = $f->LoadGroupHierarchy("<USERNAME>");
	// Get all the pictures in the first element
	$pictures = $f->LoadPhotoSetPhotos($h['Elements'][0]['Id'], 0, 100 );
	// Display the 60x60 cropped thumbnails and link to the photo page for each.
	foreach ($pictures as $pic) {
		echo '<a href="',$pic['PageUrl'],'"><img src="',phpZenfolio::imageUrl($pic, 1),'" title="',$pic['Title'],'" alt="',$pic['Id'],'" /></a>';
	}
}
catch (Exception $e) {
	echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
</body>
</html>
