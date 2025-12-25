<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/inc/config.php';
session_name($cfg['session_name']);
session_start();

require_once __DIR__ . '/inc/helpers.php';
unset($_SESSION['uid']);
flash_set('success', 'Вы вышли');
redirect('/login.php');
