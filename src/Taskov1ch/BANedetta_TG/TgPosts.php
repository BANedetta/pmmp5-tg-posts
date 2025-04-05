<?php

namespace Taskov1ch\BANedetta_TG;

use Exception;
use IvanCraft623\languages\Language;
use IvanCraft623\languages\Translator;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
use Taskov1ch\BANedetta\BANedetta;
use Taskov1ch\BANedetta\posts\PostPlugin;
use Taskov1ch\BANedetta_TG\commands\AddAdminCommand;
use Taskov1ch\BANedetta_TG\commands\AdminsListCommand;
use Taskov1ch\BANedetta_TG\commands\RemoveAdminCommand;
use Taskov1ch\BANedetta_TG\providers\libasynql;
use Taskov1ch\BANedetta_TG\requests\AsyncRequests;
use Telegram\Bot\Api;

class TgPosts extends PostPlugin
{
	use SingletonTrait;

	private Translator $translator;
	private libasynql $db;
	private AsyncRequests $requests;
	private array $posts;
	private array $admins;
	private int $lastUpdateId = 0;

	public function onEnable(): void
	{
		self::setInstance($this);
		BANedetta::getInstance()->getPostsManager()->registerPostPlugin($this);
	}

	public function onDisable(): void
	{
		$adminsConfig = new Config($this->getDataFolder() . "admins.yml");
		$adminsConfig->setAll($this->admins);
		$adminsConfig->save();
	}

	public function onRegistered(): void
	{
		$this->saveResources(["", "languages"]);
		$this->loadTranslations();
		$this->registerCommands();

		$this->db = new libasynql($this->getBansManager()->getDataBase());

		$config = $this->getConfig();
		$this->requests = new AsyncRequests($this, $config->get("bot_token"), $config->get("group_id"));

		$api = new Api($config->get("bot_token"));
		$updates = $api->getUpdates();
		$this->lastUpdateId = !empty($updates) ? end($updates)->getUpdateId() : 0;
		$this->requests->updates($this->lastUpdateId);

		foreach (["admins", "posts"] as $configName) {
			$this->{$configName} = (new Config($this->getDataFolder() . "$configName.yml"))->getAll();
		}
	}

	private function saveResources(array $dirs): void
	{
		$resourceFolder = $this->getResourceFolder();
		foreach ($dirs as $dir) {
			foreach (glob(Path::join($resourceFolder, $dir, "*.yml")) as $file) {
				$this->saveResource(str_replace($resourceFolder, "", $file));
			}
		}
	}

	private function loadTranslations(): void
	{
		$this->translator = new Translator($this);
		$defaultLang = $this->getConfig()->get("default_language");

		foreach (glob($this->getDataFolder() . "languages/*.yml") as $file) {
			$langName = basename($file, ".yml");
			$lang = new Language($langName, (new Config($file))->getAll());

			$this->translator->registerLanguage($lang);
			if ($langName === $defaultLang) {
				$this->translator->setDefaultLanguage($lang);
			}
		}
	}

	private function registerCommands(): void
	{
		$this->getServer()->getCommandMap()->registerAll("BANedetta_TG", [
			new AddAdminCommand($this, "taa", "Add admin command", "banedetta.tg.add_admin"),
			new RemoveAdminCommand($this, "tar", "Remove admin command", "banedetta.tg.remove_admin"),
			new AdminsListCommand($this, "tal", "Admins list command", "banedetta.tg.admins")
		]);
	}

	public function getDatabase(): libasynql
	{
		return $this->db;
	}

	public function getDatabaseQueriesMap(): array
	{
		return [
			"mysql" => "database/mysql.sql",
			"sqlite" => "database/sqlite.sql"
		];
	}

	public function getTranslator(): Translator
	{
		return $this->translator;
	}

	public function getAdmins(): array
	{
		return $this->admins;
	}

	public function isAdmin(int $id): bool
	{
		return in_array($id, $this->admins);
	}

