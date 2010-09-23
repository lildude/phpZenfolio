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
	<title>phpZenfolio Login Example</title>
	<style type="text/css">
		body { background-color: #fff; color: #444; }
		div { width: 600px; margin: 0 auto; text-align: center; }
		img { border: 0;}
	</style>
</head>
<body>
	<div>
		<a href="http://phpzenfolio.com"><img src="phpZenfolio-logo.png" /></a>
		<h2>phpZenfolio Login Example</h2>
<?php
/* Last updated with phpZenfolio 1.0
 *
 * This example file shows you how to login and display all the images of the your
 * first photoset or collection, regardless of whether it's public or not.
 *
 * You'll need to set the following:
 * - $appname to your application name, version and URL
 * - $username to your Zenfolio username
 * - $password to your user's password
 *
 * The application name and version is required, but there is no required format.
 * See the README.txt for a suggested format.
 *
 * You can see this example in action at http://phpzenfolio.com/examples/
 */
require_once("../phpZenfolio.php");

$appname = '';
$username = '';
$password = '';


try {
	$f = new phpZenfolio("AppName={$appname}");
	// Login. As Plaintext is not passed, the challenge-response authentication method is used.
	$f->login("Username={$username}", "Password={$password}");
	// Load the User's hierachy
	$h = $f->LoadGroupHierarchy($username);
	// Load the photos for the first set/collection
	$photos = $f->LoadPhotoSetPhotos($h['Elements'][0]['Id'], 0, $h['Elements'][0]['PhotoCount']);
	// Display the 60x60 cropped thumbnails and link to the image page for each image in the first gallery/collection.
	foreach ($photos as $photo) {
		echo '<a href="',$photo['PageUrl'],'"><img src="',phpZenfolio::imageUrl($photo, 1),'" title="',$photo['Title'],'" alt="',$photo['Id'],'" /></a>';
	}
}
catch (Exception $e) {
	echo "{$e->getMessage()} (Error Code: {$e->getCode()})";
}
?>
	</div>
</body>
</html>
