<?php
require_once __DIR__ . '/app/helpers.php';
unset($_SESSION['uid']);
session_regenerate_id(true);
flash('You have been signed out.');
header('Location: ' . url('index.php'));
exit;
