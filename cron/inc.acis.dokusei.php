<?php

require_once 'inc.common.php';
require_once 'inc.dokusei.php';
if ($libdir !== '.') set_include_path(get_include_path().PATH_SEPARATOR.$libdir);
use Shuchkin\SimpleXLSX;
require_once 'Excel/SimpleXLSX.php';
use Shuchkin\SimpleXLS;
require_once 'Excel/SimpleXLS.php';

/* 毒性関係ファイル更新 */

// ダウンロードファイル名取得
$body = file_get_contents("$txpath/$txpage");
$fdokusei = preg_replace("/$pdokusei/is", '\1', $body);
$fsuisan = preg_replace("/$psuisan/is", '\1', $body);
$fseizai = preg_replace("/$pseizai/is", '\1', $body);

// 毒性関連 ZIP ファイル取得・解凍
function get_new_file($fname, $mtime) {
  global $debug, $txpath, $date, $fupdate;
  $date = '';
  $url = "$txpath/$fname";
  if (!is_modified($url, $mtime, $fupdate)) {
    if ($debug) echo "$url: Not Modified\n";
    return false;
  }
  $file = file_get_contents($url);
  if ($file === false) {
    if ($debug) echo "Cannot download: $url\n";
    return false;
  }
  $date = date('Y.m.d', $modified);
  file_put_contents("./$fname", $file);
  $list = explode("\n", `unzip -l $fname`);
  $xfile = substr($list[3], strrpos($list[1], ' ')+1);
  exec("unzip -o $fname");
  unlink($fname);
  return $xfile;
}

// Excel ファイル読込
function read_excel($xfile) {
  global $debug;
  if (!$xfile) return false;
  $ext = strtolower(pathinfo($xfile, PATHINFO_EXTENSION));
  $reader = null;
  if ($ext === 'xlsx') {
    $reader = SimpleXLSX::parse($xfile);
  } elseif ($ext === 'xls') {
    $reader = SimpleXLS::parse($xfile);
  }
  if ($reader) return $reader;
  $err = ($ext === 'xlsx') ? SimpleXLSX::parseError() : SimpleXLS::parseError();
  if ($debug) echo "Parse Error ($ext): " . $err . "\n";
  $return = false;
}

$updated = false;

//水産動植物への影響
$time = -microtime(true);
$xfile = get_new_file($fsuisan, getLastModified("$chkbase/$suisan"));
$sql = <<<SUISAN
--/d
/* 水産動植物への影響に係る使用上の注意事項(製剤別) $date */
create table if not exists info (item varchar primary key, value varchar);
insert or replace into info (item, value) values ('suisan', '$date');
drop table if exists suisan;
create table suisan (bango integer, chuijiko varchar);
begin transaction;\n
SUISAN;
$excel = read_excel($xfile);
if ($excel) {
  $rows = $excel->rows(1); // PHPExcel の getSheet(1) 相当
  foreach ($rows as $r) {
    // A列 ($r[0]) が数値でない場合はスキップ
    if (!isset($r[0]) || !is_numeric($r[0])) continue;

    $data = [];
    $data[] = $r[0]; // A列 (1列目)

    // D列 (4列目) の加工
    if (isset($r[3])) {
      $val = $r[3];
      $val = str_replace("\n", '#', $val);
      $val = preg_replace('/(?<=^|#)・/', '', $val);
      $val = str_replace('。・', '。#', $val);
      $data[] = "'$val'";
    } else {
      $data[] = "''";
    }

    $out = implode(', ', $data);
    
    // 行の最終バリデーション
    if (!preg_match('/^[0-9]+,/', $out)) continue;

    $out = mb_convert_kana($out, 'asKV');
    $sql .= "insert into suisan values ($out);\n";
  }

  $sql .= "commit;\n";
  file_put_contents("$datdir/$suisan", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);
  
  unlink($xfile);
  $time += microtime(true);
  logputs($suisan, "Created $time");
  if ($debug) echo "$suisan Created $time\n";
  $updated = true;
}

