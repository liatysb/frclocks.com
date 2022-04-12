<?php
//temporary things for testing purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


print "<pre>";
$data=get_data("2022/districts");
print_r($data);
//end temporary things



function get_data($call) {
	//load api key
	$key = file_get_contents('configs/key.txt');
	
	// Create a stream
	$opts = array(
		'http'=>array(
			'method'=>"GET",
			'header'=>"Authorization: Basic ".$key
		)
	);
	$context = stream_context_create($opts);

	// Open the file using the HTTP headers set above
	$data = json_decode(file_get_contents('https://frc-api.firstinspires.org/v3.0/'.$call, true, $context),true);
	return $data;
}

//temporary for testing purposes
print "</pre>";
?>