<?php
/**
 * Copyright (c) 2010 Colin Seymour
 *
 * This file is part of phpZenfolio.
 *
 * phpZenfolio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * phpZenfolio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with phpZenfolio.  If not, see <http://www.gnu.org/licenses/>.
 */
?>
<html>
<head>
	<title>phpZenfolio First User Gallery/Collection Example</title>
	<style type="text/css">
		body { background-color: #fff; color: #444; }
		div { width: 600px; margin: 0 auto; text-align: center; }
		img { border: 0;}
	</style>
</head>
<body>
	<div>
		<a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
		<h2>phpZenfolio First User Gallery/Collection Example</h2>
<?php
/* Last updated with phpZenfolio 1.0
 *
 * This example file shows you how to get a list of the specified user's public
 * galleries and collections created on Zenfolio and display the first 100 images
 * in the first gallery or collection in the list.
 *
 * You'll need to set:
 * - $appname to your application name, version and URL
 * - $username to your Zenfolio username
 *
 * The application name and version is required, but there is no required format.
 * See the README.txt for a suggested format.
 *
 * You can see this example in action at http://phpzenfolio.com/examples/
 */
require_once("../phpZenfolio.php");

$appname = '';
$username = '';

try {
	$f = new phpZenfolio("AppName={$appname}");
	// Get list of recent galleries and collections
	$h = $f->LoadGroupHierarchy($username);
	// Now traverse the tree and locate the first public gallery and display it's first 100 photos
	array_walk($h['Elements'], 'displayImgs', $f);
}
catch (Exception $e) {
	echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}

function displayImgs($element, $key, $f) {
	if ( $element['$type'] == 'Group' ) {
		array_walk($element['Elements'], 'displayImgs', $f);
	} else {
		if ( $element['PhotoCount'] > 0 ) {
			$pictures = $f->LoadPhotoSetPhotos($element['Id'], 0, 100 );
			// Display the 60x60 cropped thumbnails and link to the photo page for each.
			foreach ($pictures as $pic) {
				echo '<a href="',$pic['PageUrl'],'"><img src="',phpZenfolio::imageUrl($pic, 1),'" title="',$pic['Title'],'" alt="',$pic['Id'],'" /></a>';
			}
			break;
		} 
	}
}
?>
</body>
</html>
