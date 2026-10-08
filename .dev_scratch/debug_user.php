<?php
require_once('../../../wp-load.php');
$email = '4@example.com';
$user = get_user_by('email', $email);
if ($user) {
    echo "User ID: " . $user->ID . "\n";
    echo "Roles array:\n";
    print_r($user->roles);
    echo "Formatted role string:\n";
    echo implode(',', (array)$user->roles) . "\n";
} else {
    echo "User not found.\n";
}
