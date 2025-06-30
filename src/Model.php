<?php

namespace Naneynonn;

use Naneynonn\Config;

use PDO;

class Model
{
  use Config;

  private $db;

  public function __construct()
  {
    $this->db = new PDO('pgsql:host=' . self::DB_HOST . ';port=5432;dbname=' . self::DB_NAME, self::DB_USER, self::DB_PASS, [
      PDO::ATTR_PERSISTENT => true,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }

  public function close(): void
  {
    $this->db = null;
    unset($this->db);
  }

  public function getSettingsServer(string $guild_id): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM settings_automod a LEFT JOIN servers s ON a.server_id = s.server_id WHERE a.server_id = ?");
    $sql->execute([$guild_id]);

    return $sql->fetch();
  }

  public function getAutomodSettings(string $guild_id): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM automod_rules WHERE server_id = ? AND is_enabled");
    $sql->execute([$guild_id]);

    return $sql->fetchAll();
  }

  public function getBadWordsExeption(string $id): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM badwords_exception WHERE server_id = ?");
    $sql->execute([$id]);

    return $sql->fetchAll();
  }

  public function updateGuildInfo(string $name, bool $is_active, ?string $icon, int $members_online, int $members_all, string $server_id): void
  {
    $sql = $this->db->prepare("UPDATE servers SET name = :name, is_active = :is_active, icon = :icon, members_online = :members_online, members_all = :members_all WHERE server_id = :server_id");

    $sql->bindValue(':name', $name, PDO::PARAM_STR);
    $sql->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
    $sql->bindValue(':icon', $icon, PDO::PARAM_STR);
    $sql->bindValue(':members_online', $members_online, PDO::PARAM_INT);
    $sql->bindValue(':members_all', $members_all, PDO::PARAM_INT);
    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);

    $sql->execute();
  }

  public function deleteGuild(string $server_id): array|false
  {
    $sql = $this->db->prepare("UPDATE servers SET is_active = false WHERE server_id = ? RETURNING *");
    $sql->execute([$server_id]);

    return $sql->fetch();
  }

  public function createGuildInfo(string $name, string $lang, ?string $icon, int $members_online, int $members_all, string $server_id): void
  {
    $sql = $this->db->prepare("CALL create_server_info(:server_id, :name, :icon, :lang, :members_online, :members_all)");

    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $sql->bindValue(':name', $name, PDO::PARAM_STR);
    $sql->bindValue(':icon', $icon, PDO::PARAM_STR);
    $sql->bindValue(':lang', $lang, PDO::PARAM_STR);
    $sql->bindValue(':members_online', $members_online, PDO::PARAM_INT);
    $sql->bindValue(':members_all', $members_all, PDO::PARAM_INT);

    $sql->execute();
  }

  public function getServerPerm(string $id, string $module): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM permissions WHERE server_id = ? AND module = ?");
    $sql->execute([$id, $module]);

    return $sql->fetchAll();
  }

  public function updateAutomodLogChannel(string $server_id, string $log_channel): void
  {
    $sql = $this->db->prepare("UPDATE settings_automod SET log_channel = :log_channel WHERE server_id = :server_id");

    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $sql->bindValue(':log_channel', $log_channel, PDO::PARAM_STR);

    $sql->execute();
  }

  public function automodToggle(string $server_id, bool $is_enable): void
  {
    $sql = $this->db->prepare("UPDATE settings_automod SET is_enable = :is_enable WHERE server_id = :server_id");

    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $sql->bindValue(':is_enable', $is_enable, PDO::PARAM_BOOL);

    $sql->execute();
  }

  public function automodToggleCommands(string $server_id, bool $is_enable, string $type): void
  {
    $sql = $this->db->prepare("
          INSERT INTO automod_rules (server_id, type, is_enabled)
          VALUES (:server_id, :type, :is_enabled)
          ON CONFLICT (server_id, type)
          DO UPDATE SET is_enabled = EXCLUDED.is_enabled
      ");

    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $sql->bindValue(':type', $type, PDO::PARAM_STR);
    $sql->bindValue(':is_enabled', (int)$is_enable, PDO::PARAM_INT);

    $sql->execute();
  }

  public function getServerLang(string $id): array
  {
    $sql = $this->db->prepare("SELECT * FROM servers WHERE server_id = ?");
    $sql->execute([$id]);

    return $sql->fetch();
  }

  public function setServerLang(string $server_id, string $lang): void
  {
    $sql = $this->db->prepare("UPDATE servers SET lang = :lang WHERE server_id = :server_id");

    $sql->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $sql->bindValue(':lang', $lang, PDO::PARAM_STR);

    $sql->execute();
  }

  public function getSettingsReactions(string $id): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM settings_reactions r LEFT JOIN servers s ON r.server_id = s.server_id WHERE r.server_id = ?");
    $sql->execute([$id]);

    return $sql->fetch();
  }
}
