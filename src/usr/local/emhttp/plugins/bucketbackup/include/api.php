<?php

$plugin = "bucketbackup";

function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}


function update_cron($settings) {
	global $plugin;
	
	// Parse the backup time given into a string that cron can read
	$hour = 0;
	$minutes = 0;
	$day_of_month = "1";
	$day_of_week = "*";
	switch($settings->backup_interval) {
		case "daily":
			$time = parse_time($settings->time);
			$hour = $time[0];
			$minute = $time[1];
			break;
		case "weekly":
			$time = parse_time($settings->time);
			$hour = $time[0];
			$minute = $time[1];
			$day_of_week = parse_week($settings->day_of_week);
			$day_of_month = "*";
			break;
		case "monthly":
			$time = parse_time($settings->time);
			$hour = $time[0];
			$minute = $time[1];
			$day_of_month = parse_month($settings->day_of_month);
			break;
	}
	
	$cronstring = "{$minute} {$hour} {$day_of_month} * {$day_of_week}";
	shell_exec("mkdir -p /boot/config/plugins/dynamix");
	//Create a file to add 
	$cronfile = fopen("/boot/config/plugins/dynamix/{$plugin}.cron", "w");
	// Add the scheduled job to delete old backups
	fwrite($cronfile, "# Check for and delete old backups at 3:25AM \n");
	fwrite($cronfile, "25 3 * * * /usr/local/emhttp/plugins/{$plugin}/include/delete_old_backups >> /tmp/{$plugin}/delete.log 2>&1 \n");
	if($settings->backup_interval != "never") {
		// Add the scheduled job to create new backups
		fwrite($cronfile, "# Create a new backup \n");
		fwrite($cronfile, "{$cronstring} /usr/local/emhttp/plugins/{$plugin}/include/create_backup >> /tmp/{$plugin}/backup.log 2>&1 \n");
	}
	fclose($cronfile);
	
	shell_exec("update_cron");
	
	update_settings(json_encode($settings));
}

function parse_time($time) {
	$time = preg_split("/[:]+/", $time);
	//This is the hour. Make sure it is a number. It will never be zero
	$hour = intval($time[0]);
	if($hour < 0 || $hour > 23) {
	  $hour = 1;
	}
	$minute = intval($time[1]);
	if($minute < 0 || $minutes > 59) {
		$minute = 0;
	}
	return array($hour, $minute);
}

function parse_week($day_of_week) {
	$day_of_week = intval($day_of_week);
	if($day_of_week < 0 || $day_of_week > 6) {
		return 1;
	}
	return $day_of_week;
}

function parse_month($day_of_month) {
	$day_of_month = intval($settings->day_of_month);
	if($day_of_month < 1 || $day_of_month > 31) {
		return 1;
	}
	return $day_of_month;
}

function update_settings($settingsJson) {
		global $plugin;
		$settings = fopen("/boot/config/plugins/{$plugin}/settings.config", "w");
		fwrite($settings, $settingsJson);
		fclose($settings);
}


?>