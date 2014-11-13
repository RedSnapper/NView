<?php
mb_internal_encoding('UTF-8');
set_include_path( get_include_path() . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT']);
require_once("nview.iphp");
$v= new NView('hw.xml');
$t= new NView('table.xml');
$v->set("//*[@data-xp='title']/child-gap()","Hello World");
$t->set("//*[@data-xp='a']/child-gap()","Hello");
$t->set("//*[@data-xp='b']/child-gap()","World");
$t->set("//*[@data-xp='c']/child-gap()",time());
$v->set("//*[@data-xp='main']/child-gap()",$t);
echo $v->show(true);
