<html>
<head>
<title>phpZenfolio Popular Galleries Example</title>
</head>
<body>
<?php
/* Last updated with phpZenfolio 1.0
 *
 * This example file shows you how to get a list of the most popular public
 * galleries created on Zenfolio.
 *
 * You'll need to replace:
 * - <APP NAME/VER (URL)> with your application name, version and URL
 *
 * The application name and version is required, but there is no required format.
 * See the README.txt for a suggested format.
 *
 * You can see this example in action at http://phpzenfolio.com/examples/
 */
require_once("../phpZenfolio.php");

try {
	$f = new phpZenfolio("AppName=<APP NAME/VER (URL)>");
	// Get list of recent galleries
	$galleries = $f->GetPopularSets('Gallery', 0, 100);
	// Display the 60x60 cropped thumbnails and link to the gallery page for each.
	foreach ($galleries as $gallery) {
		echo '<a href="',$gallery['PageUrl'],'"><img src="',phpZenfolio::imageUrl($gallery['TitlePhoto'], 1),'" title="',$gallery['Title'],'" alt="',$gallery['Id'],'" /></a>';
	}
}
catch (Exception $e) {
	echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
</body>
</html>
