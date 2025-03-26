<?php

namespace Taskov1ch\BANedetta_TG\requests;

use pocketmine\scheduler\AsyncTask;
use Telegram\Bot\Api;

class AsyncRemovePost extends AsyncTask
{

	public function __construct(
		private string $token,
		private int $groupId,
		private int $postId
	) {
	}

	public function onRun(): void
	{
		$api = new Api($this->token);

		$response = $api->deleteMessage([
			"chat_id" => $this->groupId,
			"message_id" => $this->postId
		]);

		$this->setResult($response);
	}
}
