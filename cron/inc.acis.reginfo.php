<?php
// データベースバージョン
$dbver = '190110';

//登録基本部表頭
$hdkihon = array(
  '登録番号' => 'bango', '農薬の種類' => 'shurui', '農薬の名称' => 'meisho', '登録を有する者の名称' => 'ryakusho',
  '有効成分' => 'ippanmei', '総使用回数における有効成分' => 'seibun', '濃度' => 'nodo', '混合数' => 'kongo',
  '用途' => 'yoto', '剤型名' => 'zaikei', '登録年月日' =>'torokubi', '登録の有効期限' => 'kigen'
);

//登録適用部表頭
$hdtekiyo = array(
  '登録番号' => 'bango', '用途' => 'yoto', '農薬の種類' => 'shurui', '農薬の名称' => 'meisho',
  '略登録を有する者の略称' => 'ryakusho2', '作物名' => 'sakumotsu', '適用場所' => 'basho', '適用病害虫雑草名' => 'byochu',
  '使用目的' => 'mokuteki', '希釈倍数使用量' => 'baisu', '散布液量' => 'ekiryo', '使用時期' => 'jiki',
  '本剤の使用回数' => 'kaisu', '使用方法' => 'hoho', 'くん蒸時間' => 'jikan', 'くん蒸温度' => 'ondo',
  '適用土壌' => 'dojo', '適用地帯名' => 'chitai', '適用農薬名' => 'tekiyaku', '混合数' => 'kongo',
  '総使用回数1' => 'kaisu1', '総使用回数2' => 'kaisu2', '総使用回数3' => 'kaisu3',
  '総使用回数4' => 'kaisu4', '総使用回数5' => 'kaisu5'
);

//屋号
$tm1 = 'ACC|BASF|CBC|DAS(?!H)|DIC|FMC|GF|HCC|HJ|HUPL|inochio|ISK|JA|JC|MIC|MAI|OAT|SDS|ST|UBE|ZMCP';
//2024.3.8 ZMCP 追加、F\.G\.|ICI|TG 削除
$tm2 = '^アース(?!ガー)|アダマ|アリスタ|イヅツヤ|カヤク|キャピタル|クレハ|コルテバ|サイアナミッド|' 
     . 'サンケイ|シンジェンタ|ゼオン|ゾエティス|ダグラス|デュポン|トクヤマ|ニューファム|ニッカ|' 
     . 'バイエル|ホクコー|ホクサン|ホドガヤ|マガン|モンサント|ヤシマ|ユーピーエル|ローヌ・プーラン';
//2024.3.8 アベンティス|ゼネカ|タケダ|チバガイギー|トクソー|ナガセ|ファイザー 削除、^アース(?!ガー)|ダグラス 追加(「ダグラス」付きはまだないが…)
$tm3 = '旭|石原|一農|井筒屋|出光|永光|大塚|科研|兼商|京都微研|協友|協和|金鳥|興農|三光|三洋|'
     . '三明|昭和|信越|新富士|住化|住商|住友(化学)?|太洋|南海|日農|日産|日曹|日本化薬|細井|'
     . '丸和|三井(東圧)?|三菱|明治|理研';
//2024.3.8 (北海)?三共|武田|東ソー 削除、「信越」付きは実態としてはないが残した

// tekiyo.csv と torokku.zip/tekiyo.csv の内容が異なる場合は toroku.zip 解凍
$fupdate |= is_changed("$datdir/$csvzip/$tekiyo", $tekiyo);
if ($fupdate) {
  exec("unzip -o $datdir/$csvzip");
  if ($debug) echo "unzip: $csvzip\n";
  clearstatcache();
}

$mtimeAcis = getLastModified("$datdir/$mainzip/$maindb");

// このスクリプトが acis.zip/acis.db より新しければ $fupdate 設定
$fupdate |= is_modified(convUrl(__FILE__), $mtimeAcis);

