<?php
//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

require_once 'inc.common.php';
require_once 'inc.sqlite.php';
require_once 'inc.sync.data.php';

// 検索システム用データベース更新
function updatedb($file) {
  global $datdir, $dbpath, $udflag, $debug;
  $db = str_replace('.zip', '.db', $file);
  exec("unzip -o $datdir/$file");
  mkdir("$dbpath/$udflag");
  copy("./$db", "$dbpath/$db");
  rmdir("$dbpath/$udflag");
  if ($debug) echo "sync: $db: Updated\n";
}

$fupdate = getForceUpdate();
$total = -microtime(true);

// $files2 と各ソースのタイムスタンプを比較して、ソースが新しければファイル同期
foreach($files2 as $item => $file) {
  $from = $src[$item];
  $dest = "$datdir/$file";
  $mtime = is_modified($from, getLastModified($dest), $fupdate);
  if ($mtime === false) {
    if ($debug) echo "sync: $file: Not Modified\n";
  } else {
    $body = file_get_contents($from);
    if ($body === false) {
      if ($debug) echo "sync: Cannot get $file\n";
      return 1;
    }
    file_put_contents($dest, $body, LOCK_EX);
    touch($dest, $mtime);
    if ($debug) echo "sync: $file: Updated\n";
  }
}

// $files1 と各サイトファイル群のタイムスタンプを比較して、各サイトが新しければファイル同期
foreach($files1 as $file) {
  foreach($sites as $site) {
    clearstatcache();
    $from = "$site/$file";
    $dest = "$datdir/$file";
    $mtime = is_modified($from, getLastModified($dest), $fupdate);
    if ($mtime === false) {
      if ($debug) echo "sync: $file: Not Modified $site\n";
    } else {
      $body = file_get_contents($from);
      if ($body === false) {
        if ($debug) echo "sync: Cannot get $file\n";
        return 1;
      }
      file_put_contents($dest, $body, LOCK_EX);
      touch($dest, $mtime);
      if ($debug) echo "sync: $file: Updated from $site\n";
      if ($file == $mainzip || $file == $subzip) updatedb($file);
    }
  }
}

$total += microtime(true);
if ($debug) echo "sync: All Files Syncronized $total\n";
?>
