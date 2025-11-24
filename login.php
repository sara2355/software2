<?php
session_start();
require "db.php";

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "طلب غير صالح"
    ]);
    exit;
}

$id = trim($_POST["identifier"]);
$pass = $_POST["password"];

$stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR phone=? LIMIT 1");
$stmt->bind_param("ss", $id, $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "اسم المستخدم أو البريد الإلكتروني غير صحيح."
    ]);
    exit;
}

if (!password_verify($pass, $user["password"])) {
    echo json_encode([
        "status" => "error",
        "message" => "كلمة المرور غير صحيحة."
    ]);
    exit;
}

$_SESSION["user_id"] = $user["id"];
$_SESSION["user_type"] = $user["user_type"];

// اختيار الصفحة حسب نوع المستخدم
$redirect = ($user["user_type"] === "provider")
            ? "provider.php"
            : "services.php";

// رد JSON للـ fetch
echo json_encode([
    "status" => "success",
    "message" => "تم تسجيل الدخول بنجاح!",
    "redirect" => $redirect
]);
exit;
?>
