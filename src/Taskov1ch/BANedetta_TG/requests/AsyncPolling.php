<?php

namespace Taskov1ch\BANedetta_TG\requests;

use pocketmine\scheduler\AsyncTask;
use Taskov1ch\BANedetta_TG\TgPosts;
use Telegram\Bot\Api;

class AsyncPolling extends AsyncTask
{

	public function __construct(
		private string $token,
		private int $offset,
	) {
	}

	public function onRun(): void
	{
		$api = new Api($this->token);
		$allowedUpdates = ["callback_query", "message_delete"];

		$updates = $api->getUpdates([
			"offset" => $this->offset,
			"timeout" => 20,
			"allowed_updates" => json_encode($allowedUpdates)
		]);

		$this->setResult($updates);
	}

	public function onCompletion(): void
	{
		TgPosts::getInstance()->handleUpdates($this->getResult());
	}
}
