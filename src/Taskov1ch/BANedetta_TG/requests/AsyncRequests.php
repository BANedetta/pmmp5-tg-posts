<?php

namespace Taskov1ch\BANedetta_TG\requests;

use Taskov1ch\BANedetta_TG\TgPosts;

class AsyncRequests
{

	public function __construct(
		private TgPosts $main,
		private string $token,
		private int $groupId
	) {
	}

	public function createPost(array $postData, array $mediaData, string $banned, array $data, array $buttons): void
	{
		$postData = json_encode($postData);
		$mediaData = json_encode($mediaData);
		$data = json_encode($data);

		$keyboard = [
			"inline_keyboard" => [
				[
					["text" => $buttons["confirm"], "callback_data" => "banadetta_confirm"],
					["text" => $buttons["not_confirm"], "callback_data" => "banadetta_reject"]
				]
			]
		];

		$replyMarkup = json_encode($keyboard);

		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncCreatePost($this->token, $this->groupId, $postData, $mediaData, $replyMarkup, $banned, $data)
		);
	}

	public function editPost(int $postId, array $postData, array $mediaData): void
	{
		$postData = json_encode($postData);
		$mediaData = json_encode($mediaData);

		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncEditPost($this->token, $this->groupId, $postId, $postData, $mediaData)
		);
	}

	public function removePost(int $postId): void
	{
		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncRemovePost($this->token, $this->groupId, $postId)
		);
	}

	public function updates(int $offset): void
	{
		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncPolling($this->token, $offset)
		);
	}

	public function answerCallback(int $callbackId, string $text, bool $showAlert): void
	{
		$this->main->getServer()->getAsyncPool()->submitTask(
			new AsyncCallbackAnswer($this->token, $callbackId, $text, $showAlert)
		);
	}
}
