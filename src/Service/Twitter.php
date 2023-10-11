<?php
namespace App\Service;

use Abraham\TwitterOAuth\TwitterOAuth;

class Twitter
{
	private $CONSUMER_KEY = null;
	private $CONSUMER_SECRET = null;
	private $OAUTH_TOKEN = null;
	private $OAUTH_TOKEN_SECRET = null;
	private $TWITTER_USERNAME = null;

	public function sendTweet(string $message, $image, string $locale)
	{
		$this->setLanguage($locale);
		$connection = new TwitterOAuth($this->CONSUMER_KEY, $this->CONSUMER_SECRET, $this->OAUTH_TOKEN, $this->OAUTH_TOKEN_SECRET);

		$parameters = [];

		if(!empty($image)) {
			$imageArray = [];
			$connection->setApiVersion('1.1');
			$media = $connection->upload('media/upload', ['media' => $image]);
			array_push($imageArray, $media->media_id_string);

			$parameters['media']['media_ids'] = $imageArray;
		}
		
		$connection->setApiVersion('2');

		$parameters['text'] = $message;

		return $connection->post('tweets', $parameters, true);
	}
	
	public function setLanguage($language)
	{
		switch($language)
		{
			case "fr":
				$this->CONSUMER_KEY = $_ENV["TWITTER_CONSUMER_KEY_FR"];
				$this->CONSUMER_SECRET = $_ENV["TWITTER_CONSUMER_SECRET_FR"];
				$this->OAUTH_TOKEN = $_ENV["TWITTER_ACCESS_TOKEN_FR"];
				$this->OAUTH_TOKEN_SECRET = $_ENV["TWITTER_ACCESS_TOKEN_SECRET_FR"];
				$this->TWITTER_USERNAME = "poeticus12";
				break;
		}
	}

	public function getLanguages()
	{
		return ["en", "fr"];
	}
}