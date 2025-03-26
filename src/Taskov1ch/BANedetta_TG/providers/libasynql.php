<?php

namespace Taskov1ch\BANedetta_TG\providers;

use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;

class libasynql
{
	/**
	 * @var \poggit\libasynql\base\DataConnectorImpl $db
	 */
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
		$this->db->executeGeneric("tg_table.init");
		$this->db->waitAll();
	}

	public function add(int $postId, string $banned, string $data): void
	{
		$this->db->executeInsert("tg_data.add", [
			"post_id" => $postId,
			"banned" => strtolower($banned),
			"data" => $data
		]);
	}

	public function removePostByBanned(string $banned): void
	{
		$this->getDataByBanned($banned)->onCompletion(
			function (array $data) {
				if (!empty($data)) {
					$this->db->executeGeneric(
						"tg_data.remove_post_by_banned",
						["banned" => $data["banned"]]
					);
				}
			},
			fn () => null
		);
	}

	public function removePostById(int $postId): void
	{
		$this->getDataById($postId)->onCompletion(
			function (array $data) {
				if (!empty($data)) {
					$this->db->executeGeneric(
						"tg_data.remove_post_by_banned",
						["post_id" => $data["post_id"]]
					);
				}
			},
			fn () => null
		);
	}

	public function getDataByBanned(string $banned): Promise
	{
		$banned = strtolower($banned);
		$promise = new PromiseResolver();

		$this->db->executeSelect(
			"tg_data.get_data_by_banned",
			compact("banned"),
			fn (array $data) => $promise->resolve($data[0] ?? []),
			fn () => $promise->reject()
		);

		return $promise->getPromise();
	}

	public function getDataById(int $postId): Promise
	{
		$promise = new PromiseResolver();

		$this->db->executeSelect(
			"tg_data.get_data_by_id",
			["post_id" => $postId],
			fn (array $data) => $promise->resolve($data[0] ?? []),
			fn () => $promise->reject()
		);

		return $promise->getPromise();
	}
}
