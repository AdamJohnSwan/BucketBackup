<?php


function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}

if (count($_POST)) {
	if ($_POST['#apply'] == "Apply") {
		$settingsObj = new \stdClass();
		$settingsObj->app_id = $_POST["api_id"];
		$settingsObj->app_key = $_POST["api_key"];
		$settingsObj->backup_interval = $_POST["backup_interval"];
		$settingsObj->backup_location = $_POST["backup_location"];
		$settingsObj->bucket_type = $_POST["bucket_type"];
		$settingsObj->encryption_password = $_POST["encryption_password"];
		
		$settingsJson = json_encode($settingsObj);
		
		console_log($settingsJson);
		
		$settings = fopen("settings.config", "w") or die("Unable to open file!");
		fwrite($settings, $settingsJson);
		fclose($settings);
		
		//Update the cron with the backup schedule
		update_cron($_POST["backup_interval"]);
	}
}

function update_cron($interval) {
	if ($interval == "daily") {
		$cronstring = "0 4 * * *";
	}
	if ($interval == "weekly") {
		$cronstring = "0 4 * * 1";
	}
	if ($interval == "monthly") {
		$cronstring = "0 4 1 * *";
	}
	$cronstring = "/usr/local/emhttp/plugins/bucketbackup/include/create_backup " . $cronstring;
	shell_exec("mkdir -p /boot/config/plugins/dynamix");
	shell_exec("touch bucketbackup.cron");
	shell_exec("echo Backup job\n" . $cronstring . " > /tmp/output.txt" . "\n");
	
	
}

?>