	public function addAdmin(int $id): void
	{
		if (!$this->isAdmin($id)) {
			$this->admins[] = $id;
		}
	}

	public function removeAdmin(int $id): void
	{
		if ($this->isAdmin($id)) {
			unset($this->admins[array_search($id, $this->admins)]);
		}
	}

	public function createPost(string $banned, string $by, string $reason, int $timeLimit): void
	{
		$post = $this->posts["waiting"];

		$postText = str_replace(
			["{banned}", "{by}", "{reason}", "{time}"],
			[
				strtolower($banned),
				strtolower($by),
				$reason,
				date("H:i:s d:m:Y", time() + $timeLimit)
			],
			$post["post"]["text"]
		);

		$this->requests->createPost(
			["text" => $postText, "parse_mode" => $post["post"]["parse_mode"]],
			$post["media"],
			$banned,
			compact("by", "reason"),
			$post["buttons"]
		);
	}

	public function removePost(string $banned): void
	{
		$this->db->getDataByBanned($banned)->onCompletion(
			function (array $data) {
				if (!empty($data)) {
					try {
						$this->requests->removePost($data["post_id"]);
					} catch (Exception $e) {
						// TODO: Исправить эту конструкцию как-то, чтобы удаление не вызывалось дважды.
					}

					$this->db->removePostByBanned($data["banned"]);
				}
			},
			fn () => null
		);
	}

	private function updatePost(string $banned, string $type): void
	{
		$this->db->getDataByBanned($banned)->onCompletion(
			function (array $data) use ($type) {
				if (!empty($data)) {
					$this->requests->editPost(
						$data["post_id"],
						[
							"text" => str_replace(
								["{banned}", "{by}", "{reason}"],
								[$data["banned"], json_decode($data["data"], true)["by"], json_decode($data["data"], true)["reason"]],
								$this->posts[$type]["post"]["text"]
							),
							"parse_mode" => $this->posts[$type]["post"]["parse_mode"]
						],
						$this->posts[$type]["media"]
					);

					$this->db->removePostByBanned($data["banned"]);
				}
			},
			fn () => null
		);
	}

	public function confirmed(string $banned): void
	{
		$this->updatePost($banned, "confirmed");
	}

	public function notConfirmed(string $banned): void
	{
		$this->updatePost($banned, "not_confirmed");
	}

	public function handleUpdates(array $updates): void
	{
		foreach ($updates as $update) {
			$this->lastUpdateId = $update->getUpdateId();
			$callback = $update->toArray()["callback_query"] ?? null;

			if (!$callback) {
				continue;
			}

			$userId = $callback["from"]["id"];
			$callbackId = $callback["id"];
			$messageId = $callback["message"]["message_id"];
			$callbackData = $callback["data"];

			if (!str_starts_with($callbackData, "banedetta_")) {
				return;
			}

			if (!$this->isAdmin($userId)) {
				$message = $this->translator->translate(null, "tg.ban.not_admin");
				$this->requests->answerCallback($callbackId, $message, true);
				continue;
			}

			$this->db->getDataById($messageId)->onCompletion(
				function (array $data) use ($callbackData, $callbackId, $messageId) {
					if (empty($data)) {
						$message = $this->translator->translate(null, "tg.ban.post_not_found");
						$this->requests->answerCallback($callbackId, $message, false);
						$this->requests->removePost($messageId);
						return;
					}

					if ($callbackData === "banedetta_confirm") {
						$this->getBansManager()->confirm($data["banned"]);
						$confirmed = true;
					} elseif ($callbackData === "banedetta_reject") {
						$this->getBansManager()->notConfirm($data["banned"]);
						$confirmed = false;
					}

					$message = $this->translator->translate(
						null,
						$confirmed ? "tg.ban.confirmed" : "tg.ban.not_confirmed",
						["{banned}" => $data["banned"]]
					);
					$this->requests->answerCallback($callbackId, $message, false);
				},
				fn () => null
			);
		}

		$this->requests->updates($this->lastUpdateId + 1);
	}
}
