#!/bin/sh
cd $(cd $(dirname $0) && pwd)
. ./inc.setup.sh

#PHP
$PhpPath "$UserRoot/cron/.sync.data.php"
exit
