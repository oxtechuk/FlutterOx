<?php
require_once('../../../wp-load.php');
$email = '2@example.com';
$user = get_user_by('email', $email);
if ($user) {
    echo "User found. ID: " . $user->ID . "\n";
    echo "Roles:\n";
    print_r($user->roles);
} else {
    echo "User not found\n";
}
