<?php

echo '<pre>';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// init
@include("_conf.php");

// slack class
@include(ROOT . "classes/Slack.php");
$slack = new Slack;
$slack->init();

// tester
//$result = $slack->tester();
//print_r($result);

// real world
$slack->sendWeeklyUpdate();
	
?>