<?php
// .make.dokusei.php 設定ファイル

$txpath   = 'https://www.acis.famic.go.jp/toroku';
$txpage   = 'index.htm';
$pdokusei = '.*href="(yukolist.+?\.zip)".*';
$psuisan  = '.*href="(suisaneikyou.+?\.zip)".*';
$pseizai  = '.*href="(tourokugaiyo.+?\.zip)".*';
$dokusei  = 'acis.dokusei.txt';
$suisan   = 'acis.suisan.txt';
$seizai   = 'acis.seizai.txt'
?>