// tekiyo.csv が acis.zip/acis.db より新しければデータベース更新
$fupdate |= is_modified(convUrl($tekiyo), $mtimeAcis);
if (!$fupdate) {
  if ($debug) echo "acis: $tekiyo: Not Modified\n";
  return 0;
}

// 登録適用部 m_tekiyo 作成
$time = -microtime(true);
$fh = fopen($tekiyo, 'r');
$heads = explode(',', preg_replace('/(有効)?成分\(?([1-5])\)?(を含む農薬の)?(総使用回数)/', '$4$2', str_replace("'", '', rtrim(mb_convert_encoding(fgets($fh), 'UTF-8', 'SJIS-win')))));
$i = 0;
unset($cols);
foreach($heads as $head) $cols[] = $hdtekiyo[$head] ? $hdtekiyo[$head] : 'dummy'.$i++;
$collist = implode(',', $cols);
$sql = <<<ACIS1
create temp table tekiyo ($collist);
begin transaction;
ACIS1;
$db->exec($sql);
while ($line = rtrim(mb_convert_encoding(fgets($fh), 'UTF-8', 'SJIS-win'))) {
//  $db->exec("insert into tekiyo ($collist) values ($line);");
  $db->exec("insert into tekiyo values ($line);");
}
$sql = <<<ACIS2
drop table if exists m_tekiyo;
drop index if exists idxTekiyo;
create table m_tekiyo (bango integer,sakumotsu varchar,basho varchar,byochu varchar,mokuteki varchar,baisu varchar,ekiryo varchar,jiki varchar,kaisu varchar,hoho varchar,jikan varchar,ondo varchar,dojo varchar,chitai varchar,tekiyaku varchar,kaisu1 varchar,kaisu2 varchar,kaisu3 varchar,kaisu4 varchar,kaisu5 varchar);
insert into m_tekiyo (bango,sakumotsu,basho,byochu,mokuteki,baisu,ekiryo,jiki,kaisu,hoho,jikan,ondo,dojo,chitai,tekiyaku,kaisu1,kaisu2,kaisu3,kaisu4,kaisu5) select bango,sakumotsu,basho,byochu,mokuteki,baisu,ekiryo,jiki,kaisu,hoho,jikan,ondo,dojo,chitai,tekiyaku,kaisu1,kaisu2,kaisu3,kaisu4,kaisu5 from tekiyo;
drop table tekiyo;
create index idxTekiyo on m_tekiyo (bango,sakumotsu,byochu,mokuteki);
commit;
ACIS2;
$res = $db->exec($sql);
fclose($fh);
$time += microtime(true);
if ($res === false) {
  $err = $db->errorInfo();
  logputs('acis m_tekiyo', $err[2]);
  echo "acis: m_tekiyo: $err[2]\n";
  return 0;
} else {
  if ($debug) echo "acis: m_tekiyo: Created $time\n";
}

unset($cols);
$time = -microtime(true);
$fh = fopen($kihon, 'r');
$i = 0;
$heads = explode(',', str_replace("'", '', rtrim(mb_convert_encoding(fgets($fh), 'UTF-8', 'SJIS-win'))));
//foreach($heads as $head) $cols[] = $hdkihon[trim($head, "'")];
foreach($heads as $head) $cols[] = $hdkihon[$head] ? $hdkihon[$head] : 'dummy'.$i++;
$collist = implode(',', $cols);
$sql = <<<ACIS3
create temp table kihon ($collist);
begin transaction;
ACIS3;
$db->exec($sql);
while ($line = rtrim(mb_convert_encoding(fgets($fh), 'UTF-8', 'SJIS-win'))) {
//  $db->exec("insert into kihon ($collist) values ($line);");
  $db->exec("insert into kihon values ($line);");
}
$sql = <<<ACIS4
drop table if exists seibun;
drop index if exists idxSeibun;
create table seibun (bango integer,ippanmei varchar,nodo varchar,seibun varchar);
insert into seibun (bango,ippanmei,nodo,seibun) select bango,ippanmei,nodo,seibun from kihon;
create index idxSeibun on seibun (bango,ippanmei,seibun);
commit;
ACIS4;
$res = $db->exec($sql);
fclose($fh);
$time += microtime(true);
if ($res === false) {
  $err = $db->errorInfo();
  logputs('acis seibun', $err[2]);
  echo "acis: seibun: $err[2]\n";
  return 0;
} else {
  if ($debug) echo "acis: seibun: Created $time\n";
}

