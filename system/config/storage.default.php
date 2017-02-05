<?php
$config = array(
  // Max size for uploaded file
  'max_filesize' => 1024 * 1024 * 10,

  // Config for preview generator
  'image_preview' => array(
    // Maximum size of file
    // If size is greater, preview will not be available
    'max_filesize' => (1024 * 1024 * 4),

    // Maximum width and height of file
    // If width or height is greater, preview will not be available
    'max_filewidth' => 4096,
    'max_fileheight' => 4096,

    // Minimum width and height of file
    // If width or height is smaller, preview will not be available
    'min_fileheight' => 120,
    'min_filewidth' => 80,

    // Max width for preview
    // If file width or height is greater, preview will be resized
    'max_width' => 2048,
    'max_height' => 1980,
  ),

  // Storage salt
  'salt' => 'Some salt here',

  // Results per page
  'results_per_page' => 10,

  // Download domain
  'domain' => 'example.com',
);
?>