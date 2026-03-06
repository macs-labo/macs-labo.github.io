#!/bin/sh
cd $(cd $(dirname $0) && pwd)
. ./inc.setup.sh

#PHP
$PhpPath "$UserRoot/cron/.make.acis.php"
exit
