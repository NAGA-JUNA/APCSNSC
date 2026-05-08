<?php
require_once __DIR__ . '/../db.php';

unset($_SESSION['admin_user_id'], $_SESSION['admin_username']);
session_regenerate_id(true);
redirect_to('admin/login.php');
