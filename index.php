<?php

include("SDK/CheckDetect.php");

$options = array(
			'username' => "xxxxxxxxxxxx",
			'password' => "xxxxxxxxx",
			'pathfile' => "111.txt",	
			'savepath' => "/file/pdf/",
			'debug' => true,
			'logfile' => "./Log/log.txt"
			);

$detect = new CheckDetect($options);
$res = $detect->run();
echo "<pre>";
print_r($res);
echo "</pre>";