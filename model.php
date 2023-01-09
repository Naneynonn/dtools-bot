<?php

class DB
{
  private $db;

  public function __construct()
  {
    $this->db = new PDO('pgsql:host=' . CONFIG['connect']['host'] . ';port=5432;dbname=' . CONFIG['connect']['dbname'], CONFIG['connect']['user'], CONFIG['connect']['pass']);
  }

  public function getSettingsServer(string $id): array|false
  {
    $sql = $this->db->prepare("SELECT * FROM settings_automod a LEFT JOIN servers s ON a.server_id = s.server_id WHERE a.server_id = ?");
    $sql->execute([$id]);

    return $sql->fetch();
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

  public function deleteGuild(string $server_id): void
  {
    $sql = $this->db->prepare("UPDATE servers SET is_active = false WHERE server_id = ?");
    $sql->execute([$server_id]);
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
}
