<?php

$plugin = "bucketbackup";

function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . ');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}


function update_cron($interval) {
		global $plugin;
        $cronstring = "";
        if ($interval == "daily") {
                $cronstring = "0 4 * * *";
        }
        if ($interval == "weekly") {
                $cronstring = "0 4 * * 1";
        }
        if ($interval == "monthly") {
                $cronstring = "0 4 1 * *";
        }
        if ($cronstring != "") {
                shell_exec("mkdir -p /boot/config/plugins/dynamix");
                shell_exec("touch /boot/config/plugins/dynamix/{$plugin}.cron");
                shell_exec("echo 'Backup job\n{$cronstring} /usr/local/emhttp/plugins/{$plugin}/include/create_backup\n' > /boot/config/plugins/dynamix/{$plugin}.cron");
                shell_exec("update_cron");
        }
}

function update_settings($settingsJson) {
		global $plugin;
		$settings = fopen("/usr/local/emhttp/plugins/{$plugin}/settings.config", "w") or die("Unable to open file!");
		fwrite($settings, $settingsJson);
		fclose($settings);
}

?>