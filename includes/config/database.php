<?php
try {
    $conn = new PDO(
        "mysql:host=localhost;dbname=dearengi_class;charset=utf8mb4",
        "dearengi_raisulme",
        "dgI#F6cOI?ta",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 