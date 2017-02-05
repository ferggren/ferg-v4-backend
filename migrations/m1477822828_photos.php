<?php
Class m1477822828_photos {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS photolibrary (
        file_id int(10) unsigned NOT NULL,
        file_hash char(10) NOT NULL,
        user_id int(10) unsigned NOT NULL,
        photo_deleted tinyint(1) NOT NULL DEFAULT '0',
        photo_collection_id smallint(5) unsigned NOT NULL DEFAULT '0',
        photo_size char(10) NOT NULL,
        photo_gps char(20) NOT NULL,
        photo_iso char(20) NOT NULL,
        photo_aperture char(20) NOT NULL,
        photo_shutter_speed char(20) NOT NULL,
        photo_camera char(20) NOT NULL,
        photo_lens char(50) NOT NULL,
        photo_fl char(10) NOT NULL,
        photo_efl char(10) NOT NULL,
        photo_category char(150) NOT NULL,
        photo_taken char(10) NOT NULL,
        photo_taken_timestamp int(10) unsigned NOT NULL,
        photo_title_ru char(50) NOT NULL,
        photo_title_en char(50) NOT NULL,
        photo_added int(10) unsigned NOT NULL,
        photo_views int(10) unsigned NOT NULL DEFAULT '0',
        photo_last_view_ip int(10) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (file_id),
        KEY photo_group_id (photo_collection_id),
        KEY user_id (user_id,photo_deleted)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS photolibrary_collections (
        collection_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        user_id int(10) unsigned NOT NULL,
        collection_name char(20) NOT NULL,
        collection_deleted tinyint(1) NOT NULL DEFAULT '0',
        collection_updated int(10) unsigned NOT NULL DEFAULT '0',
        collection_created int(10) unsigned NOT NULL,
        collection_cover_photo_id int(10) unsigned NOT NULL DEFAULT '0',
        collection_cover_photo_hash char(10) NOT NULL,
        collection_photos int(10) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (collection_id),
        KEY user_id (user_id,collection_deleted)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
  }

  public static function down() {
    return false;
  }
}
?>