<?php
mb_internal_encoding('UTF-8');
spl_autoload_register();
require 'vendor/autoload.php';

use redsnapper\nview\Session;
use redsnapper\nview\Settings;

Settings::getInstance(); //initialise settings..
Session::getInstance();  //initialise session.
Settings::usr();		 //maybe this should be set by Sio.
Session::setto(144000); //100 days

