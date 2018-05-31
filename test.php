<?php
require 'mimini.php';
$browser=Mimini::open();
$browser->get('http://google.com');
$browser->click('Hình ảnh');
$browser->clickLink('#gb_1[name=abc]');
$browser->setField('@q','abcder');
$browser->click('@btnG');
echo $browser->getUrl();
//echo $browser->getContent();
//echo $browser->getUrl();
//$form=$browser->getForm('f');
//$form->setField('q','tìm kiếm');

//$browser->click('Tìm kiếm');
//$browser->submitForm($form);
//echo $browser;
//$form->setField('q','tìm kiếm');
//$form->submit();
//var_dump($browser->getContent());
//echo $browser->submitForm($form);


