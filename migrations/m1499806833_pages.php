<?php
Class m1499806833_pages {
  public static function up() {
    Database::query("ALTER TABLE media_pages ADD page_gps CHAR(60) NOT NULL AFTER page_tags");
    Database::query("ALTER TABLE media_pages ADD page_location CHAR(60) NOT NULL AFTER page_gps");
    Database::query("ALTER TABLE feed CHANGE feed_type feed_type ENUM('travel', 'blog', 'photostream') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");

  public static function down() {
    return false;
  }
}
?>