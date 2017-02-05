<?php
Class m1477823033_pages {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS media_entries (
        entry_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        entry_key char(50) NOT NULL,
        PRIMARY KEY (entry_id),
        UNIQUE KEY entry_key (entry_key)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS media_entries_content (
        entry_id int(10) unsigned NOT NULL,
        entry_lang enum('any','ru','en') NOT NULL,
        entry_visible tinyint(1) NOT NULL DEFAULT '0',
        entry_desc varchar(100) NOT NULL,
        entry_title varchar(50) NOT NULL,
        entry_text_raw text NOT NULL,
        entry_text_html text NOT NULL,
        entry_views int(10) unsigned NOT NULL DEFAULT '0',
        entry_last_view_ip int(10) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (entry_id,entry_lang)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS media_entries_photos (
        entry_id int(10) unsigned NOT NULL,
        photo_id int(10) unsigned NOT NULL,
        PRIMARY KEY (entry_id,photo_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
    
    Database::query(
      "CREATE TABLE IF NOT EXISTS media_pages (
        page_id int(10) unsigned NOT NULL AUTO_INCREMENT,
        page_type enum('notes','moments','portfolio') NOT NULL,
        page_photo_id int(10) unsigned NOT NULL,
        page_tags char(200) NOT NULL,
        page_date char(10) NOT NULL,
        page_date_timestamp int(10) unsigned NOT NULL,
        page_versions set('ru','en') NOT NULL,
        page_visible tinyint(1) NOT NULL DEFAULT '0',
        page_deleted tinyint(1) NOT NULL DEFAULT '0',
        page_views int(10) unsigned NOT NULL DEFAULT '0',
        page_last_view_ip int(10) unsigned NOT NULL DEFAULT '0',
        created_at int(10) unsigned NOT NULL,
        updated_at int(10) unsigned NOT NULL,
        PRIMARY KEY (page_id),
        KEY page_group (page_type,page_visible)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    return false;
  }
}
?>