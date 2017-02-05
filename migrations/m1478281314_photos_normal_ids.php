<?php
Class m1478281314_photos_normal_ids {
  public static function up() {
    Database::query("ALTER TABLE photolibrary ADD photo_id INT UNSIGNED NOT NULL FIRST");
    Database::query("UPDATE photolibrary SET photo_id=file_id");
    Database::query("ALTER TABLE photolibrary DROP PRIMARY KEY, ADD UNIQUE (file_id), ADD PRIMARY KEY (photo_id)");
    Database::query("ALTER TABLE photolibrary CHANGE photo_id photo_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
  }

  public static function down() {
    return false;
  }
}
?>