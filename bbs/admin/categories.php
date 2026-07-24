<?php
/**
 * Retired: the forum admin moved to Keeper (/keeper/bbs/). Redirects to the
 * Keeper equivalent, preserving any ?id= query string.
 */
$qs = (isset($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] !== "") ? "?" . $_SERVER["QUERY_STRING"] : "";
header("Location: /keeper/bbs/categories.php" . $qs, true, 301);
exit;
