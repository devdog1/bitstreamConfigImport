<?php
require_once 'Auth.php';
require_once 'AzureADSSO.php';
require_once 'config.php';

$auth = new Auth($CONFIG);
$auth->login();
