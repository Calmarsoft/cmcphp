#!/bin/sh
(cd /var/www/devpt/cmc;mv -f cmc.phar cmc.phar.old;php5 -f ./phar.php)
