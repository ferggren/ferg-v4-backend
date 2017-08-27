<?php
Class m1503824754_photolib_exif {
  public static function up() {
    Database::query("ALTER TABLE photolibrary CHANGE photo_taken photo_taken CHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
  }

  public static function down() {
    return false;
  }
}
?>