// 登録基本部 m_kihon 作成
$time = -microtime(true);
$sql = <<<ACIS5
begin transaction;
--create temp table kihon3 as select bango,shurui,yoto,zaikei,replace(re_replace('/([1-9])(?=/|$)',torokubi,'/0$1'), '/', '.') as torokubi,replace(re_replace('/([1-9])(?=/|$)',kigen,'/0$1'), '/', '.') as kigen from kihon group by bango;
create temp table kihon3 as select bango,shurui,yoto,zaikei,replace(re_replace('/([1-9])(?=/|$)',torokubi,'/0$1'), '/', '.') as torokubi, null as kigen from kihon group by bango;
create temp table kihon2 as select bango,kongo,meisho,meisho as tsusho,ryakusho,re_replace('(燻|くん)(蒸|煙)剤|エアゾル|ペースト剤|マイクロカプセル剤|乳剤|塗布剤|水和剤|水溶剤|油剤|液剤|粉剤|粉末|(粉)?粒剤|複合肥料|(?<!合|着)剤', shurui, '') as seibun1,null as keito1,null as seibun2,null as keito2,null as seibun3,null as keito3,null as seibun4,null as keito4,null as seibun5,null as keito5,0 as nkoka,null as koka from kihon group by bango;
drop table kihon;
update kihon2 set seibun1 = replace(seibun1, '水和', '') where seibun1 like '%水和硫黄%';
update kihon2 set seibun1 = replace(seibun1, '貯穀用', '') where seibun1 like '%貯穀用%';
update kihon2 set seibun5 = explode('・', seibun1, 5) where kongo >= 5;
update kihon2 set seibun4 = explode('・', seibun1, 4) where kongo >= 4;
update kihon2 set seibun3 = explode('・', seibun1, 3) where kongo >= 3;
update kihon2 set seibun2 = explode('・', seibun1, 2), seibun1 = explode('・', seibun1, 1) where kongo >= 2;
update kihon2 set seibun1 = null, koka = '展着' where seibun1 like '%展着剤';
update kihon2 set seibun1 = null, koka = null where seibun1 like '%粘着剤';
update kihon2 set keito1 = (select keito from bunrui where seibun = seibun1);
update kihon2 set keito2 = (select keito from bunrui where seibun = seibun2) where kongo >= 2;
update kihon2 set keito3 = (select keito from bunrui where seibun = seibun3) where kongo >= 3;
update kihon2 set keito4 = (select keito from bunrui where seibun = seibun4) where kongo >= 4;
update kihon2 set keito5 = (select keito from bunrui where seibun = seibun5) where kongo >= 5;
--update kihon2 set seibun1 = (select ippanmei from seibun where seibun.bango = kihon2.bango),keito1 = (select keito from bunrui where seibun = seibun1) where keito1 is null and koka is null;
update kihon2 set seibun1 = (select ippanmei from seibun where seibun.bango = kihon2.bango) where keito1 is null and koka is null;
update kihon2 set keito1 = (select keito from bunrui where seibun = seibun1) where keito1 is null and koka is null;
update kihon2 set nkoka = (select kokaid from bunrui where seibun = seibun1) where seibun1 is not null;
update kihon2 set nkoka = nkoka | (select kokaid from bunrui where seibun = seibun2) where kongo >= 2;
update kihon2 set nkoka = nkoka | (select kokaid from bunrui where seibun = seibun3) where kongo >= 3;
update kihon2 set nkoka = nkoka | (select kokaid from bunrui where seibun = seibun4) where kongo >= 4;
update kihon2 set nkoka = nkoka | (select kokaid from bunrui where seibun = seibun5) where kongo >= 5;
update kihon2 set koka = concat('・',(select koka from koka where kokaid = nkoka & 3),(select koka from koka where kokaid = nkoka & 4),(select koka from koka where kokaid = nkoka & 8),(select koka from koka where kokaid = nkoka & 16),(select koka from koka where kokaid = nkoka & 32),(select koka from koka where kokaid = nkoka & 64),(select koka from koka where kokaid = nkoka & 128),(select koka from koka where kokaid = nkoka & 256),(select koka from koka where kokaid = nkoka & 512),(select koka from koka where kokaid = nkoka & 1024),(select koka from koka where kokaid = nkoka & 2048),(select koka from koka where kokaid = nkoka & 4096),(select koka from koka where kokaid = nkoka & 8192),(select koka from koka where kokaid = nkoka & 16384),(select koka from koka where kokaid = nkoka & 32768)) where nkoka > 0;
update kihon2 set tsusho = re_replace('^SG', tsusho, '') where ryakusho like '%レインボー薬品%' and tsusho like 'SG%';
update kihon2 set tsusho = replace(tsusho, 'B', '') where ryakusho like '%バイエル%' and tsusho like '%B%';
update kihon2 set tsusho = replace(tsusho, 'カダン ', '') where tsusho like 'カダン %';
update kihon2 set tsusho = replace(tsusho, 'キング', '') where ryakusho like '%キング%' and tsusho like '%キング%';
update kihon2 set tsusho = re_replace('^アグロス(?!リン|ター)', tsusho, '') where ryakusho like '%住友%' and tsusho like 'アグロス%';
update kihon2 set tsusho = replace(tsusho, 'アグロス', '') where ryakusho like '%アグロテック%' and tsusho like '%アグロス%';
update kihon2 set tsusho = replace(tsusho, 'トモノ', '') where tsusho regexp 'トモノ(?!ール)';
update kihon2 set tsusho = re_replace('^(NS|N(?![CT]))', tsusho, '') where tsusho like 'N%';
update kihon2 set tsusho = re_replace('([\[\"]|「|〔)?クミアイ([\] \-\"]|〕|」|の|・)?', tsusho, '') where tsusho like '%クミアイ%';
update kihon2 set tsusho = '石灰硫黄合剤' where tsusho like '%石灰硫黄合剤%';
update kihon2 set tsusho = re_replace('.*?(粒状)?(石灰窒素.*)', tsusho, '$1$2') where tsusho like '%石灰窒素%';
update kihon2 set tsusho = re_replace('.*?\(?(ボルドー液用|農薬用)\)?(粉末)?(生石灰).*', tsusho, '$1$2$3') where tsusho like '%生石灰%';
update kihon2 set tsusho = '硫酸銅' where tsusho like '%硫酸銅%';
--update kihon2 set tsusho = re_replace('.*(硫酸銅)(.*)', tsusho, '$1$2') where tsusho like '%硫酸銅%';
--update kihon2 set tsusho = '硫酸銅(粉状)' where tsusho like '%粉状丹礬%';
update kihon2 set tsusho = re_replace('([\[\"]|「|〔)?($tm1)([\] \-\"]|〕|」|の|・)?', tsusho, '') where tsusho regexp '$tm1';
update kihon2 set tsusho = re_replace('([\[\"]|「|〔)?($tm2)([\] \-\"]|〕|」|の|・)?', tsusho, '') where tsusho regexp '$tm2';
update kihon2 set tsusho = re_replace('([\[\"]|「|〔)?($tm3)([\] \-\"]|〕|」|の|・)?', tsusho, '') where tsusho regexp '$tm3';
update kihon2 set tsusho = re_replace('「|」', tsusho, '') where tsusho regexp '「|」';
update kihon2 set tsusho = re_replace('\(.+\)', tsusho, '') where tsusho regexp '\(.+\)';
update kihon2 set tsusho = '硫酸銅(粉状)' where meisho like '%硫酸銅(粉%' or meisho like '%粉状丹礬%';
--update kihon2 set tsusho = replace(tsusho, '有機', '') where tsusho like '%有機リドミル%';
--update kihon2 set tsusho = replace(tsusho, 'NT', '殺虫用') where tsusho like '%NT炭酸%';
--update kihon2 set tsusho = 'マシン油乳剤95' where bango in (select bango from seibun where ippanmei = 'マシン油' and nodo = '95.0%');
--update kihon2 set tsusho = 'マシン油乳剤97' where bango in (select bango from seibun where ippanmei = 'マシン油' and nodo = '97.0%');
update kihon2 set tsusho = meisho where meisho like '%バイエル アージラン%';
update kihon2 set tsusho = meisho where meisho like '%ヤシマNCS%';
drop table if exists m_kihon;
drop index if exists idxKihon;
create table m_kihon (bango integer primary key,shurui varchar,meisho varchar,tsusho varchar,ryakusho varchar,kongo integer,yoto varchar,zaikei varchar,torokubi varchar,kigen varchar,koka varchar,seibun1 varchar,keito1 varchar,seibun2 varchar,keito2 varchar,seibun3 varchar,keito3 varchar,seibun4 varchar,keito4 varchar,seibun5 varchar,keito5 varchar);
insert into m_kihon (bango,shurui,meisho,tsusho,ryakusho,kongo,yoto,zaikei,torokubi,kigen,koka,seibun1,keito1,seibun2,keito2,seibun3,keito3,seibun4,keito4,seibun5,keito5) select bango,shurui,meisho,tsusho,ryakusho,kongo,yoto,zaikei,torokubi,kigen,koka,seibun1,keito1,seibun2,keito2,seibun3,keito3,seibun4,keito4,seibun5,keito5 from kihon3 left join kihon2 using(bango);
--create table m_kihon (bango integer primary key,shurui varchar,meisho varchar,tsusho varchar,ryakusho varchar,kongo integer,yoto varchar,zaikei varchar,torokubi varchar,koka varchar,seibun1 varchar,keito1 varchar,seibun2 varchar,keito2 varchar,seibun3 varchar,keito3 varchar,seibun4 varchar,keito4 varchar,seibun5 varchar,keito5 varchar);
--insert into m_kihon (bango,shurui,meisho,tsusho,ryakusho,kongo,yoto,zaikei,torokubi,koka,seibun1,keito1,seibun2,keito2,seibun3,keito3,seibun4,keito4,seibun5,keito5) select bango,shurui,meisho,tsusho,ryakusho,kongo,yoto,zaikei,torokubi,koka,seibun1,keito1,seibun2,keito2,seibun3,keito3,seibun4,keito4,seibun5,keito5 from kihon3 left join kihon2 using(bango);
create index idxKihon on m_kihon (shurui,meisho,tsusho,yoto,zaikei);
drop table kihon2;
drop table kihon3;
commit;
ACIS5;
$res = $db->exec($sql);
$time += microtime(true);
if ($res === false) {
  $err = $db->errorInfo();
  logputs('acis m_kihon', $err[2]);
  echo "acis: m_kihon: $err[2]\n";
  return 0;
} else {
  if ($debug) echo "acis: m_kihon: Created $time\n";
}

