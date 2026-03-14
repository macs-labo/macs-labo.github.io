<?php
require_once 'inc.sakumotsu.php';

// m_tekiyo テーブルが更新されていれば「農水省農薬登録情報提供システム／作物名で探す」ページを読み込んで sakumotsu.txt 作成
$mtime = getLastModified("$chkbase/$massql");
$fupdate |= is_modified(convUrl(__FILE__), $mtime);
$fupdate |= is_modified("$chkbase/spec.beppyo1.txt", $mtime);
$fupdate |= is_modified("$chkbase/spec.cvo.utf8.txt", $mtime);
if (!$finfo && !$fupdate) {
  if ($debug)  echo "acis: sakumotsu: Not Modified\n";
  return 0;
}
$time = -microtime(true);
$s = file_get_contents("$dopage/$dofile/");
if ($s === false) {
  logputs('acis: sakumotsu', "Cannot Load $dofile");
  if ($debug)  echo "acis: sakumotsu: Cannot Load $dofile";
  return 0;
}

/* 作物名称絞込み HTML -> SQL 変換 */
// 前処理
$date = date('Y.m.d');
$s = preg_replace('/.*const cropsListJson ?= ?\[/s', '[', $s);
$s = preg_replace('/(.*?\]);.*/s', '$1', $s);
$s = mb_convert_kana($s, 'asKV', 'utf8');
$cropslist = json_decode($s, true);

$query = <<<SAKU1
--/d
/* $date 現在の農水省農薬登録情報提供システムに基づく作物 ID テンポラリマスター (2024.8.11) */
drop table if exists $fidtbl;
create temp table $fidtbl (toroku integer, class integer, level integer, classid varchar, idsaku varchar, sakumotsu varchar primary key, ruby varchar, class0 varchar, class1 varchar, class2 varchar, class3 varchar, cropid varchar, branch varchar);
begin transaction;\n
SAKU1;

foreach($cropslist as $crop) {
  $code = str_split($crop['classCropsName'], 2);
  $query .= sprintf("insert or ignore into $fidtbl values(0, 0, 0, null, null, '%s', '%s', '%s', '%s', '%s', '%s', '%s%s', '%s');\n", $crop['cropsName'], _ruby($crop['cropsName']), $code[0], $code[1], $code[2], $code[3], $code[5], $code[6], $crop['branch']);
}

