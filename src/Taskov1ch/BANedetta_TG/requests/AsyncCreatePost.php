<?php

namespace Taskov1ch\BANedetta_TG\requests;

use pocketmine\scheduler\AsyncTask;
use Taskov1ch\BANedetta_TG\TgPosts;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

final class AsyncCreatePost extends AsyncTask
{
	public function __construct(
		private string $token,
		private int $groupId,
		private string $postData,
		private string $mediaData,
		private string $replyMarkup,
		private string $banned,
		private string $data
	) {
	}

	public function onRun(): void
	{
		$api = new Api($this->token);
		$media = json_decode($this->mediaData, true);
		$post = json_decode($this->postData, true);

		$mediaMessage = [
			"chat_id" => $this->groupId,
			"caption" => $post["text"],
			"parse_mode" => $post["parse_mode"],
			"reply_markup" => $this->replyMarkup
		];

		$textMessage = [
			"chat_id" => $this->groupId,
			"text" => $post["text"],
			"parse_mode" => $post["parse_mode"],
			"reply_markup" => $this->replyMarkup
		];

		if (!empty($media["type"]) && !empty($media["url"])) {
			$file = InputFile::create($media["url"]);

			$sendMethods = [
				"photo" => "sendPhoto",
				"video" => "sendVideo",
				"document" => "sendDocument",
				"audio" => "sendAudio",
				"animation" => "sendAnimation"
			];

			$method = $sendMethods[$media["type"]] ?? null;

			if ($method) {
				$mediaMessage[$media["type"]] = $file;
				$response = $api->$method($mediaMessage);
			} else {
				$response = $api->sendMessage($textMessage);
			}
		} else {
			$response = $api->sendMessage($textMessage);
		}

		$this->setResult($response);
	}

	public function onCompletion(): void
	{
		$data = $this->getResult();
		TgPosts::getInstance()->getDatabase()->add($data["message_id"] ?? null, $this->banned, $this->data);
	}
}
