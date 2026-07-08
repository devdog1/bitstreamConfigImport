<?php
require_once 'autoload.php';

$auth = new Auth($config);

if ($auth->handleCallback()) {
    header("Location: overview.php");
} else {
    die("Login failed. Please check your configuration and Azure AD settings.");
}
