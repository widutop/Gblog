<?php
require_once __DIR__ . '/../../core/bootstrap.php';
unset($_SESSION['user_id']);
header('Location: index.php');
exit;
