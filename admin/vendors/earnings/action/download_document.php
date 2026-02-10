<?php 
include_once'../../../includes/config.php';
include_once'../../../includes/auth-check.php';

if($_SESSION['user_type'] !== 'vendor') {
    header('location:'. SITE_URL .'index.php');
    exit();
}

?>