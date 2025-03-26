<?php

namespace Taskov1ch\BANedetta_TG\requests;

use pocketmine\scheduler\AsyncTask;
use Telegram\Bot\Api;

class AsyncCallbackAnswer extends AsyncTask
{

	public function __construct(
		private string $token,
		private int $callbackId,
		private string $text,
		private bool $showAlert
	) {
	}

	public function onRun(): void
	{
		$api = new Api($this->token);

		$api->answerCallbackQuery([
			"callback_query_id" => $this->callbackId,
			"text" => $this->text,
			"show_alert" => $this->showAlert
		]);
	}
}
