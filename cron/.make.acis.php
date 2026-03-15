<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

require_once 'inc.common.php';
require_once 'inc.sqlite.php';
require_once 'inc.csv.php';
require_once 'inc.acis.php';
$total = -microtime(true);

// 毒性データ SQL 作成
$ftoxic  = include_once 'inc.acis.dokusei.php';

// CSV データ更新
$fupdate |= include_once 'inc.acis.csv.php';

//メインデータベース更新
// 1. 準備：cron/acis.db がなければ data/acis.db をコピー
if (!file_exists($maindb)) copy("$datdir/$maindb", $maindb);

// 2. Open (1回だけ)
OpenDB($db);

// 3. 高速化設定 (最初)
$db->exec('PRAGMA journal_mode = WAL;');
$db->exec('PRAGMA synchronous = OFF;');

//毒性データベース更新
if ($ftoxic) {
//if ($ftoxic || $facis) {
  $files = array();
  $files['dokusei'] = "$datdir/$dokusei";
  $files['suisan']  = "$datdir/$suisan";
  $files['seizai']  = "$datdir/$seizai";
  $ftoxic = false;
  $time = -microtime(true);
  foreach($files as $item => $file) {
    $time = -microtime(true);
    $sql = file_get_contents($file);
    if (strtolower(mb_detect_encoding($sql, 'sjis-win, utf-8')) == 'sjis-win') $sql = mb_convert_encoding($sql, 'utf-8', 'sjis-win');
    $res = $db->exec($sql);
    if ($res === false) {
      $err = $db->errorInfo();
      logputs("acis $item", $err[2], 'Cron DB Error');
      if ($debug) echo "acis: $item: $err[2]\n";
      return 2;
    }
    $time += microtime(true);
    if ($debug) echo "acis: $item: Created $time\n";
  }
} else {
  if ($debug) echo "acis: toxic: Not Updated\n";
}

// csv 取り込み後に、関連テーブル・ビュー作成
$finfo   = include_once 'inc.acis.reginfo.php';
$fbyochu = include_once 'inc.acis.byochu.php';
$fsaku   = include_once 'inc.acis.sakumotsu.php';
$facis = $finfo || $fbyochu || $fsaku;

// info テーブル及びビュー作成
if ($facis) {
  $time = -microtime(true);
  $lastUpdate = mb_convert_encoding(rtrim(file_get_contents("$datdir/$update")), 'UTF-8', 'SJIS-win');
  $lastUpdate = preg_replace('/分$/', '', $lastUpdate);
  $sql = <<<SQL6
  create table if not exists main.info (Item varchar primary key, Value varchar);
  insert or replace into main.info (Item, Value) values ('LastUpdate', '$lastUpdate');
  insert or replace into main.info (Item, Value) values ('Version', '$dbver');
  create view if not exists vTekiyo as select bango,shurui,meisho,sakumotsu,byochu,mokuteki,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei,ryakusho from m_tekiyo left join m_kihon using(bango);
  create view if not exists vTsushoTekiyo as select distinct sakumotsu,byochu,mokuteki,shurui,tsusho,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei from m_tekiyo left join m_kihon using(bango);
  --drop view if exists tekiyo;
  create view if not exists tekiyo as select bango,shurui,meisho,tsusho,xidsaku,idsaku,sakumotsu,idbyochu,byochu,mokuteki,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei,ryakusho from m_tekiyo left join m_kihon using(bango) left join m_sakumotsu using(sakumotsu) left join m_byochu using(byochu);
  SQL6;
  $res = $db->exec($sql);
  $time += microtime(true);
  if ($res === false){
    $err = $db->errorInfo();
    logputs('acis view', $err[2], 'Cron DB Error');
    echo "acis: view: $err[2]\n";
  } else {
    if ($debug) echo "acis: view: Created $time\n";
  }
}

// 4. 仕上げ設定 (最後)
$res = $db->query('PRAGMA integrity_check;')->fetch();
if ($res[0] === 'ok') {
  $db->exec('PRAGMA journal_mode = DELETE;'); // WALを統合して消す
  dbClose($db);
  // 確実に物理ファイルとしてコピーを完了させる
  if (!copy($maindb, "$datdir/$maindb")) {
    die("Failed to copy $maindb to $datdir");
  }
} else {
  dbClose($db);
  unlink($maindb);
  die('Aborted database update');
}

if (!$facis && !$ftoxic) return 1;

// 公開 zip ファイル更新
//exec("zip -jDq $datdir/$mainzip $datdir/$maindb");
$zip = new ZipArchive();
// CREATE | OVERWRITE で、確実に最新の状態だけで作り直す
if ($zip->open("$datdir/$mainzip", ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
  // $datdir 側の確定したファイルを、$maindb（ファイル名のみ）として格納
  $zip->addFile("$datdir/$maindb", $maindb);
  $zip->close();
} else {
  die("Failed to create ZIP file with ZipArchive.");
}
// 検索システム用データベース更新
if ($dbpath) {
  mkdir("$dbpath/$udflag");
  copy("./$maindb", "$dbpath/$maindb");
  //touch("$dbpath/$maindb", getLastModified($maindb));
  rmdir("$dbpath/$udflag");
}
$total += microtime(true);
logputs('acis', "Created $total");
if ($debug) echo "acis: Created $total\n";
return 0;
?>
