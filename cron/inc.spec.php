<?php
// .make.spec.php 設定ファイル

require_once 'inc.setup.php';

$subzip = 'spec.zip';

$specfiles['iso']      = 'spec.iso.txt';
$specfiles['cvo']      = 'spec.cvo.utf8.txt';
$specfiles['beppyo1']  = 'spec.beppyo1.txt';
$specfiles['frac']     = 'spec.frac.txt';
$specfiles['irac']     = 'spec.irac.txt';
$specfiles['hrac']     = 'spec.hrac.txt';
$specfiles['ojas']     = 'spec.ojas.txt';
$specfiles['feedrice'] = 'spec.feedrice.txt';
$specfiles['bunrui']   = 'spec.bunrui.txt';
$specfiles['ryutsu']   = 'spec.ryutsu.txt';
$specfiles['shurui']   = 'spec.idshurui.txt';

foreach($specfiles as $item => $file) {
  $src[$item] = "$datdir/$file";
}
?>
