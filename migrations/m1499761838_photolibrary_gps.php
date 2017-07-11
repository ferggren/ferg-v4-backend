<?php
Class m1499761838_photolibrary_gps {
  public static function up() {
    Database::query("ALTER TABLE photolibrary CHANGE photo_gps photo_gps CHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
  }

  public static function down() {
    return false;
  }
}
?>