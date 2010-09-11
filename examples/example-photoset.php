<html>
<head>
	<title>phpZenfolio Recent Galleries Example</title>
	<style type="text/css">
		div { width: 600px; margin: 0 auto; text-align: center; }
		img { border: 0;}
	</style>
</head>
<body>
	<div>
		<h1>phpZenfolio Recent Galleries Example</h1>
<?php
/* Last updated with phpZenfolio 1.0
 *
 * This example file shows you how to get a list of the most recent public
 * galleries created on Zenfolio.
 *
 * You'll need to replace:
 * - "phpZenfolio Recent Galleries Example/0.1" with your application name, version and URL
 *
 * The application name and version is required, but there is no required format.
 * See the README.txt for a suggested format.
 *
 * You can see this example in action at http://phpzenfolio.com/examples/
 */
require_once("../phpZenfolio.php");

try {
	$f = new phpZenfolio("AppName=phpZenfolio Recent Galleries Example/0.1");
	// Get list of recent galleries
	$galleries = $f->GetRecentSets('Gallery', 0, 100);
	// Display the 60x60 cropped thumbnails and link to the gallery page for each.
	foreach ($galleries as $gallery) {
		echo '<a href="',$gallery['PageUrl'],'"><img src="',phpZenfolio::imageUrl($gallery['TitlePhoto'], 1),'" title="',$gallery['Title'],'" alt="',$gallery['Id'],'" /></a>';
	}
}
catch (Exception $e) {
	echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
	</div>
</body>
</html>
