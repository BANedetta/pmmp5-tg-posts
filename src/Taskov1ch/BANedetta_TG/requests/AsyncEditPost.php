<?php

namespace Taskov1ch\BANedetta_TG\requests;

use pocketmine\scheduler\AsyncTask;
use Telegram\Bot\Api;

final class AsyncEditPost extends AsyncTask
{
	public function __construct(
		private string $token,
		private int $groupId,
		private int $postId,
		private string $postData,
		private string $mediaData
	) {
	}

	public function onRun(): void
	{
		$api = new Api($this->token);
		$post = json_decode($this->postData, true) ?? [];
		$media = json_decode($this->mediaData, true) ?? [];

		$textMessage = [
			"chat_id" => $this->groupId,
			"message_id" => $this->postId,
			"text" => $post["text"],
			"parse_mode" => $post["parse_mode"]
		];

		if (!empty($media["type"]) && !empty($media["url"])) {
			$mediaTypeMapping = ["photo", "video", "document", "audio", "animation"];

			if (in_array($media["type"], $mediaTypeMapping)) {
				$mediaData = [
					"type" => $media["type"],
					"media" => $media["url"],
					"caption" => $post["text"],
					"parse_mode" => $post["parse_mode"]
				];

				$response = $api->editMessageMedia([
					"chat_id" => $this->groupId,
					"message_id" => $this->postId,
					"media" => json_encode($mediaData)
				]);
			} else {
				$response = $api->editMessageText($textMessage);
			}
		} else {
			$response = $api->editMessageText($textMessage);
		}

		$this->setResult($response);
	}
}
