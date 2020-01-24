<?php

function list_backups() {
        $plugin = "bucketbackup";
        if (!file_exists("/usr/local/emhttp/plugins/{$plugin}/settings.config")) {
                return;
        }

        $settings = file_get_contents("/usr/local/emhttp/plugins/{$plugin}/settings.config");
        $json = json_decode($settings,true);

        $api_id = $json["api_id"];
        $api_key = $json["api_key"];

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

        // List the buckets
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

        // List the files in the bucket
        $backups = [];
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
                        $backups[] = $file["fileInfo"]["backup_job"];
                }
                $start_file = $json["nextFileName"];
        } while ($start_file != null);
        return array_unique($backups);
}

?>