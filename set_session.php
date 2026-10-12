<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_email'] = 'admin@athletica.pk';
$_SESSION['admin_name'] = 'ATHLETICA Admin';
echo "LOGGED_IN: " . session_id();
