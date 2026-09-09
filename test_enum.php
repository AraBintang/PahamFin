<?php require "app/db.php"; $pdo->exec("ALTER TABLE categories MODIFY COLUMN type ENUM('PEMASUKAN','PENGELUARAN','TABUNGAN','HUTANG','PIUTANG') NOT NULL"); echo "Success"; ?>
