<?php
$host = '';
$db   = '';
$user = '';
$pass = '';
$charset = 'utf8';

// PDO configuration
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// MySQLi configuration
define('DB_SERVER', $host);
define('DB_USERNAME', $user);
define('DB_PASSWORD', $pass);
define('DB_NAME', $db);

// Attempt to connect to MySQL database using MySQLi
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset to ensure proper handling of Arabic characters
mysqli_set_charset($conn, "utf8mb4");
?>