$query .= <<<SAKU2
--class, level の設定
update $fidtbl set class = 5, level = 4 where branch != '0000';
update $fidtbl set class = 4, level = 3 where cropid != '0000' and class = 0;
update $fidtbl set level = 4 where class3 != '00' and class = 4;
update $fidtbl set class = 3, level = 3 where class3 != '00' and class = 0;
update $fidtbl set class = 2, level = 2 where class2 != '00' and class = 0;
update $fidtbl set class = 1, level = 1 where class1 != '00' and class = 0;
--適用作物名一覧作成
drop table if exists t_tekiyosaku;
create temp table t_tekiyosaku as select distinct sakumotsu from m_tekiyo;
--適用に存在して農水省農薬登録情報提供システムに存在しない作物名の追加
insert into $fidtbl select 1,0,0,NULL,NULL,sakumotsu,toruby(sakumotsu),NULL,NULL,NULL,NULL,NULL,NULL from t_tekiyosaku where sakumotsu not in (select sakumotsu from $fidtbl);
--適用に存在する作物名の toroku を 1 に設定
update $fidtbl set toroku = 1 where sakumotsu in (select sakumotsu from t_tekiyosaku);
drop table if exists t_tekiyosaku;
--「落葉果樹」例外処理
update $fidtbl set class = 4, level = 3, branch = '0000', cropid = printf('%04d', (select max(cropid)+1 from $fidtbl as b where b.class0||b.class1||b.class2||b.class3 = $fidtbl.class0||$fidtbl.class1||$fidtbl.class2||$fidtbl.class3)) where sakumotsu = '落葉果樹' and class = 5;
update $fidtbl set cropid = (select cropid from $fidtbl where sakumotsu = '落葉果樹') where sakumotsu like '落葉果樹(%' and class = 5;
--「ぶどう」例外処理
--update $fidtbl set class2 = '02', cropid = '0000' where sakumotsu like '%ぶどう%' and class0||class1 = (select class0||class1 from $fidtbl where sakumotsu = 'ぶどう');
update $fidtbl set class2 = '02', cropid = '0000' where sakumotsu like '%ぶどう%' and sakumotsu not regexp '果樹(類)?\(';
update $fidtbl set class = 2, level = 2 where sakumotsu = 'ぶどう';
update $fidtbl set class = 3, level = 3, class3 = '01', branch = '0000' where sakumotsu = '小粒種ぶどう';
update $fidtbl set class = 3, level = 3, class3 = '01', branch = null where sakumotsu like '小粒種ぶどう(%';
update $fidtbl set class = 4, level = 4, class3 = '01', cropid = '0001', branch = '0000' where sakumotsu = 'ぶどう(デラウェア)';
update $fidtbl set class3 = '01', cropid = '0001', branch = NULL where regexp('^ぶどう\(デラウェア', sakumotsu, 'cwkv') and class = 5;
update $fidtbl set class = 3, level = 3, class3 = '02', branch = '0000' where sakumotsu = '大粒種ぶどう';
update $fidtbl set class = 3, level = 3, class3 = '02', branch = null where sakumotsu like '大粒種ぶどう(%';
insert or ignore into $fidtbl select 3,0,0,NULL,NULL,'ぶどう(巨峰系4倍体品種)','ぶどう(きょほうけい4ばいたいひんしゅ)',class0,class1,class2,class3,NULL,NULL from $fidtbl where sakumotsu = 'ぶどう';
update $fidtbl set class = 4, level = 4, class3 = '02', cropid = '0001', branch = '0000' where sakumotsu = 'ぶどう(巨峰系4倍体品種)' or sakumotsu like 'ぶどう(巨峰系4倍体品種)%';
update $fidtbl set class3 = '02', cropid = '0001', branch = NULL where sakumotsu regexp 'ぶどう\((巨峰|高尾|ピオーネ|あづましずく|ふくしずく|サニールージュ|ルビーロマン|ハニービーナス)' and class = 5;
insert or ignore into $fidtbl select 3,0,0,NULL,NULL,'ぶどう(2倍体米国系品種)','ぶどう(2ばいたいべいこくけいひんしゅ)',class0,class1,class2,class3,NULL,NULL from $fidtbl where sakumotsu = 'ぶどう';
update $fidtbl set class = 4, level = 4, class3 = '02', cropid = '0002', branch = '0000' where sakumotsu = 'ぶどう(2倍体米国系品種)';
update $fidtbl set class3 = '02', cropid = '0002', branch = NULL where sakumotsu regexp 'ぶどう\((2倍体米国|キャンベルアーリー|マスカット・ベリーA|キャンベルアーリー|ヒムロッドシードレス)' and class = 5;
insert or ignore into $fidtbl select 3,0,0,NULL,NULL,'ぶどう(2倍体欧州系品種)','ぶどう(2ばいたいおうしゅうけいひんしゅ)',class0,class1,class2,class3,NULL,NULL from $fidtbl where sakumotsu = 'ぶどう';
update $fidtbl set class = 4, level = 4, class3 = '02', cropid = '0003', branch = '0000' where sakumotsu = 'ぶどう(2倍体欧州系品種)';
update $fidtbl set class3 = '02', cropid = '0003', branch = NULL where sakumotsu regexp 'ぶどう\((2倍体欧州|ヒロハンブルグ|マスカット・オブ|シャインマスカット)' and class = 5;
insert or ignore into $fidtbl select 3,0,0,NULL,NULL,'ぶどう(3倍体品種)','ぶどう(3ばいたいひんしゅ)',class0,class1,class2,class3,NULL,NULL from $fidtbl where sakumotsu = 'ぶどう';
update $fidtbl set class = 4, level = 4, class3 = '02', cropid = '0004', branch = '0000' where sakumotsu = 'ぶどう(3倍体品種)';
update $fidtbl set class3 = '02', cropid = '0004', branch = NULL where sakumotsu regexp 'ぶどう\((3倍体|ハニーシードレス|キングデラ|ナガノパープル|BKシードレス|大粒系デラウェア|ポンタ)' and class = 5;
--「長門ユズキチ」例外処理
update $fidtbl set toroku = 2, sakumotsu = '長門ユズキチ', ruby = 'ながとゆずきち' where sakumotsu = '長門ユズキチ(無核)' and class = 4;
insert or ignore into $fidtbl values (1, 5, 4, NULL, NULL, '長門ユズキチ(無核)', 'ながとゆずきち(むかく)', NULL, NULL, NULL, NULL, NULL, NULL);
--「紅まどんな」を「愛媛果試第28号」の条件付き作物名に変更(2024.8.8追加 2024.4.1改正適用農作物表に伴う修正)
update $fidtbl set class = 5, level = 4, branch = NULL, cropid = (select cropid from $fidtbl where sakumotsu = '愛媛果試第28号') where sakumotsu = '紅まどんな' and class = 4;
insert or ignore into $fidtbl values (1, 5, 4, NULL, NULL, '長門ユズキチ(無核)', 'ながとゆずきち(むかく)', NULL, NULL, NULL, NULL, NULL, NULL);
--「なし(栽培育成時の非収穫年樹)」の例外処理
update $fidtbl set class = 5, level = 4, class0 = NULL, class1 = NULL, class2 = NULL, class3 = NULL, cropid = NULL, branch = NULL where sakumotsu = 'なし(栽培育成時の非収穫年樹)';
--「オリーブ(交互結実栽培の非収穫年樹)」の例外処理
update $fidtbl set class = 5, level = 4, class0 = NULL, class1 = NULL, class2 = NULL, class3 = NULL, cropid = NULL, branch = NULL where sakumotsu = 'オリーブ(交互結実栽培の非収穫年樹)';
--「あぶらな科野菜」例外処理
update $fidtbl set class = 4, level = 3, branch = '0000', cropid = printf('%04d', (select max(cropid)+1 from $fidtbl as b where b.class0||b.class1||b.class2||b.class3 = $fidtbl.class0||$fidtbl.class1||$fidtbl.class2||$fidtbl.class3)) where sakumotsu = 'あぶらな科野菜' and class = 5;
update $fidtbl set cropid = (select cropid from $fidtbl where sakumotsu = 'あぶらな科野菜') where sakumotsu like 'あぶらな科野菜(%' and class = 5;
--「せり(水耕栽培)」を「せり」の条件付き作物名に変更
update $fidtbl set class = 5, level = 4, branch = NULL, cropid = (select cropid from $fidtbl where sakumotsu = 'せり') where sakumotsu = 'せり(水耕栽培)' and class = 4;
--「いね科細粒雑穀類」例外処理
update $fidtbl set sakumotsu = 'イネ科細粒雑穀類' where sakumotsu = 'いね科細粒雑穀類';
--「まめ科牧草等」を「まめ科牧草」の条件付き作物名に変更
update $fidtbl set class = 5, level = 4, branch = NULL, cropid = (select cropid from $fidtbl where sakumotsu = 'まめ科牧草') where sakumotsu = 'まめ科牧草等' and class = 4;
--「ソテツ」例外処理
insert into $fidtbl select 0, 3, 4, NULL, NULL, 'そてつ', 'そてつ', class0, class1, class2, class3, cropid, branch from $fidtbl as b where b.sakumotsu = 'ソテツ';
update $fidtbl set class = 5, level = 4, branch = NULL where sakumotsu = 'ソテツ';
--「大作物群のない食用作物」を「その他の食用作物」に変更
update $fidtbl set sakumotsu = 'その他の食用作物', ruby = 'そのたのしょくようさくもつ' where sakumotsu = '大作物群のない食用作物';
--「樹木等」の例外処理 --適用地帯の樹木等を樹木類に移動
update $fidtbl set (class, level, class0, class1, class2, class3, cropid, branch) = (select 5, 4, class0, class1, class2, class3, cropid, NULL from $fidtbl where sakumotsu = '樹木類') where sakumotsu = '樹木等';
--「適用地帯」の例外処理
delete from $fidtbl where sakumotsu = '適用地帯';
update $fidtbl set toroku = 0, sakumotsu = '適用地帯等', ruby = 'てきようちたいとう' where sakumotsu = '適用地帯その他';
insert into $fidtbl select 0, 4, 3, NULL, NULL, '開墾後に栽培する作物', 'かいこんごにさいばいするさくもつ', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '開墾後に栽培する農作物等';
update $fidtbl set cropid = (select printf('%04d', max(cropid)+1) from $fidtbl as b where b.class0||b.class1||b.class2||b.class3 = $fidtbl.class0||$fidtbl.class1||$fidtbl.class2||$fidtbl.class3) where sakumotsu = '開墾後に栽培する作物';
update $fidtbl set cropid = (select cropid from $fidtbl where sakumotsu = '開墾後に栽培する作物') where sakumotsu like '開墾後に栽培する%' and class = 5;
--「水田作物、畑作物等」の例外処理
update $fidtbl set cropid = printf('%04d', (select max(cropid)+1 from $fidtbl where class0||class1||class2||class3 like (select class0||class1||class2||class3||'%' from $fidtbl where sakumotsu = '適用地帯等'))) where class0||class1 = '9104';
update $fidtbl set (class0, class1, class2, class3) = (select class0, class1, class2, class3 from $fidtbl where sakumotsu = '適用地帯等') where class0||class1 = '9104';
update $fidtbl set (class0, class1, class2, class3, cropid, branch) = (select class0, class1, class2, class3, cropid, NULL from $fidtbl where sakumotsu = '水田作物、畑作物等') where sakumotsu = '畑作物';
--「水田作物・畑作物等」は展着剤の登録なので、作物名を「水田作物・畑作物」に変更
insert into $fidtbl select 0, 4, 3, NULL, NULL, '水田作物・畑作物', 'すいでんさくもつ・はたさくもつ', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '水田作物';
--「くん蒸等」例外処理
--insert into $fidtbl select 0, 4, 3, NULL, NULL, 'その他穀類', 'そのたこくるい', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '貯蔵穀物等';
update $fidtbl set (class0, class1, class2, class3, cropid, branch) = (select class0, class1, class2, class3, cropid, NULL from $fidtbl where sakumotsu = '穀物、豆類、飼料') where sakumotsu in ('米、麦、とうもろこし等の穀類', '雑穀、豆類等', '貯蔵穀物等');
update $fidtbl set class = 4, level = 3, branch = '0000' where sakumotsu = '葉たばこ' and class = 5;
--「展着剤等」例外処理
insert into $fidtbl select 0, 4, 3, NULL, NULL, '薬液の付きにくい作物', 'やくえきのつきにくいさくもつ', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '薬液のつきにくい農作物等';
insert into $fidtbl select 0, 4, 3, NULL, NULL, '薬液の付きやすい作物', 'やくえきのつきやすいさくもつ', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '比較的展着の容易な作物';
update $fidtbl set (class0, class1, class2, class3, cropid, branch) = (select class0, class1, class2, class3, cropid, NULL from $fidtbl where sakumotsu = '薬液の付きにくい作物') where sakumotsu = '稲、麦、キャベツ、ねぎ等で展着しにくい作物';
update $fidtbl set cropid = printf('%04d', (select max(cropid)+1 from $fidtbl where class0||class1||class2||class3 like (select class0||class1||class2||class3||'%' from $fidtbl where sakumotsu = '展着剤等'))) where class0||class1 = '9103';
update $fidtbl set (class0, class1, class2, class3) = (select class0, class1, class2, class3 from $fidtbl where sakumotsu = '展着剤等') where class0||class1 = '9103';
update $fidtbl set class = 4, level = 3 where sakumotsu = '一般畑作物';
update $fidtbl set (class0, class1, class2, class3) = (select class0, class1, class2, class3 from $fidtbl where sakumotsu = '展着剤等') where sakumotsu = '水田作物、畑作物等';
update $fidtbl set cropid = printf('%04d', (select max(cropid)+1 from $fidtbl where class0||class1||class2||class3 like (select class0||class1||class2||class3||'%' from $fidtbl where sakumotsu = '展着剤等'))) where sakumotsu = '水田作物、畑作物等';
update $fidtbl set class = 4, level = 3 where sakumotsu = '水田作物、畑作物等';
update $fidtbl set cropid = printf('%04d', (select max(cropid)+1 from $fidtbl where class0||class1||class2||class3 like (select class0||class1||class2||class3||'%' from $fidtbl where sakumotsu = '展着剤等'))) where sakumotsu like '%登録内容%';
insert into $fidtbl select 0, 4, 3, NULL, NULL, '除草剤用', 'じょそうざいよう', class0, class1, class2, class3, cropid, '0000' from $fidtbl as b where b.sakumotsu = '除草剤の登録内容の作物';
--農水省農薬登録情報提供システムに ID が存在しない条件付き作物名の例外処理
--update $fidtbl set (class0, class1, class2, class3, cropid) = (select class0, class1, class2, class3, cropid from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, '')) where class0 is null and sakumotsu regexp '[\)\]]$';
update $fidtbl set
  class0 = (select class0 from $fidtbl as b where b.sakumotsu = re_replace('\([^\(]+\)$', replace($fidtbl.sakumotsu, '、ただし、', ')('), '')),
  class1 = (select class1 from $fidtbl as b where b.sakumotsu = re_replace('\([^\(]+\)$', replace($fidtbl.sakumotsu, '、ただし、', ')('), '')),
  class2 = (select class2 from $fidtbl as b where b.sakumotsu = re_replace('\([^\(]+\)$', replace($fidtbl.sakumotsu, '、ただし、', ')('), '')),
  class3 = (select class3 from $fidtbl as b where b.sakumotsu = re_replace('\([^\(]+\)$', replace($fidtbl.sakumotsu, '、ただし、', ')('), '')),
  cropid = (select cropid from $fidtbl as b where b.sakumotsu = re_replace('\([^\(]+\)$', replace($fidtbl.sakumotsu, '、ただし、', ')('), ''))
