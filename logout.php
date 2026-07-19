<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
clearRememberToken();
session_unset();
session_destroy();
header('Location: login.php?msg=' . urlencode('You have been successfully logged out.') . '&type=success');
exit;
