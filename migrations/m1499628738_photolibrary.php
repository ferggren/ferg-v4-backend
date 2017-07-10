<?php
Class m1499628738_photolibrary {
  public static function up() {
    Database::query("ALTER TABLE photolibrary ADD photo_location CHAR(50) NOT NULL AFTER photo_category");
    Database::query("ALTER TABLE photolibrary ADD photo_show_in_photostream BOOLEAN NOT NULL DEFAULT '0'");
    Database::query("ALTER TABLE `photolibrary` DROP `user_id`");
    Database::query("ALTER TABLE `photolibrary_collections` DROP `user_id`");
  }

  public static function down() {
    return false;
  }
}
?>