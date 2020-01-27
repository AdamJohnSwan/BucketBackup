<?php

if ($_POST['restore'] == "Restore") {
	$password = $_POST["encryption_password_restore"];
	$bucket_info = $_POST["bucket_to_restore"];
	$location = $_POST["restore_location"];
	$api_id = $_POST["api_id_restore"];
	$api_key = $_POST["api_key_restore"];
	
	$_SESSION['message'] = "Restore Starting";
	// get the auth token
	$credentials = base64_encode($api_id . ":" . $api_key);
	$authurl = "https://api.backblazeb2.com/b2api/v2/b2_authorize_account";

	$ch = curl_init($authurl);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Basic ' . $credentials,
			'Accept: application/json'
	));
	curl_setopt($ch, CURLOPT_HTTPGET, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Receive server response
	$auth_response = curl_exec($ch);
	curl_close ($ch);
	$json = json_decode($auth_response, true);
	$token = $json["authorizationToken"];
	$accountid = $json["accountId"];
	$apiurl = $json["apiUrl"];
	$apiurl = $apiurl . '/b2api/v2/';
	
	// Get bucketbackup's bucket
	$ch = curl_init($apiurl . 'b2_list_buckets');
	$data = array("accountId" => $accountid);
	$post_fields = json_encode($data);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: ' . $token,
			'Accept: application/json'
	));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$list_buckets_response = curl_exec($ch);
	curl_close ($ch);

	$json = json_decode($list_buckets_response, true);
	$buckets = $json['buckets'];
	$bucketid = 0;
	foreach($buckets as $key => $bucket) {
			if($bucket["bucketName"] == "unraid-bucket-backup") {
					$bucketid = $bucket["bucketId"];
			}
	}
	if($bucketid == 0) {
			return;
	}
	$_SESSION['message'] = "Found bucketbackup's bucket";
	//Create temporary folder to house the downloads
	$download_dir = $location . "bucketbackup_download_dir";
	if(file_exists($download_dir)) {
		exec("rm -r $download_dir");
	}
	mkdir($download_dir);
	
	// Gather the files in the bucket that have the file info that was selected by the user
	$start_file = null;
	do {
		$ch = curl_init($apiurl .  "b2_list_file_names");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: ' . $token,
				'Accept: application/json'
		));
		$data = array("bucketId" => $bucketid, "startFileName" => $start_file);
		$post_fields = json_encode($data);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$list_files_response = curl_exec($ch);
		curl_close ($ch);
		$json = json_decode($list_files_response, true);
		$files = $json['files'];
		foreach($files as $key => $file) {
			if($file["fileInfo"]["backup_job"] == $bucket_info) {
				//Download the file
				$downloadurl = $apiurl . "b2_download_file_by_id?fileId=" . $file["fileId"];
				$destination = $download_dir . "/" . $file["fileName"];
				$fp = fopen($destination, "w+");
				
				$ch = curl_init($downloadurl);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
						'Authorization: ' . $token
				));
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_HTTPGET, true);
				curl_exec($ch);
				$st_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				fclose($fp);
				
				if($st_code == 200) {
					$_SESSION['message'] = $file["fileName"] . ' downloaded successfully';
					//Now to decrypt the file
					$gzfile = str_replace(".dat", "", $file["fileName"]);
					$_SESSION['message'] = "Decrypting...";
					$_SESSION['message'] = shell_exec("openssl enc -aes-256-cbc -pbkdf2 -d -pass pass:{$password} < {$download_dir}/{$file["fileName"]} > {$download_dir}/{$gzfile}");
					//Remove the encrypted file
					$_SESSION['message'] = shell_exec("rm {$download_dir}/{$file["fileName"]}");
					//Unzip and untar the file
					$_SESSION['message'] = shell_exec("tar -xzvf {$download_dir}/{$gzfile} -C {$location}");
					//Remove the tar file
					$_SESSION['message'] = shell_exec("rm {$download_dir}/{$gzfile}");
				} else {
					$_SESSION['message'] = 'Error downloading ' . $file["fileName"];
				}
			}
		}
		$start_file = $json["nextFileName"];
	} while ($start_file != null);
	$_SESSION['message'] = 'Download finished';
	exec("rm -r $download_dir");
}

?>