where class0 is null and sakumotsu like '%、ただし、%';
update $fidtbl set
  class0 = (select class0 from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, '')),
  class1 = (select class1 from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, '')),
  class2 = (select class2 from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, '')),
  class3 = (select class3 from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, '')),
  cropid = (select cropid from $fidtbl as b where b.sakumotsu = re_replace('\(.+?[\)\]]$', $fidtbl.sakumotsu, ''))
where class0 is null and sakumotsu regexp '[\)\]]$';
update $fidtbl set class = 5, level = 4, branch = printf('%04d', (select max(branch)+1 from $fidtbl as b where b.class0||b.class1||b.class2||b.class3||b.cropid = $fidtbl.class0||$fidtbl.class1||$fidtbl.class2||$fidtbl.class3||$fidtbl.cropid)) where class0 is not null and branch is null;
--条件付き作物名のみ存在する作物名・分類名の toroku に 3 をセット
update $fidtbl set toroku = 3 where class < 5 and toroku = 0 and class0||class1||class2||class3||cropid||'0000' in (select distinct class0||class1||class2||class3||cropid||'0000' from $fidtbl where class = 5 and toroku = 1);
--適用に存在しない分類名の toroku に 2 をセット
update $fidtbl set toroku = 2 where class < 4 and toroku = 0;
--農水省農薬登録情報提供システムに存在して適用に存在しない作物名の削除
delete from $fidtbl where toroku = 0;
--classid の設定
update $fidtbl set classid = class0||class1||class2||class3;
--idsaku の設定
update $fidtbl set idsaku = classid||cropid||branch;
commit;\n
SAKU2;

