<?php
Class m1478236580_feed_date {
  public static function up() {
    Database::query("ALTER TABLE feed ADD feed_timestamp INT UNSIGNED NOT NULL DEFAULT '0' AFTER feed_desc_en");
  }

  public static function down() {
    return false;
  }
}
?>