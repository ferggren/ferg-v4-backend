<?php
Class m1478155609_feed_desc {
  public static function up() {
    Database::query("ALTER TABLE feed ADD feed_desc_ru CHAR(100) NOT NULL AFTER feed_title_ru");
    Database::query("ALTER TABLE feed ADD feed_desc_en CHAR(100) NOT NULL AFTER feed_title_en");
  }

  public static function down() {
    return false;
  }
}
?>