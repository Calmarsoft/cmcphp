<?php

$phar = new Phar('cmc.phar', 0, 'cmc.phar');
$phar->buildFromIterator(
    new RecursiveIteratorIterator(
     new RecursiveDirectoryIterator('/var/www/devpt/cmc/cmc')),
    '/var/www/devpt/cmc');

$phar->setStub($phar->createDefaultStub('cmc/index.php', 'cmc/index.php'));
$phar->stopBuffering();
$chksum = md5(file_get_contents('cmc.phar'));
$phar->setMetadata(array('cmcChecksum'=>$chksum));
echo $chksum;
?>

