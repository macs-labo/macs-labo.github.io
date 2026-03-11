<?php
// .make.acis.php 設定ファイル

require_once 'inc.setup.php';
require_once 'inc.sqlite.php';

$mainzip   = 'acis.zip';
$rubytbl   = 'tsushoruby';
$rubyfile  = "$rubytbl.txt";
$kwtbl     = 'kwlists';
$kwfile    = "$kwtbl.txt";
$byochu    = 'byochu.txt';
$sakumotsu = 'sakumotsu.txt';
$total = null;
$db = null;
$fupdate = getForceUpdate();

// データベースオープン
function OpenDB(&$db) {
  if (isset($db)) return;
  global $maindb, $subdb;
  $db = new PDO("sqlite:$maindb");
  $db->exec("attach database '$subdb' as spec");
  $db->sqliteCreateFunction('regexp', '_regexp', 2);
  $db->sqliteCreateFunction('regexp', '_regexp', 3);
  $db->sqliteCreateFunction('re_replace', '_re_replace', 3);
//  $db->sqliteCreateFunction('replace', '_replace', 3);
  $db->sqliteCreateFunction('explode', '_explode', 3);
  $db->sqliteCreateFunction('ifnullstr', '_ifnullstr', 2);
  $db->sqliteCreateFunction('if', '_if', 2);
  $db->sqliteCreateFunction('if', '_if', 3);
  $db->sqliteCreateFunction('fuzzy', '_fuzzy', 1);
  $db->sqliteCreateFunction('strconv', '_strconv', 1);
  $db->sqliteCreateFunction('strconv', '_strconv', 2);
  $db->sqliteCreateFunction('chemruby', '_chemruby', 1);
  $db->sqliteCreateFunction('concat', '_concat');
  $db->sqliteCreateAggregate('concat', '_concatStep', '_concatFinal', 2);
  $db->sqliteCreateFunction('concat2', '_concat2');
  $db->sqliteCreateAggregate('concat2', '_concat2Step', '_concatFinal', 2);
}
?>
