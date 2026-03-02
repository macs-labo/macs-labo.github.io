<?php
//カレントディレクトリをスクリプトディレクトリに変更
chdir(__DIR__);

require_once 'inc.common.php';
require_once 'inc.sqlite.php';
require_once 'inc.csv.php';
require_once 'inc.acis.php';
$total = -microtime(true);

//メインデータベース更新
$finfo   = include_once 'inc.acis.reginfo.php';
$fbyochu = include_once 'inc.acis.byochu.php';
$fsaku   = include_once 'inc.acis.sakumotsu.php';
if (!$finfo && !$fbyochu && !$fsaku) return 1;

// info テーブル及びビュー作成
$time = -microtime(true);
$update = mb_convert_encoding(rtrim(file_get_contents("update.txt")), 'UTF-8', 'SJIS-win');
$update = preg_replace('/分$/', '', $update);
$sql = <<<SQL6
create table if not exists main.info (Item varchar primary key, Value varchar);
insert or replace into main.info (Item, Value) values ('LastUpdate', '$update');
insert or replace into main.info (Item, Value) values ('Version', '$dbver');
create view if not exists vTekiyo as select bango,shurui,meisho,sakumotsu,byochu,mokuteki,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei,ryakusho from m_tekiyo left join m_kihon using(bango);
create view if not exists vTsushoTekiyo as select distinct sakumotsu,byochu,mokuteki,shurui,tsusho,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei from m_tekiyo left join m_kihon using(bango);
--drop view if exists tekiyo;
create view if not exists tekiyo as select bango,shurui,meisho,tsusho,xidsaku,idsaku,sakumotsu,idbyochu,byochu,mokuteki,jiki,baisu,ekiryo,hoho,basho,jikan,ondo,dojo,chitai,tekiyaku,kongo,kaisu,seibun1,keito1,kaisu1,seibun2,keito2,kaisu2,seibun3,keito3,kaisu3,seibun4,keito4,kaisu4,seibun5,keito5,kaisu5,yoto,koka,zaikei,ryakusho from m_tekiyo left join m_kihon using(bango) left join m_sakumotsu using(sakumotsu) left join m_byochu using(byochu);
SQL6;
OpenDB($db);
$res = $db->exec($sql);
$time += microtime(true);
if ($res === false){
  $err = $db->errorInfo();
  logputs('acis view', $err[2], 'Cron DB Error');
  echo "acis: view: $err[2]\n";
} else {
  if ($debug) echo "acis: view: Created $time\n";
}
dbClose($db);

// 公開 zip ファイル更新
exec("zip -Dq $mainzip $maindb");
rename("./$mainzip", "$datdir/$mainzip");
// 検索システム用データベース更新
if ($dbpath) {
  mkdir("$dbpath/$udflag");
  copy("./$maindb", "$dbpath/$maindb");
  //touch("$dbpath/$maindb", filemtime($maindb));
  rmdir("$dbpath/$udflag");
}
$total += microtime(true);
logputs('acis', "Created $total");
if ($debug) echo "acis: Created $total\n";
return 0;
?>