//農水省システムの作物 ID に異常があった場合は、作物マスターを作成せずにエラー終了 2025.8.12
$db->sqliteCreateFunction('toruby', '_ruby', 1);
$res = $db->exec($query);
if ($res === false) {
  $err = $db->errorInfo();
  logputs("acis: sakumotsu", $err[2], 'Cron DB Error');
  if ($debug) echo "acis: sakumotsu: $err[2]\n";
  return 0;
}
$sql = "select * from $fidtbl where idsaku is null or length(idsaku) != 16 or idsaku = 0";
$res = $db->query("select * from $fidtbl where idsaku is null or length(idsaku) != 16 or idsaku = 0");
if ($res === false) {
  $err = $db->errorInfo();
  logputs("acis: sakumotsu", $err[2], 'Cron DB Error');
  if ($debug) echo "acis: sakumotsu: $err[2]\n";
  return 0;
}
$row = $res->fetch();
dbCloseStatement($res);
if ($row !== false) {
  $err = "$row[4] $row[5]";
  logputs("acis: sakumotsu id", $err, 'Cron DB Error');
  if ($debug) echo "acis: sakumotsu id error: $err\n";
  return 0;
}

$query = <<<SAKU3
/* 2021.1.14 2消安第4246号課長通知に基づく作物 ID テンポラリマスター (2021.5.12) */
begin transaction;
drop table if exists $midtbl;
create temp table $midtbl as select class as cls, 3 as lvl, null as fidsaku, null as lgid, 0 as mgid, 0 as sgid, 0 as tgid, 0 as cid, 0 as ccid, lg, mg, sg, tg, sakumotsu, betsumei, shukakubui, ruby as yomi from xtekiyosakumotsu;
update $midtbl set lvl = cls where cls < 3;
--作物ID設定
update $midtbl set cid = (select max(cid) + 1 from $midtbl as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg and b.sg = $midtbl.sg and b.tg = $midtbl.tg) where cls = 4 and tg is not null;
update $midtbl set cid = (select max(cid) + 1 from $midtbl as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg and b.sg = $midtbl.sg group by b.tg) where cls = 4 and cid = 0 and sg is not null;
update $midtbl set cid = (select max(cid) + 1 from $midtbl as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg group by b.sg) where cls = 4 and cid = 0 and mg is not null;
update $midtbl set cid = (select max(cid) + 1 from $midtbl as b where b.lg = $midtbl.lg group by b.mg) where cls = 4 and cid = 0;
--細作物群ID設定
drop table if exists tgid;
create temp table tgid as select distinct lg, mg, sg, tg, 0 as id from $midtbl where tg is not null;
update tgid set id = (select max(id) + 1 from tgid as b where b.lg = tgid.lg and b.mg = tgid.mg and b.sg = tgid.sg group by b.sg);
update $midtbl set tgid = (select id from tgid as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg and b.sg = $midtbl.sg and b.tg = $midtbl.tg) where tg is not null;
drop table tgid;
update $midtbl set lvl = 4 where cls = 4 and tg is not null;-- and sg != 'イネ科雑穀類';
--update $midtbl set lvl = 3 where cls = 4 and sg = 'イネ科雑穀類';
--小作物群ID設定
drop table if exists sgid;
create temp table sgid as select distinct lg, mg, sg, 0 as id from $midtbl where sg is not null;
update sgid set id = (select max(id) + 1 from sgid as b where b.lg = sgid.lg and b.mg = sgid.mg group by b.mg);
update $midtbl set sgid = (select id from sgid as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg and b.sg = $midtbl.sg) where sg is not null;
drop table sgid;
--中作物群ID設定
drop table if exists mgid;
create temp table mgid as select distinct lg, mg, 0 as id from $midtbl where mg is not null;
update mgid set id = (select max(id) + 1 from mgid as b where b.lg = mgid.lg group by b.lg);
update $midtbl set mgid = (select id from mgid as b where b.lg = $midtbl.lg and b.mg = $midtbl.mg) where mg is not null;
drop table mgid;
--大作物群ID設定
drop table if exists lgid;
create temp table lgid (lg varchar primary key, id varchar);
insert into lgid values('果樹類', '01');
insert into lgid values('野菜類', '02');
insert into lgid values('穀類', '03');
insert into lgid values('きのこ類', '04');
insert into lgid values('その他の食用作物', '05');
insert into lgid values('飼料作物', '11');
insert into lgid values('薬用作物', '12');
insert into lgid values('花き類・観葉植物', '21');
insert into lgid values('樹木類', '22');
insert into lgid values('芝', '23');
insert into lgid values('その他の非食用作物', '31');
update $midtbl set lgid = (select id from lgid as b where b.lg = $midtbl.lg);
drop table lgid;
commit;