//通称読み仮名テーブル作成
$time = -microtime(true);
$sql = <<<ACIS9
begin transaction;
drop table if exists $rubytbl;
drop index if exists idx$rubytbl;
create table $rubytbl (tsusho varchar primary key, ruby varchar);
insert into $rubytbl select distinct tsusho, chemruby(tsusho) from m_kihon;
commit;
create index idx$rubytbl on $rubytbl (tsusho, ruby);
ACIS9;
$res = $db->exec($sql);
$time += microtime(true);
if ($res === false) {
  $err = $db->errorInfo();
  logputs("acis $rubytbl", $err[2]);
  echo "acis: $rubytbl: $err[2]\n";
  return 0;
} else {
  if ($debug) echo "acis: $rubytbl: Created $time\n";
}

//通称読み仮名テーブル作成クエリー
$sql =<<<ACIS10
--/d
/* 通称読み仮名テーブル */
begin transaction;
drop table if exists $rubytbl;
drop index if exists idx$rubytbl;
create table $rubytbl (tsusho varchar primary key, ruby varchar);\n
ACIS10;
$res = $db->query("select * from $rubytbl");
while ($rec = $res->fetch(PDO::FETCH_NUM)) {
  $sql .= sprintf("insert into $rubytbl values('%s', '%s');\n", $rec[0], $rec[1]);
}
dbCloseStatement($res);
$sql .= <<<ACIS11
commit;
create index idx$rubytbl on $rubytbl (tsusho, ruby);
ACIS11;
$res = file_put_contents("$datdir/$rubyfile", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);
if ($res === false) {
  if ($debug) echo "$rubyfile Failed\n";
  return 0;
}

