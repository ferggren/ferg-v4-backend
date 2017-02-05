<?php
Class m1478240266_pages_rename {
  public static function up() {
    Database::query("TRUNCATE TABLE feed");
    Database::query("ALTER TABLE feed CHANGE feed_type feed_type ENUM('events','blog','dev','gallery') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");    
    Database::query("ALTER TABLE media_pages CHANGE page_type page_type ENUM('notes','moments','portfolio','events','blog','dev') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
    Database::query("UPDATE media_pages SET page_type='dev' WHERE page_type='portfolio'");
    Database::query("UPDATE media_pages SET page_type='events' WHERE page_type='moments'");
    Database::query("UPDATE media_pages SET page_type='blog' WHERE page_type='notes'");
    Database::query("ALTER TABLE media_pages CHANGE page_type page_type ENUM('events','blog','dev') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
  }

  public static function down() {
    return false;
  }
}
?>