<?php
session_start();
header('Content-Type: application/json');

$response = [
    'logged_in' => isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true
];

echo json_encode($response);
?>
