<?php
/*
The MIT License

Copyright (c) 2012 Dominik Sommer (dominik.sommer@bluebee.mobi)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

require_once('parse.php');

// Mind the key for the REST API is different from that of the Android API!
$parseConfig = array(
		'appid' => 'INSERT_PARSE_APPLICATION_ID_HERE',
		'restkey' => 'INSERT_PARSE_REST_API_KEY_HERE'
);

// Add the bluebee SDK to the include path
define('ROOT', dirname(dirname(dirname(__FILE__))));
if (function_exists('ini_set')) {
	ini_set('include_path', ROOT . PATH_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'bluebee');
}

// Auto-load the bluebee template classes with the bluebee namespace
function __autoload($class_name) {
	if (strpos($class_name, 'bluebee\\sdk\\') === 0) {
		include substr($class_name, strrpos($class_name, '\\') + 1) . '.php';
	}
}

// Check if chosen template exists & instantiate it
$template = 'bluebee\\sdk\\'.$_GET['template'];
if (!class_exists($template)) {
	header('HTTP/1.0 400 Bad request');
	exit;
}
$result = new $template();

// Get Album Name from parameters
$albumName = $_POST['albumName'];
if (empty($albumName)) {
	// Abort if no Album Name given
	header('HTTP/1.0 404 Not Found');
	exit;
}

// Get Album from Parse Backend
$parse = new parseRestClient($parseConfig);
$album = json_decode($parse->query(array("className" => "Album", "query" => array("name" => $albumName))));
if (count($album->results) == 0) {
	header('HTTP/1.0 404 Not Found '.$albumName);
	exit;
}
$album = $album->results[0];

// Get 10 most recent images from parse backend
$where = array("album" => array("__type" => "Pointer", "className" => "Album", "objectId" => $album->objectId));
$oder = "-createdAt";
$limit = $result->getSupportedNrOfImages();
$images = json_decode($parse->query(array("className" => "Image", "query" => $where, "order" => $oder, "limit" => $limit)));

// Provide most recent images to selected template
foreach($images->results as $image) {
	if (!$result->addImage($image->imageFile->url)) break;
}

// Send response
print $result->serializeForBeeApp();
