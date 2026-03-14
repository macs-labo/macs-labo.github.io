<?php
require_once 'inc.common.php';

$file = 'cron.zip';
$src = "https://raw.githubusercontent.com/macs-labo/macs-labo.github.io/main/cron/$file";
$fupdate = getForceUpdate();

//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

$total = -microtime(true);

// ローカルと携帯農薬実験室の cron.zip のタイムスタンプを比較して、実験室が新しければファイル同期
$mtime = is_modified($src, getLastModified($file));
if (!$fupdate && $mtime === false) {
  echo "sync: $file: Not Modified\n";
  exit(1);
}

$body = file_get_contents($src);
if ($body === false) {
  echo "sync: Cannot get $src\n";
  exit(1);
}
file_put_contents($file, $body, LOCK_EX);
touch($file, $mtime);
echo "sync: $file: Downloaded\n";

// unzip コマンドが使えるか確認 (リターンコードが 0 なら成功)
$return_var = 0;
$output = [];
exec("unzip -v", $output, $return_var);

if ($return_var === 0) {
  exec("unzip -o ./$filename");
  echo "sync: $file: Updated\n";
} elseif (class_exists('ZipArchive')) {
  // unzip コマンドがない場合は ZipArchive クラスを使用
  $zip = new ZipArchive;
  if ($zip->open("./$filename") === TRUE) {
    $zip->extractTo('./');
    $zip->close();
    echo "sync: $file: Updated\n";
  } else {
    echo "Failed to unzip $filename\n";
    exit(1);
  }
} else {
  echo "Error: unzip command not found and ZipArchive class is missing.\n";
  exit(1);
}

require_once '.update.cron.php';