/* 作物マスター (2024.8.10) */
--一時作物マスター $fidtbl に $midtbl を統合
begin transaction;
drop table if exists $tmptbl;
create temp table $tmptbl as select toroku, ifnull(cls, class) as class, ifnull(lvl, level) as level, classid, cropid, branch, printf('%02d%02d%02d%02d', lgid, mgid, sgid, tgid) as grid, cid, lg, mg, sg, tg, sakumotsu, betsumei, shukakubui, concat('、', ruby, yomi) as ruby, null as kamei from $fidtbl left join $midtbl using(sakumotsu) order by idsaku, class;
--適用未登録作物名追加
drop table if exists t_sakunotexists;
create temp table t_sakunotexists as select * from $midtbl where not exists (select sakumotsu from $fidtbl where $midtbl.sakumotsu = $fidtbl.sakumotsu);
insert or ignore into $tmptbl select 0, cls, lvl, '00000000', '0000', '0000', printf('%02d%02d%02d%02d', lgid, mgid, sgid, tgid), cid, lg, mg, sg, tg, sakumotsu, betsumei, shukakubui, yomi, null from t_sakunotexists;
drop table if exists t_sakunotexists;
drop table if exists $midtbl;
drop table if exists $fidtbl;
--適用に存在しない分類名・作物名の toroku に 2 をセット
update $tmptbl set toroku = 2 where toroku = 0;
--適用に存在しない分類名の toroku に 0 をセット
update $tmptbl set toroku = 0 where class < 4 and toroku = 2;
--grid 設定
update $tmptbl set grid = classid where cast(substr(classid, 1, 2) as int) >= 75 and not grid;
update $tmptbl set grid = (select grid from $tmptbl as b where b.branch = '0000' and b.cropid = '0000' and b.classid = $tmptbl.classid) where class = 4 and not grid;
update $tmptbl set grid = (select grid from $tmptbl as b where b.branch = '0000' and b.cropid = $tmptbl.cropid and b.classid = $tmptbl.classid) where class = 5 and not grid;
--cid 設定
update $tmptbl set cid = (select cid from $tmptbl as b where b.branch = '0000' and b.classid = $tmptbl.classid and b.cropid = $tmptbl.cropid) where class = 5;
update $tmptbl set cid = cast(cropid as int) where cid is null;
--lg, mg, sg, tg 設定
update $tmptbl set lg = sakumotsu where class = 0 and lg is null;
update $tmptbl set mg = sakumotsu, lg = (select lg from $tmptbl as b where b.grid = substr($tmptbl.grid, 1, 2)||'000000') where class = 1 and mg is null;
update $tmptbl set sg = sakumotsu, mg = (select mg from $tmptbl as c where c.grid = substr($tmptbl.grid, 1, 4)||'0000'), lg = (select lg from $tmptbl as b where b.grid = substr($tmptbl.grid, 1, 2)||'000000') where class = 2 and sg is null;
update $tmptbl set tg = sakumotsu, sg = (select mg from $tmptbl as d where d.grid = substr($tmptbl.grid, 1, 6)||'00'), mg = (select mg from $tmptbl as c where c.grid = substr($tmptbl.grid, 1, 4)||'0000'), lg = (select lg from $tmptbl as b where b.grid = substr($tmptbl.grid, 1, 2)||'000000') where class = 3 and tg is null;
update $tmptbl set tg = (select tg from $tmptbl as e where e.grid = $tmptbl.grid), sg = (select sg from $tmptbl as d where d.grid = substr($tmptbl.grid, 1, 6)||'00'), mg = (select mg from $tmptbl as c where c.grid = substr($tmptbl.grid, 1, 4)||'0000'), lg = (select lg from $tmptbl as b where b.grid = substr($tmptbl.grid, 1, 2)||'000000') where class in (4, 5) and lg is null;
--class4-5 作物収穫部位設定
update $tmptbl set shukakubui = '茎葉、花蕾等' where sakumotsu = 'あぶらな科野菜';
update $tmptbl set shukakubui = (select shukakubui from $tmptbl as b where b.grid = $tmptbl.grid and b.cid = 0) where lg in ('果樹類', '野菜類', '穀類', 'きのこ類', '飼料作物', '薬用作物') and class = 4 and shukakubui is null;
update $tmptbl set shukakubui = (select shukakubui from $tmptbl as b where b.grid = $tmptbl.grid and b.cid = $tmptbl.cid) where lg in ('果樹類', '野菜類', '穀類', 'きのこ類', '飼料作物', '薬用作物') and class = 5;
--「芝」class5 作物例外処理
update $tmptbl set cid = (select cid from $tmptbl as b where b.sakumotsu like '%'||$tmptbl.sakumotsu) where cropid > 0 and sakumotsu like '芝(%' and sakumotsu not like '%芝)'; 
update $tmptbl set cid = (select cid from $tmptbl where sakumotsu = '西洋芝(ベントグラス)') where sakumotsu = '西洋芝(ベントグラス)(生産圃場)'; 
--芝など class = 4 で branch != '0000' の branch 修正
update $tmptbl set branch = '0000' where class = 4 and branch != '0000';
--class3 分類名が適用に存在しない場合、class4 作物を level3 に昇格
update $tmptbl set level = 3 where substr(grid, 6, 2) != '00' and class = 4 and grid in (select grid from $tmptbl as b where b.class = 3 and b.toroku = 0);
--cvo2 適用作物統合
drop table if exists tcvo;
create temp table tcvo as select sakumotsu, crop, null as ncrop, superior, family, replace(kana, '|', '、') as kana, replace(synonym, '|', '、') as synonym, bunruihyo, seibunhyo, itgl, harvest from cvo2 where sakumotsu is not null;
update tcvo set harvest = '茎葉と根' where harvest = '茎葉|根';
update tcvo set sakumotsu = re_replace('\|(パクチョイ|畑わさび|ゆうがお|チャイブ)$', sakumotsu, '') where sakumotsu regexp '\|(パクチョイ|畑わさび|ゆうがお|チャイブ)$';
update tcvo set sakumotsu = replace(sakumotsu, 'わさび|', '') where sakumotsu like 'わさび|%';
--update tcvo set crop = replace(crop, '('||harvest||')', '') where crop like '%(%' and sakumotsu not like '%(%';
update tcvo set crop = seibunhyo where sakumotsu = 'かぼちゃ' and seibunhyo is not null;
update tcvo set sakumotsu = itgl where sakumotsu = 'なし' and itgl is not null;
update tcvo set crop = crop||'メロン', kana = kana||'めろん' where sakumotsu = 'メロン' and crop not like '%メロン' and crop not like '%ウリ';
update tcvo set bunruihyo = (select bunruihyo from cvo2 as b where b.crop = replace(tcvo.crop, '成熟', '')), seibunhyo = (select seibunhyo from cvo2 as b where b.crop = replace(tcvo.crop, '成熟', '')) where crop like '成熟%';  
update tcvo set bunruihyo = (select bunruihyo from cvo2 as b where b.crop = re_replace('\(.+\)', tcvo.crop, '')), seibunhyo = (select seibunhyo from cvo2 as b where b.crop = re_replace('\(.+\)', tcvo.crop, '')) where crop like '%(%' and sakumotsu is not null and (seibunhyo is null and bunruihyo is null);
update tcvo set synonym = (select replace(synonym, '|', '、') from cvo2 as b where strconv(b.crop) = strconv(tcvo.sakumotsu)) where synonym is null and re_replace('\(.+\)', strconv(crop), '') = strconv(sakumotsu) and strconv(sakumotsu) in (select strconv(crop) from cvo2 where sakumotsu is null and synonym is not null);
update tcvo set kana = concat('、', kana, (select replace(kana, '|', '、') from cvo2 as b where strconv(b.crop) = strconv(tcvo.sakumotsu))) where re_replace('\(.+\)', strconv(crop), '') = strconv(sakumotsu) and strconv(sakumotsu) in (select strconv(crop) from cvo2 where sakumotsu is null);
update tcvo set crop = sakumotsu where sakumotsu = strconv(crop, 'kw');
update tcvo set sakumotsu = sakumotsu||'('||crop||')' where crop != sakumotsu and sakumotsu||'('||crop||')' in (select sakumotsu from $tmptbl);
drop table if exists t_cvo;
create temp table t_cvo as select sakumotsu as saku, concat('、', if(crop != sakumotsu, crop, '')) as crop, concat('、', synonym) as synonym, concat('、', kana) as kana, max(family) as family from tcvo group by sakumotsu;
update $tmptbl set betsumei = concat('、', betsumei, (select concat('、', crop, synonym) from t_cvo where saku = sakumotsu));
update $tmptbl set ruby = concat('、', ruby, (select kana from t_cvo where saku = sakumotsu));
update $tmptbl set ruby = concat('、', ruby, fuzzy(ruby));
update $tmptbl set kamei = (select family from t_cvo where saku = sakumotsu);
drop table if exists tcvo;
drop table if exists t_cvo;
commit;
--パーマネント作物マスター
begin transaction;
drop view if exists $viewgroup;
drop view if exists ${viewgroup}2;
drop view if exists $viewsearch;
drop view if exists ${viewsearch}2;
drop table if exists $mastbl;
create table $mastbl (class integer, level integer, idsaku varchar primary key, xidsaku varchar, toroku integer, shukakubui varchar, sakumotsu varchar unique, betsumei varchar, ruby varchar, kamei varchar, gunmei varchar);
insert or ignore into $mastbl select class, level, printf('%s%04d%s', grid, cid, branch), null, toroku, shukakubui, sakumotsu, betsumei, ruby, kamei, null from $tmptbl order by grid, cid, branch;
--xidsaku の設定
update $mastbl set xidsaku = substr(idsaku, 1, 12)||substr(idsaku, -2) where substr(idsaku, 7, 2) != '00';
update $mastbl set xidsaku = substr(idsaku, 1, 6)||substr(idsaku, -8) where xidsaku is null;
--群横断作物群名追加
update $mastbl set gunmei = (select gunmei from gun_odan as b where b.sakumotsu = $mastbl.sakumotsu) where sakumotsu in (select sakumotsu from gun_odan);
update $mastbl set gunmei = (select gunmei from $mastbl as b where b.idsaku = substr($mastbl.idsaku, 1, 12)||'0000') where class = 5 and substr(idsaku, 1, 12)||'0000' in (select idsaku from $mastbl where sakumotsu in (select sakumotsu from gun_odan));
--作物名・上位作物群・群横断作物全てに登録がない作物の toroku を 0 に設定
update $mastbl set toroku = 0 where toroku = 2 and gunmei is null and (select toroku from $mastbl as b where b.idsaku = substr($mastbl.idsaku, 1, 8)||'00000000') = 0 and (select toroku from $mastbl as c where c.idsaku = substr($mastbl.idsaku, 1, 6)||'0000000000') = 0 and (select toroku from $mastbl as d where d.idsaku = substr($mastbl.idsaku, 1, 4)||'000000000000') = 0 and (select toroku from $mastbl as e where e.idsaku = substr($mastbl.idsaku, 1, 2)||'00000000000000') = 0;
drop table if exists $tmptbl;
--別名、ふりがなインデックス作成
drop index if exists idxMSakumotsu;
create index idxMSakumotsu on $mastbl (betsumei, ruby);
--下位互換作物分類用ビュー
create view $viewgroup as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, idsaku from $mastbl where sakumotsu not like '%除く%' and not((class = 3 and toroku = 0) or (level = 4 and toroku != 1)) order by idsaku;
--新作物分類用ビュー
create view ${viewgroup}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei from $mastbl where sakumotsu not like '%除く%' order by idsaku;
--下位互換作物検索用ビュー
--create view $viewsearch as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, kamei, gunmei), 'kw') as keywords, gunmei = '落葉果樹' as rakuyokaju from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
create view $viewsearch as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, gunmei), 'kw') as keywords, gunmei = '落葉果樹' as rakuyokaju from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
--新作物検索用ビュー
--create view ${viewsearch}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, kamei, gunmei), 'kw') as keywords from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
create view ${viewsearch}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, gunmei), 'kw') as keywords from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く)' order by idsaku;
--下位互換用作物テーブル削除
drop table if exists $dlgtbl;
commit;

