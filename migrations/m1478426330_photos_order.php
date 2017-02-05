<?php
Class m1478426330_photos_order {
  public static function up() {
    // ok until total photos is less than 86400
    Database::query("ALTER TABLE photolibrary ADD photo_orderby INT UNSIGNED NOT NULL DEFAULT '0' AFTER photo_last_view_ip");
    Database::query("ALTER TABLE photolibrary ADD INDEX (photo_collection_id, photo_orderby)");
    Database::query("UPDATE photolibrary SET photo_orderby = photo_id + photo_taken_timestamp");
  }

  public static function down() {
    return false;
  }
}
?>