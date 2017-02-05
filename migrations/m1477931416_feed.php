<?php
Class m1477931416_feed {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS feed (
        feed_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        feed_type enum('moments','notes','portfolio','gallery') NOT NULL,
        feed_target_id int(10) unsigned NOT NULL,
        feed_preview char(100) NOT NULL,
        feed_ratio decimal(3,1) unsigned NOT NULL,
        feed_title_ru char(50) NOT NULL,
        feed_title_en char(50) NOT NULL,
        feed_order int(10) unsigned NOT NULL,
        PRIMARY KEY (feed_id),
        KEY feed_order (feed_order,feed_id)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    return false;
  }
}
?>