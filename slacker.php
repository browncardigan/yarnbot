<?php

// this is the basic 'cron job' to send the weekly updates to slack

// init
@include("_conf.php");

// slack class
@include(ROOT . "classes/Slack.php");
$slack = new Slack;
$slack->init();

// execute
$slack->sendWeeklyUpdate();

?>