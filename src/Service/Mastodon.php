<?php

namespace App\Service;

class Mastodon {
	private $MASTODON_ACCESS_TOKEN = null;
	private $MASTODON_URL = null;

	public function postMessage(string $message, string $image = null, string $locale) {
		$this->setLanguage($locale);

		$accessToken = $this->MASTODON_ACCESS_TOKEN;
		$urlMastodon = $this->MASTODON_URL;

		$headers = [];
		$headers[] = 'Authorization: Bearer '.$accessToken;
		
		$res = new \stdClass;

		$data = ['status' => $message];

		if(!empty($image)) {
			$curl_file = curl_file_create($image, mime_content_type($image), basename($image));

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://${urlMastodon}/api/v1/media");

			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			curl_setopt($ch, CURLOPT_HTTPHEADER, [
			  'Authorization: Bearer '.$accessToken,
			  'Content-Type: multipart/form-data'
			]);

			$body = [
			  'file' => $curl_file
			];

			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

			// Only in dev
			if($_ENV["APP_ENV"] == "dev") {
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}

			$response = json_decode(curl_exec($ch));
			curl_close($ch);
		
			$data['media_ids'] = [$response->id];
		}
		
		$headers[] = 'Content-Type: application/json';

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://${urlMastodon}/api/v1/statuses");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		// Only in dev
		if($_ENV["APP_ENV"] == "dev") {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch))
			$res->error = curl_error($ch);
		else
			$res->success = "success";

		curl_close($ch);

		return $res;
	}

	public function setLanguage($language)
	{
		switch($language)
		{
			case "fr":
				$this->MASTODON_ACCESS_TOKEN = $_ENV["MASTODON_FR_ACCESS_TOKEN"];
				$this->MASTODON_URL = $_ENV["MASTODON_FR_URL"];
				break;
			case "en":
				$this->MASTODON_ACCESS_TOKEN = $_ENV["MASTODON_EN_ACCESS_TOKEN"];
				$this->MASTODON_URL = $_ENV["MASTODON_EN_URL"];
				break;
			case "es":
				$this->MASTODON_ACCESS_TOKEN = $_ENV["MASTODON_ES_ACCESS_TOKEN"];
				$this->MASTODON_URL = $_ENV["MASTODON_ES_URL"];
				break;
		}
	}

	public function getLanguages()
	{
		return ["fr", "en", "es"];
	}
}