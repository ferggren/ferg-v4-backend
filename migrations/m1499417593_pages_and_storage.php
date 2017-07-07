<?php
Class m1499417593_pages_and_storage {
  public static function up() {
    Database::query("ALTER TABLE media_pages CHANGE page_type page_type ENUM('events', 'blog', 'dev', 'travel') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
    Database::query("UPDATE media_pages SET page_type='travel' WHERE page_type='events'");
    Database::query("ALTER TABLE media_pages CHANGE page_type page_type ENUM('blog', 'dev', 'travel') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
    Database::query("UPDATE media_pages SET page_visible=0 WHERE page_type='dev'");
  }

  public static function down() {
    return false;
  }
}
?>