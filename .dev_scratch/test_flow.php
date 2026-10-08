<?php
require_once('../../../wp-load.php');

$request = new WP_REST_Request('POST', '/mvapp/v1/vendor/auth/login');
$request->set_header('Content-Type', 'application/json');
$request->set_body(json_encode([
    'email' => '2@example.com',
    'password' => 'password123',
    'user_type' => 'vendor'
]));

$response = rest_do_request($request);

echo "Login Response:\n";
print_r($response->get_data());
