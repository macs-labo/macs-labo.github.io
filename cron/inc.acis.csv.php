<?php
require_once 'inc.common.php';
require_once 'inc.csv.php';

/* CSVデータファイル更新チェック */
// ダウンロードページ取得
$s = file_get_contents($dlpage);
if ($s === false) {
  if ($debug) echo ("csv: Cannot get $dlpage\n");
  return false;
}
$s = mb_convert_encoding($s, 'UTF-8', 'auto');
// コメント削除
$s = preg_replace('/<!--.*?-->\r*\n*/s', '', $s);
// データタイトル取得
$title = preg_replace('#.*(<p\s+class="bold"\s*>|<h4>)(.*?)</.*#is', '$2', $s);
// ファイル名取得
$files[0] = preg_replace('|.*<a\s+href="(.*?)"[^>]*>登録基本部.*|is', '$1', $s);
$files[1] = preg_replace('|.*<a\s+href="(.*?)"[^>]*>登録適用部一.*|is', '$1', $s);
$files[2] = preg_replace('|.*<a\s+href="(.*?)"[^>]*>登録適用部二.*|is', '$1', $s);
// http レスポンスヘッダで Last-Modified が取得できないので、$title と update.txt の内容が異なる場合は強制更新
$fupdate = $fupdate || $title != mb_convert_encoding(file_get_contents($update), 'UTF-8', 'SJIS-win');
// 登録基本部の .zip ファイルと toroku.zip ファイルで更新チェック
$dlurl = dirname($dlpage);
$mtime = filemtime("$datdir/$csvzip");
$res = $fupdate || !$mtime || $mtime <= filemtime(__FILE__) || is_modified("$dlurl/$files[0]", $mtime) !== false;
if (!$res) {
  if ($debug) echo ("csv: Not Modified\n");
  return false;
}
$madetime = -microtime(true);

// ZIP ファイルのダウンロードと解凍
$time = -microtime(true);
$csvdir = dirname($files[0]);
if ($csvdir) $dlurl .= "/$csvdir";
foreach($files as &$file) {
  if ($csvdir) $file = preg_replace("|$csvdir/|", '', $file);
  file_put_contents("./$file", file_get_contents("$dlurl/$file"), LOCK_EX);
  $list = explode("\n", `unzip -l $file`);
  $item = explode("\t", preg_replace('/\s+/', "\t", trim($list[3])));
  exec("unzip -o ./$file");
  unlink("./$file");
  $file = $item[3];
}
unset($file);
$time += microtime(true);
if ($debug) echo "csv: Downloaded $time\n";

// 登録基本部を MACS CSV 形式に変換
$time = -microtime(true);
$file = array_shift($files);
file_convert($file);
$wh = fopen($kihon, 'w');
$rh = fopen($file, 'r');
$rec = fgetcsv($rh);
$cols = reccount($rec);
fwrite($wh, macscsv($rec, $cols)."\r\n");
while ($rec = fgetcsv($rh)) fwrite($wh, macscsv($rec, $cols)."\r\n");
fclose($rh);
unlink($file);
fclose($wh);
$time += microtime(true);
if ($debug) echo "csv: kihon: Converted $time\n";

// 登録適用部を MACS CSV 形式に変換
$time = -microtime(true);
$thead = true;
$wh = fopen($tekiyo, 'w');
foreach($files as $file) {
  file_convert($file);
  $rh = fopen($file, 'r');
  $rec = fgetcsv($rh);
  if ($thead) {
    $thead = false;
    $cols = reccount($rec);
    fwrite($wh, macscsv($rec, $cols)."\r\n");
  }
  while ($rec = fgetcsv($rh)) fwrite($wh, macscsv($rec, $cols)."\r\n");
  fclose($rh);
  unlink($file);
}
fclose($wh);
$time += microtime(true);
if ($debug) echo "csv: tekiyo: Converted $time\n";

file_put_contents($update, mb_convert_encoding($title, 'SJIS-win', 'UTF-8'));

// ファイル圧縮と転送
exec("zip -Dq $csvzip $update $kihon $tekiyo");
touch($csvzip, filemtime($kihon));
unlink("$datdir/$update");
rename("./$csvzip", "$datdir/$csvzip");
copy("./$update", "$datdir/$update");

$madetime += microtime(true);
logputs('csv', "Created $madetime");
if ($debug) echo "csv: Created $madetime\n";

return 1;

function file_convert($file) {
  //eval(mb_convert_encoding(file_get_contents('eval.winsjis.php'), 'UTF-8', 'SJIS-win'));
  $from = array('㍑', '㍍', '㌢', '㎝', '㎜', '㎏', '㎞', '㏄', '㌘', '①', '②', '③', '④', '⑤');
  $into = array('L', 'm', 'cm', 'cm', 'mm', 'kg', 'km', 'cc', 'g', '(1)', '(2)', '(3)', '(4)', '(5)');
  $s = file_get_contents($file);
  if ($s === false) {
    logputs('csv', "Not Found $file", 'Cron File Error');
    if ($debug) echo "csv: Not Found $file";
    return 2;
  }
  unlink($file);
  $s = mb_convert_kana(str_replace('，', '、', mb_convert_encoding($s, 'UTF-8', 'SJIS-win')), 'asKV');
  //$s = preg_replace('/[\r\n]+\"/', '"', $s);
  file_put_contents($file, str_replace($from, $into, $s));
}

function reccount($rec) {
  $c = count($rec);
  if (!end($rec)) $c--;
  return $c;
}

function macscsv($rec, $cols) {
//  $rec = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', str_replace(array("\r", "\n"), '', $line), -1, PREG_SPLIT_DELIM_CAPTURE);
  $rec = str_replace(array("\r", "\n"), '', $rec);
  $diff = $cols - count($rec);
  if ($diff > 0) {
    $rec = array_merge($rec, array_fill(0, $diff, ''));
  } elseif ($diff < 0) {
    $rec = array_slice($rec, 0, $cols);
  }
  foreach($rec as &$col) {
    if ($col == '') {
      $col = 'NULL';
    } elseif (!preg_match('/^[0-9]+$/', $col)) {
      $col = trim($col, '"');
      $col = str_replace(array('""', '“'), '"', $col);
      $col = "'$col'";
    }
  }
  return mb_convert_encoding(implode(',', $rec), 'SJIS-win', 'UTF-8');
}
?>
