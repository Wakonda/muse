<?php

namespace App\Service;

class Facebook {
	// https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived
	private $apiGraphVersion = "v14.0";
	
	public function getLongLiveAccessToken() {
		$accessToken = ""; // Token généré à partir de la page : https://developers.facebook.com/tools/explorer/
		$pageId = $_ENV["FACEBOOK_PAGE_ID"];
		$appId = $_ENV["FACEBOOK_APP_ID"];
		$secretKey = $_ENV["FACEBOOK_SECRET_KEY"];
		$apiGraphVersion = $_ENV["FACEBOOK_GRAPH_VERSION"];
		$userId = $_ENV["FACEBOOK_USER_ID"]; // GET me?fields=id,name
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/${apiGraphVersion}/oauth/access_token?grant_type=fb_exchange_token&client_id=${appId}&client_secret=${secretKey}&fb_exchange_token=${accessToken}");
			
		// Only on dev
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);

		$result = json_decode($result);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/${apiGraphVersion}/${userId}/accounts?access_token=".$result->access_token);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// Only on dev
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);

		return json_decode($result);
	}

	public function postMessage(string $url, string $message) {
		$pageId = $_ENV["FACEBOOK_PAGE_ID"];

		$url = urlencode($url);
		
		$ch = curl_init();

		$message = urlencode($message);
		
		$llt = $_ENV["FACEBOOK_ACCESS_TOKEN"];

		curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/${pageId}/feed?message=${message}&link=${url}&access_token=".$llt);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// Only on dev
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);

		return $result;
	}
}