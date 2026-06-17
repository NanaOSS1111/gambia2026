<?php
/* Replaced by export_csv.php — redirect for any existing bookmarks */
session_start();
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
header('Location: export_csv.php?export_all=1&status=' . urlencode($_GET['status'] ?? 'all'));
exit;
