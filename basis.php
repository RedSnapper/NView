<?php
mb_internal_encoding('UTF-8');
spl_autoload_register();
require 'vendor/autoload.php';

$s = new Services();
$st = $s->get(Settings::class);
Session::getInstance();  //initialise session.
Session::setto(144000);  //100 days
Session::start();
Settings::usr();		 //maybe this should be set by Sio.


