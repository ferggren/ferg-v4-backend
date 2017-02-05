<?php
Class m1477822810_storage {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS storage_bruteforce_protection (
        attempt_ip int(10) unsigned NOT NULL,
        attempt_time int(10) unsigned NOT NULL,
        UNIQUE KEY attempt_ip (attempt_ip,attempt_time),
        KEY attempt_time (attempt_time)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS storage_files (
        file_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        user_id int(10) unsigned NOT NULL,
        group_id int(10) unsigned NOT NULL DEFAULT '0',
        file_hash char(10) NOT NULL,
        file_name char(200) NOT NULL,
        file_media enum('image','video','audio','document','source','archive','other') NOT NULL DEFAULT 'other',
        file_path char(50) NOT NULL,
        file_deleted tinyint(1) NOT NULL DEFAULT '0',
        file_size int(10) unsigned NOT NULL,
        file_uploaded int(10) unsigned NOT NULL,
        file_downloads int(10) unsigned NOT NULL DEFAULT '0',
        file_last_download_time int(10) unsigned NOT NULL,
        file_last_download_ip int(10) unsigned NOT NULL,
        file_preview tinyint(1) NOT NULL DEFAULT '0',
        file_access enum('public','private') NOT NULL DEFAULT 'public',
        PRIMARY KEY (file_id),
        UNIQUE KEY file_hash (file_hash),
        KEY user_group_id (group_id),
        KEY user_id (user_id,group_id,file_media),
        KEY user_id_2 (user_id,file_media),
        KEY file_media (file_media)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS storage_groups (
        group_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        user_id int(10) unsigned NOT NULL,
        group_name char(30) NOT NULL,
        PRIMARY KEY (group_id),
        UNIQUE KEY user_id (user_id,group_name)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS storage_history (
        file_id int(10) unsigned NOT NULL,
        user_ip int(10) unsigned NOT NULL,
        access_time int(10) unsigned NOT NULL,
        user_browser int(20) NOT NULL,
        user_os int(15) NOT NULL,
        KEY file_id (file_id,access_time)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS storage_previews (
        preview_hash char(14) NOT NULL,
        preview_path char(50) NOT NULL,
        preview_last_access int(10) unsigned NOT NULL,
        preview_downloads int(10) unsigned NOT NULL,
        PRIMARY KEY (preview_hash)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    return false;
  }
}
?>