//製剤毒性
$time = -microtime(true);
$xfile = get_new_file($fseizai, getLastModified("$chkbase/$seizai"));
$sql = <<<SEIZAI
--/d
/* 製剤追加情報テーブル $date */
create table if not exists info (item varchar primary key, value varchar);
insert or replace into info (item, value) values ('seizai', '$date');
drop table if exists seizai;
create table seizai (bango integer primary key, dokusei varchar, kousin varchar);
begin transaction;\n
SEIZAI;
$excel = read_excel($xfile);
if ($excel) {
  $rows = $excel->rows(0);
  foreach ($rows as $r) {
    // 列数が 6 未満またはA列が数値でない場合はスキップ
    if (count($r) < 6 || !is_numeric($r[0])) continue;

    $data = [];
    $data[] = $r[0]; // A列 (1列目)
    $data[] = "'$r[5]'"; // F列 (6列目)
    $data[] = 'NULL'; // G列(7列目の日付は日付変換せずに破棄)

    $out = implode(', ', $data);
    if (!preg_match('/^[0-9]+,/', $out)) continue;
    $out = mb_convert_kana($out, 'asKV');
    $sql .= "insert into seizai values ($out);\n";
  }

  $sql .= "commit;\n";
  file_put_contents("$datdir/$seizai", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);

  unlink($xfile);
  $time += microtime(true);
  logputs($seizai, "Created $time");
  if ($debug) echo "$seizai Created $time\n";
  $updated = true;
}

// 有効成分毒性
$time = -microtime(true);
$xfile = get_new_file($fdokusei, getLastModified("$chkbase/$dokusei"));
$sql = <<<DOKUSEI1
--/d
/* 有効成分毒性マスター $date */
create table if not exists info (item varchar primary key, value varchar);
insert or replace into info (item, value) values ('m_dokusei', '$date');
drop table if exists m_dokusei;
create table m_dokusei (ippanmei varchar, nai varchar, yoto varchar, biko varchar, ojas varchar, rac varchar, dokusei varchar, adi varchar, arfd varchar, wqs varchar, eqs varchar, hatsutoroku varchar, jogai varchar);
begin transaction;\n
DOKUSEI1;
$sqlfoot = <<<DOKUSEI2
commit;
drop view if exists dokusei;
create view dokusei as select ippanmei,seibunmei,yoto,dokusei,jogai, biko, ojas, rac, null as seibunEikyo from m_dokusei left join (select distinct ippanmei, seibun as seibunmei from seibun) using(ippanmei);\n
DOKUSEI2;
$excel = read_excel($xfile);
if ($excel) {
  $rows = $excel->rows(0);
  foreach ($rows as $r) {
    // $r の長さが 13 未満の場合はスキップ
    if (count($r) < 13) continue;

    // $r の先頭(A列)と 14 番目(N列)以降をカット
    $data = array_slice($r, 1, 12);
    if (is_numeric($data[11])) {
        // 元のM列が数値の場合：Excelシリアル値からUnixシリアル値へ変換
        // ※ 25569 は 1900/1/1 と 1970/1/1 の差日数、86400 は1日の秒数 60 * 60 * 24
        $timestamp = ($data[11] - 25569) * 86400;
    } else {
        // 文字列の場合：strtotime でタイムスタンプへ変換
        $timestamp = strtotime($data[11]);
    }
    // 変換に成功していればフォーマットを整える
    if ($timestamp) $data[11] = date('Y.m.d', $timestamp);
    $out = trim(implode("\t", $data));
    $out = str_replace("\n", ' ', $out);
    $count = substr_count($out, "\t");
    if ($count < 11) continue;
    if (preg_match('/^有効成分/', $out)) continue;
    $out .= "\t";
    $out = mb_convert_kana($out, 'asKV');
    $out = preg_replace('/銅\((.*?)\)/', '\1', $out);
    $out = preg_replace('/(.*?)(毒|劇)\((.*?)\)(.*)/', '\1\2\4\3', $out);
    $out = preg_replace('/(?<=^|\t)([^\t]+)(?=\t|$)/', "'$1'", $out);
    $out = preg_replace('/(\t)(?=\t|$)/', '\1NULL', $out);
    $out = str_replace("\t", ',', $out);
    $sql .= "insert into m_dokusei values ($out);\n";
  }
  $sql .= $sqlfoot;
  file_put_contents("$datdir/$dokusei", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);

  unlink($xfile);
  $time += microtime(true);
  logputs($dokusei, "Created $time");
  if ($debug) echo "$dokusei Created $time\n";
  $updated = true;
}

return $updated;

?>
