<?php
//when using xml, it is best to always use unicode for everything.
mb_internal_encoding('UTF-8');

//Set include paths to include current path, and paths from doc. root.
set_include_path( get_include_path() . PATH_SEPARATOR . $_SERVER['DOCUMENT_ROOT'] . PATH_SEPARATOR . dirname(__FILE__));

//load the nview class
require_once("../nview.iphp");

//Instantiate the view for this php.
//hw.xml uses the basename of this file + 'xml' as an extension.
$v= new NView();  //will be hw.xml

//Load up a fragment view.
$t= new NView('table.xml');

//set the title of this view.
$v->set("//*[@data-xp='title']/child-gap()","Hello World");

//Modify parts of the fragment view $t
$t->set("//*[@data-xp='a']/child-gap()","Hello");
$t->set("//*[@data-xp='b']/child-gap()","World");
$t->set("//*[@data-xp='c']/child-gap()",time());

//Now insert the fragment $t into the main view.
$v->set("//*[@data-xp='main']/child-gap()",$t);

//now display the view for this file, with xml prefix etc.
echo $v->show(true);
