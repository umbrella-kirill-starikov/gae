<?php

require_once 'config.php';
require_once 'ParselyModel.php';

DatastoreService::setInstance(new DatastoreService($google_api_config));

// check date in request
if(empty($_GET['date'])) {
	return 'Empty date';
}

$date = $_GET['date'];
$kname = sha1($date);
// get count by date key
$date_model_fetched = ParselyModel::fetch_by_name($kname)[0];
// update if exist or create new
if($date_model_fetched){
	$current_count = $date_model_fetched->getSubscriberUrl();
	$current_count = (int)$current_count;
	$current_count = $current_count+1;
	$current_count = (string)$current_count;
	$parsely_model = new ParselyModel($date, $current_count);
	$parsely_model->put();
	return 'update';
} else {
	$parsely_model = new ParselyModel($date, '1');
	$parsely_model->put();
	return 'added new date';
}

return false;