<?php
Class m1499427699_storage {
  public static function up() {
    Database::query("ALTER TABLE `storage_files` ADD `file_group` ENUM('storage', 'photolibrary') NOT NULL DEFAULT 'storage' AFTER `user_id`");

    $groups = [];

    foreach (Database::from('storage_groups')->get() as $group) {
      $groups[$group->group_id] = $group->group_name;
    }

    foreach (Database::from('storage_files')->get() as $file) {
      $file->file_group = isset($groups[$file->group_id]) ? $groups[$file->group_id] : 'storage';
      $file->save();
    }

    Database::query("ALTER TABLE `storage_files` DROP `user_id`, DROP `group_id`");
    Database::query("ALTER TABLE `storage_files` ADD INDEX (`file_group`, `file_media`)");
    Database::query("ALTER TABLE `storage_files` ADD INDEX (`file_media`, `file_group`)");
    Database::query("DROP TABLE `storage_groups`");
  }

  public static function down() {
    return false;
  }
}
?>