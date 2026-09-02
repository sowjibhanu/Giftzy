<?php
require __DIR__ . '/lib/init.php';
session_destroy();
header('Location: login.php');
