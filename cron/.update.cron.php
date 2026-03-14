<?php
//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

require_once 'inc.setup.php';
require_once 'inc.common.php';
require_once 'inc.files.php';
$files = array_merge($files, $setup);

$dstpath = $crondir;
if (!file_exists($dstpath)) mkdir($dstpath, 0705);
$data = array('kihon.csv', 'tekiyo.csv', 'update.txt', 'acis.db', 'spec.db', 'log.txt');

foreach($files as $file) {
  if (!file_exists($file)) {
    echo "Not Found $file\n";
    continue;
  }
  $mtime = getLastModified($file);
  if ($mtime <= getLastModified("$dstpath/$file")) {
    echo "Not Modified $file\n";
    continue;
  }
  copy("./$file", "$dstpath/$file");
  touch("$dstpath/$file", $mtime);
  echo "Updated $file\n";
}

foreach($data as $file) {
  if (!file_exists($file)) {
    echo "Not Found $file\n";
    continue;
  }
  $mtime1 = getLastModified($file);
  $mtime2 = getLastModified("$dstpath/$file");
  if ($mtime2 < $mtime1) {
    copy("./$file", "$dstpath/$file");
    touch("$dstpath/$file", $mtime1);
    echo "Syncronized cron <- $file\n";
  } elseif ($mtime2 > $mtime1) {
    copy("$dstpath/$file", "./$file");
    touch("./$file", $mtime2);
    echo "Syncronized cron -> $file\n";
  } else {
    echo "Not Modified $file\n";
  }
}
?>
