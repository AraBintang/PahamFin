<?php
require 'C:/laragon/www/PahamFin/app/db.php';
$stmt = $pdo->prepare("SHOW COLUMNS FROM transaction_archive");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
