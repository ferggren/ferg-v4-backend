<?php
Class m1477823293_tags {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS tags (
        tag_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        group_id int(10) unsigned NOT NULL,
        tag_name char(100) NOT NULL,
        tag_weight int(10) unsigned NOT NULL,
        PRIMARY KEY (tag_id),
        UNIQUE KEY tag_id (group_id,tag_name)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS tags_groups (
        group_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        group_name char(50) NOT NULL,
        PRIMARY KEY (group_id),
        UNIQUE KEY tag_key (group_name)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS tags_relations (
        group_id int(10) unsigned NOT NULL,
        tag_id int(10) unsigned NOT NULL,
        relation_id int(10) unsigned NOT NULL,
        relation_weight tinyint(3) unsigned NOT NULL DEFAULT '1',
        PRIMARY KEY (group_id,tag_id,relation_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    return false;
  }
}
?>