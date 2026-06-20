<?php
require_once 'includes/functions.php';
session_unset();
session_destroy();
setFlash('You have been logged out.', 'info');
redirect(baseUrl() . '/login.php');
