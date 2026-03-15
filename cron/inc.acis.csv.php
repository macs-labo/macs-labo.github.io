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
$fupdate |= $title != mb_convert_encoding(file_get_contents("$datdir/$update"), 'UTF-8', 'SJIS-win');
// 登録基本部の .zip ファイルと toroku.zip/kihon.csv ファイルで更新チェック
// 登録機本部 .zip は If-Modified-Since が使えなくなったので、Last-Modified 取得方式に変更
$dlurl = dirname($dlpage);
$res = is_modified("$dlurl/$files[1]", getLastModified("$datdir/$csvzip/$kihon"), $fupdate);
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
copy("./$kihon", "$datdir/$kihon");
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
copy("./$tekiyo", "$datdir/$tekiyo");
$time += microtime(true);
if ($debug) echo "csv: tekiyo: Converted $time\n";

file_put_contents($update, mb_convert_encoding($title, 'SJIS-win', 'UTF-8'));

// $title から最新の西暦日付を抽出し、GitHubタグ用JSONを生成する
// 1. まず「失効」という文字があれば「99日」に置換しておく（正規表現を共通化するため）
$tmpTitle = str_replace('失効', '99日', $title);

// 2. 西暦・月・日を抽出
preg_match_all('/(\d{4})[\s年\/-](\d{1,2})[\s月\/-](\d{1,2})/u', $tmpTitle, $matches, PREG_SET_ORDER);
$dates = [];
foreach ($matches as $match) {
  // 文字列として 8桁 (YYYYMMDD) に整形
  $dates[] = sprintf('%04d%02d%02d', $match[1], $match[2], $match[3]);
}

// 3. 文字列比較で最大値を取得
$latest = max($dates); // 例: "20260299"

// 4. GitHub タグ形式の生成
// 99日のままだとタグとして少し特殊なので、一応そのまま出すか、00等にするかは好みですが
// 文字列比較の整合性を保つため、ここでは抽出した数字をそのままドット区切りにします
$tag = 'v' . substr($latest, 0, 4) . '.' . substr($latest, 4, 2) . '.' . substr($latest, 6, 2);

$updateData = [
    "version_tag" => $tag,
    "raw_title"    => $title, // オリジナルの「〜失効反映」を残す
    "update_date"  => date("Y-m-d H:i:s"),
    "database_ver" => $latest
];

$jsonResult = json_encode($updateData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonResult) file_put_contents("update.json", $jsonResult);

// ファイル圧縮と転送
exec("zip -Dq $csvzip $update $kihon $tekiyo");
//touch($csvzip, getLastModified($kihon));
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