/* 作物補助テーブル (2024.10.22) */
begin transaction;
drop table if exists tsakuhojo;
create temp table tsakuhojo as select idsaku, sakumotsu, (select sakumotsu from m_sakumotsu where idsaku = substr(a.idsaku,1,12)||'0000') as shozoku, null as nozoku, null as fukumu from m_sakumotsu as a where class = 5;
/*
insert into tsakuhojo select idsaku, sakumotsu, (select sakumotsu from m_sakumotsu where idsaku = substr(a.idsaku,1,8)||'00000000'), null, null from m_sakumotsu as a
where idsaku regexp (select '('||concat('|',substr(idsaku,1,8))||')[0-9]{4}0000' from m_sakumotsu where class = 3 and toroku = 1) and class = 4;
*/
delete from tsakuhojo where shozoku = 'その他の概念';
update tsakuhojo set shozoku = '展着剤' where idsaku like (select substr(idsaku, 1, 8)||'%' from m_sakumotsu where sakumotsu = '展着剤等');
update tsakuhojo set fukumu = re_replace('作物一般\((.*作物)?', fukumu, '') where fukumu like '%作物一般%';
update tsakuhojo set fukumu = re_replace('(果樹|野菜|麦)', fukumu, '$1類') where fukumu regexp '(果樹|野菜|麦)、';
update tsakuhojo set nozoku = (select '、'||concat('、', re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1'))||'、' from tsakuhojo where shozoku = 'ぶどう(巨峰系4倍体品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%') where shozoku = 'ぶどう(巨峰系4倍体品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%';
update tsakuhojo set nozoku = replace(nozoku, re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1')||'、', '') where  shozoku = 'ぶどう(巨峰系4倍体品種)' and nozoku is not null;
update tsakuhojo set nozoku = (select '、'||concat('、', re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1'))||'、' from tsakuhojo where shozoku = 'ぶどう(2倍体米国系品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%') where shozoku = 'ぶどう(2倍体米国系品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%';
update tsakuhojo set nozoku = replace(nozoku, re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1')||'、', '') where  shozoku = 'ぶどう(2倍体米国系品種)' and nozoku is not null;
update tsakuhojo set nozoku = (select '、'||concat('、', re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1'))||'、' from tsakuhojo where shozoku = 'ぶどう(2倍体欧州系品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%') where shozoku = 'ぶどう(2倍体欧州系品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%';
update tsakuhojo set nozoku = replace(nozoku, re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1')||'、', '') where  shozoku = 'ぶどう(2倍体欧州系品種)' and nozoku is not null;
update tsakuhojo set nozoku = (select '、'||concat('、', re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1'))||'、' from tsakuhojo where shozoku = 'ぶどう(3倍体品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%' and sakumotsu not like '%キングデラ%') where shozoku = 'ぶどう(3倍体品種)' and sakumotsu not like '%品種%' and sakumotsu not like '%除く%';
update tsakuhojo set nozoku = replace(nozoku, re_replace('ぶどう\((.+?)[\)\(].*', sakumotsu, '$1')||'、', '') where  shozoku = 'ぶどう(3倍体品種)' and nozoku is not null;
update tsakuhojo set nozoku = replace(nozoku, '大粒系デラウェア、', '') where  sakumotsu = 'ぶどう(キングデラ)';
--update tsakuhojo set shozoku = 'ぶどう(巨峰)' where sakumotsu regexp 'ぶどう\(巨峰\)?[\(\[]';
--update tsakuhojo set shozoku = re_replace('\[.*\]', sakumotsu, '') where sakumotsu like 'ぶどう%' and sakumotsu not like '%品種%' and sakumotsu like '%栽培]';
update tsakuhojo set shozoku = concat('、', shozoku, 'ぶどう(巨峰)') where sakumotsu regexp 'ぶどう\(巨峰\)?[\(\[]';
update tsakuhojo set shozoku = concat('、', shozoku, re_replace('\[.*\]', sakumotsu, '')) where sakumotsu like 'ぶどう%' and sakumotsu not like '%品種%' and sakumotsu like '%栽培]';
update tsakuhojo set nozoku = '、'||re_replace('.+[\(\[](.+)を除く.*', sakumotsu, '$1')||'、' where sakumotsu like 'ぶどう%' and sakumotsu  like '%除く%';
--update tsakuhojo set nozoku = (select '、'||concat('、', re_replace('メロン\((.+?)\)', sakumotsu, '$1'))||'、' from tsakuhojo where shozoku = 'メロン' and sakumotsu like '%メロン)') where shozoku = 'メロン' and sakumotsu like '%メロン)';
--update tsakuhojo set nozoku = replace(nozoku, re_replace('メロン\((.+?)\)', sakumotsu, '$1')||'、', '') where  shozoku = 'メロン' and nozoku is not null;
update tsakuhojo set nozoku = re_replace('(.*)を除く.*', sakumotsu, '$1') where sakumotsu like '%除く%';  
update tsakuhojo set nozoku = '、'||re_replace('^'||replace(replace(shozoku,'(','(\('),')','\))?')||'[\(\[]', nozoku, '')||'、' where sakumotsu like '%除く%';  
update tsakuhojo set nozoku = re_replace('(?<=、)(晩柑類|なし|うり科野菜|稲|花き類|水田作物)\(', nozoku, '') where nozoku regexp '、(晩柑類|なし|うり科野菜|稲|花き類|水田作物)\(';  
update tsakuhojo set nozoku = re_replace('(?<=、)(.+?)(?=、)', nozoku, 'りんご($1)') where sakumotsu like 'りんご%' and nozoku is not null;
update tsakuhojo set nozoku = re_replace('(?<=、)(.+?)(?=、)', nozoku, 'なし($1)') where sakumotsu like 'なし%' and nozoku is not null;
update tsakuhojo set nozoku = re_replace('(?<=、)(.+?)(?=、)', nozoku, 'ぶどう($1)') where sakumotsu like 'ぶどう%' and nozoku is not null;
update tsakuhojo set nozoku = re_replace('(?<=、)(.+?)(?=、)', nozoku, 'メロン($1)') where sakumotsu like 'メロン%' and nozoku is not null;
update tsakuhojo set nozoku = '、落葉果樹、', fukumu = '、'||re_replace('.+\((.+)\)', sakumotsu, '$1')||'、' where sakumotsu like '落葉果樹(%' and sakumotsu not like '%除く%';
update tsakuhojo set nozoku = replace(nozoku, '、ただし、らっかせいを除く', ''), fukumu = '、らっかせい、' where nozoku like '%豆類(種実、ただし、らっかせいを除く)%'; 
update tsakuhojo set nozoku = re_replace('.*、ただし(?=、)', nozoku, '') where nozoku like '%、ただし、%' and nozoku not like '%除く%';  
update tsakuhojo set nozoku = '、'||replace(sakumotsu, 'を除く', '')||'、' where nozoku like '%栽培、%';
update tsakuhojo set nozoku = re_replace('\(.*?ただし、', nozoku, '(') where nozoku like '%ただし%';
update tsakuhojo set shozoku = re_replace('、ただし、.*除く', sakumotsu, '') where sakumotsu like '%、ただし、%' and shozoku not like '%(%' and sakumotsu not like '%豆類%';
update tsakuhojo set nozoku = ifnullstr(nozoku, '、')||'豆類(未成熟)、' where shozoku like '%、豆類、%';
update tsakuhojo set nozoku = (select concat('、', sakumotsu) from tsakuhojo as b where b.shozoku = tsakuhojo.shozoku and sakumotsu not regexp '除く|[^\)\]]$' group by shozoku) where shozoku in ('果樹類', 'みかん', 'びわ', 'りんご', 'なし', '日本なし', '西洋なし', 'おうとう', 'すもも', 'かき') and sakumotsu not regexp '除く|[^\)\]]$'; 
update tsakuhojo set nozoku = (select concat('、', sakumotsu) from tsakuhojo as b where b.shozoku = tsakuhojo.shozoku and sakumotsu not like '%除く%' group by shozoku) where shozoku in ('野菜類', 'いも類', 'ばれいしょ', 'ごぼう', 'てんさい', 'たまねぎ', 'あさつき', 'ねぎ', '豆類(種実)', 'えだまめ', 'さやいんげん', 'きゅうり', 'とうがん', 'かぼちゃ', 'すいか', 'メロン', 'ピーマン', 'とうがらし類', 'なばな', 'キャベツ', 'はくさい', 'みつば', 'レタス', '非結球レタス', 'ほうれんそう', 'アスパラガス', '食用ぎく', 'いちご') and sakumotsu not like '%除く%'; 
update tsakuhojo set nozoku = (select concat('、', sakumotsu) from tsakuhojo as b where b.shozoku = tsakuhojo.shozoku and sakumotsu not like '%除く%' group by shozoku) where shozoku in ('水稲', '移植水稲', '直播水稲', '麦類', '大麦', '小麦', 'きのこ類', 'しいたけ', 'さとうきび', '茶', '飼料用さとうきび') and sakumotsu not like '%除く%'; 
update tsakuhojo set nozoku = (select concat('、', sakumotsu) from tsakuhojo as b where b.shozoku = tsakuhojo.shozoku and sakumotsu not like '%除く%' group by shozoku) where shozoku in ('花き類・観葉植物', 'きく', 'トルコギキョウ', 'パンジー', 'ペチュニア') and sakumotsu not like '%除く%'; 
update tsakuhojo set nozoku = (select concat('、', sakumotsu) from tsakuhojo as b where b.shozoku = tsakuhojo.shozoku and sakumotsu not like '%除く%' group by shozoku) where shozoku in ('樹木類', 'つつじ類', 'なら類', 'まつ類', 'あじさい', 'えぞまつ', 'さくら', 'すぎ', 'とどまつ', 'ひのき', 'ぶな', 'ポインセチア') and sakumotsu not like '%除く%'; 
update tsakuhojo set nozoku = concat('、', nozoku, replace(sakumotsu, '水耕', '露地')), fukumu = ifnullstr(fukumu, '、')||replace(sakumotsu, '水耕', '施設')||'、' where sakumotsu like '%水耕栽培%' and sakumotsu not regexp '水耕栽培.*?を除く';
update tsakuhojo set nozoku = concat('、', nozoku, replace(sakumotsu, '施設', '露地')) where sakumotsu like '%施設栽培%' and sakumotsu not like '%除く%';
update tsakuhojo set fukumu = ifnullstr(fukumu, '、')||replace(sakumotsu, '施設', '水耕')||'、' where sakumotsu like '%施設栽培%' and sakumotsu not like '%除く%' and nozoku not like '%水耕栽培%';
update tsakuhojo set nozoku = concat('、', nozoku, replace(shozoku, '施設', '露地')), fukumu = ifnullstr(fukumu, '、')||replace(shozoku, '施設', '水耕')||'、' where shozoku like '%施設栽培%' and nozoku not like '%水耕栽培%';
update tsakuhojo set nozoku = concat('、', nozoku, replace(sakumotsu, '露地', '施設'), replace(sakumotsu, '露地', '水耕')) where sakumotsu like '%(露地栽培)%';
update tsakuhojo set nozoku = concat('、', nozoku, shozoku||'(施設栽培)', shozoku||'(水耕栽培)') where sakumotsu like '%露地栽培%' and nozoku is null;
update tsakuhojo set nozoku = nozoku||'、' where nozoku not like '%、'; 
update tsakuhojo set nozoku = replace('、'||nozoku, '、'||sakumotsu||'、', '、') where nozoku not like '、%'; 
update tsakuhojo set nozoku = replace(nozoku, '、すもも(貴陽)、', '、') where sakumotsu = 'すもも(中晩生種)'; 
update tsakuhojo set nozoku = replace(nozoku, '、すもも(中晩生種)、','、') where sakumotsu = 'すもも(貴陽)'; 
update tsakuhojo set nozoku = replace(nozoku, '、水稲(箱育苗)、', '、') where sakumotsu = '稲(箱育苗)'; 
update tsakuhojo set nozoku = replace(nozoku, '、稲(箱育苗)、', '、') where sakumotsu = '水稲(箱育苗)'; 
update tsakuhojo set nozoku = replace(nozoku, '、稲(湛水直播)、', '、') where sakumotsu = '湛水直播水稲'; 
update tsakuhojo set nozoku = replace(nozoku, '、湛水直播水稲、', '、') where sakumotsu = '稲(湛水直播)'; 
update tsakuhojo set nozoku = replace(nozoku, '、ベントグラス、', '、芝(ベントグラス)、西洋芝(ベントグラス)、') where nozoku = '、ベントグラス、'; 
drop table if exists sakuhojo;
create table sakuhojo as select idsaku, shozoku, nozoku, fukumu from tsakuhojo order by idsaku;
drop table tsakuhojo;
drop index if exists idxSakuHojo;
create index idxSakuHojo on sakuhojo (idsaku, shozoku, nozoku, fukumu);
commit;\n
SAKU3;

if ($sqlonly) {
  $query = preg_replace('/toruby\((.+?)\)/', "strconv($1, 'kw')", $query);
  echo mb_convert_encoding($query, 'SJIS-win', 'UTF-8');
  return 1;
}

$res = $db->exec($query);
if ($res === false) {
  $err = $db->errorInfo();
  logputs("acis: sakumotsu", $err[2], 'Cron DB Error');
  if ($debug) echo "acis: sakumotsu: $err[2]\n";
  return 0;
}
dbCloseStatement($res);

$sql = <<<SAKU4
--/d
/* $date 現在の農水省農薬登録情報提供システムに基づく作物マスター (2021.5.25) */
drop view if exists $viewgroup;
drop view if exists ${viewgroup}2;
drop view if exists $viewsearch;
drop view if exists ${viewsearch}2;
drop table if exists $mastbl;
create table $mastbl (class integer, level integer, idsaku varchar primary key, xidsaku varchar, toroku integer, shukakubui varchar, sakumotsu varchar unique, betsumei varchar, ruby varchar, kamei varchar, gunmei varchar);
begin transaction;

SAKU4;

$res = $db->query("select * from $mastbl");
while ($rec = $res->fetch(PDO::FETCH_NUM)) {
  $sql .= str_replace("''", 'NULL', sprintf("insert into $mastbl values(%d, %d, '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s');\n", $rec[0], $rec[1], $rec[2], $rec[3], $rec[4], $rec[5], $rec[6], $rec[7], $rec[8], $rec[9], $rec[10]));
}
dbCloseStatement($res);

$sql .= <<<SAKU5
commit;
--別名、ふりがなインデックス作成
drop index if exists idxMSakumotsu;
create index idxMSakumotsu on $mastbl (betsumei, ruby);
--下位互換作物分類用ビュー
create view $viewgroup as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, idsaku from $mastbl where sakumotsu not like '%除く%' and not((class = 3 and toroku = 0) or (level = 4 and toroku != 1)) order by idsaku;
--新作物分類用ビュー
create view ${viewgroup}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei from $mastbl where sakumotsu not like '%除く%' order by idsaku;
--下位互換作物検索用ビュー
--create view $viewsearch as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, kamei, gunmei), 'kw') as keywords, gunmei = '落葉果樹' as rakuyokaju from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
create view $viewsearch as select level, xidsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, gunmei), 'kw') as keywords, gunmei = '落葉果樹' as rakuyokaju from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
--新作物検索用ビュー
--create view ${viewsearch}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, kamei, gunmei), 'kw') as keywords from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
create view ${viewsearch}2 as select class, idsaku, toroku, shukakubui, sakumotsu, betsumei, gunmei, strconv(concat('、', sakumotsu, ruby, betsumei, gunmei), 'kw') as keywords from m_sakumotsu where toroku in (1, 2) and sakumotsu not like '%除く%' order by idsaku;
--下位互換用作物テーブル削除
drop table if exists $dlgtbl;

/* 作物補助テーブル (2024.10.22) */
drop table if exists sakuhojo;
create table sakuhojo (idsaku varchar primary key, shozoku varchar, nozoku varchar, fukumu varchar);
begin transaction;

SAKU5;

$res = $db->query("select * from sakuhojo");
while ($rec = $res->fetch(PDO::FETCH_NUM)) {
  $sql .= str_replace("''", 'NULL', sprintf("insert into sakuhojo values('%s', '%s', '%s', '%s');\n", $rec[0], $rec[1], $rec[2], $rec[3]));
}
dbCloseStatement($res);

$sql .= <<<SAKU6
commit;
drop index if exists idxSakuHojo;
create index idxSakuHojo on sakuhojo (idsaku, shozoku, nozoku, fukumu);
SAKU6;

$sql = str_replace('ゔ', 'ヴ', $sql);
file_put_contents("$datdir/$massql", mb_convert_encoding($sql, 'SJIS-win', 'UTF-8'), LOCK_EX);
$time += microtime(true);
logputs('acis: sakumotsu', "Created $time");
if ($debug) echo "acis: sakumotsu: Created $time\n";
return 1;

function _ruby($item) {
  global $from, $into;
  $item = preg_replace('/((.*)\((露地|施設|水耕)栽培(\(.*?栽培\))?\))/', '$1、$3$2$4', $item);
  return mb_convert_kana(str_replace($from, $into, $item), 'c');
}

?>
