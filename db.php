<?php
// إعدادات الاتصال بقاعدة البيانات
$host = "localhost";     
$user = "root";         
$pass = "root";              
$db   = "mihn";       

$conn = new mysqli($host, $user, $pass, $db);

// فحص الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ضبط اللغة العربية لتفادي الترميز
$conn->set_charset("utf8mb4");
?>
