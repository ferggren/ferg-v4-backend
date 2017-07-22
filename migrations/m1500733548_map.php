<?php
Class m1500733548_map {
  public static function up() {
    Database::query(
      "ALTER TABLE feed
        ADD feed_location VARCHAR(100) NOT NULL AFTER feed_desc_en,
        ADD feed_gps VARCHAR(100) NOT NULL AFTER feed_location,
        ADD feed_tags VARCHAR(150) NOT NULL AFTER feed_gps"
    );
    
    Database::query("ALTER TABLE feed ADD feed_marker VARCHAR(100) NOT NULL AFTER feed_preview");
  }

  public static function down() {
    return false;
  }
}
?>