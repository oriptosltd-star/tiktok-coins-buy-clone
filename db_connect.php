<?php
$config = require 'config.php';

$host = $config['db']['host'];
$dbname = $config['db']['name'];
$username = $config['db']['user'];
$password = $config['db']['pass'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // For security, don't show full error in production
    // die("Connection Error: " . $e->getMessage()); 
    die("Database Connection Failed.");
}
?>
