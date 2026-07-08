<?php
require_once 'autoload.php';

$auth = new Auth($config);
$auth->logout();

header("Location: login.php");
exit;
