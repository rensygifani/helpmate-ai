<?php
require __DIR__ . '/includes/bootstrap.php';
redirect(empty($_SESSION['user']) ? 'login.php' : 'dashboard.php');
