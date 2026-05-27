<?php
require_once 'Auth.php';
require_once 'AzureADSSO.php';
require_once 'config.php';

$auth = new Auth($CONFIG);

if ($auth->handleCallback()) {
    header("Location: overview.php");
} else {
    die("Login failed. Please check your configuration and Azure AD settings.");
}
