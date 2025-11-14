<?php
function requireRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        die("Access denied");
    }
}
?>