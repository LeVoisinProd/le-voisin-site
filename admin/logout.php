<?php
require __DIR__ . '/_inc.php';
Auth::logout();
redirect('/admin/login.php');