//薬剤検索用キーワードリストテーブル作成
$time = -microtime(true);
$sql = <<<ACIS6
begin transaction;
drop view if exists v_seibun;
create temp view v_seibun as select
  bango,concat(', ',ippanmei) as ippanmei,concat(', ', a.seibun) as seibun,concat(', ',iso) as iso,concat(', ',b.keito) as keito,concat(', ',c.keito) as racgroup,
  concat(', ',mid) as rac,concat(', ',sayoten) as sayoten,concat(', ',sayokiko) as sayokiko,concat(', ',fgroup) as fgroup
from seibun as a left join iso using(ippanmei) left join bunrui as b on a.ippanmei = b.seibun left join rac as c using(ippanmei) group by bango;
drop table if exists $kwtbl;
drop index if exists idx$kwtbl;
create table $kwtbl (bango integer primary key, kws varchar);
insert into $kwtbl select bango,concat(';',meisho,shurui,ryakusho,koka,ippanmei,seibun,iso,keito,racgroup,rac,sayoten,sayokiko,fgroup,chemruby(shurui),fuzzy(chemruby(shurui)),chemruby(tsusho),fuzzy(chemruby(tsusho)))
from m_kihon left join v_seibun using(bango);
commit;
create index idx$kwtbl on $kwtbl (kws);
drop view if exists v_seibun;\n
ACIS6;
$res = $db->exec($sql);
$time += microtime(true);
if ($res === false) {
  $err = $db->errorInfo();
  logputs("acis $kwtbl", $err[2]);
  echo "acis: $kwtbl: $err[2]\n";
  return 0;
} else {
  if ($debug) echo "acis: $kwtbl: Created $time\n";
}

//薬剤検索用キーワードリストテーブル作成クエリー
$sql =<<<ACIS7
--/d
/* 薬剤検索用キーワードリストテーブル */
begin transaction;
drop table if exists $kwtbl;
drop index if exists idx$kwtbl;
create table $kwtbl (bango integer primary key, kws varchar);\n
ACIS7;
$res = $db->query("select * from $kwtbl");
while ($rec = $res->fetch(PDO::FETCH_NUM)) {
  $sql .= sprintf("insert into $kwtbl values(%d, '%s');\n", $rec[0], $rec[1]);
}
dbCloseStatement($res);
$sql .= <<<ACIS8
commit;
create index idx$kwtbl on $kwtbl (kws);
ACIS8;
$res = file_put_contents("$datdir/$kwfile", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);
if ($res === false) {
  if ($debug) echo "$kwfile Failed\n";
  return 0;
}

return 1;
?>
