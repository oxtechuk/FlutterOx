<?php
require_once('../../../wp-load.php');
global $wp_roles;
echo "Registered Roles in WP:\n";
print_r(array_keys($wp_roles->roles));
