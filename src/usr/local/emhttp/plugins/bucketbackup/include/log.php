<?php


if (!is_null($_POST['logtype'])) {
	$plugin = "bucketbackup";
	$logtype = $_POST['logtype'];
	$logcontents = file_get_contents("/tmp/{$plugin}/{$logtype}.log");
	echo nl2br($logcontents);
}