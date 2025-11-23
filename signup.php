<?php
header("Content-Type: application/json");
require "db.php"; // ملف الاتصال بقاعدة البيانات

$response = [];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$full = trim($_POST["full_name"]);
$email = trim($_POST["email"]);
$phone = trim($_POST["phone"]);
$pass = $_POST["password"];
$type = $_POST["user_type"];

if (strlen($pass) < 8) {
    echo json_encode(["status" => "error", "message" => "كلمة المرور يجب أن تكون 8 أحرف على الأقل"]);
    exit;
}

if (!preg_match("/^05[0-9]{8}$/", $phone)) {
    echo json_encode([
        "status" => "error",
        "message" => "رقم الجوال غير صالح. يجب أن يبدأ بـ 05 ويتكون من 10 أرقام."
    ]);
    exit;
}


// تحقق أن الإيميل غير موجود
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "هذا البريد الإلكتروني مسجل مسبقًا ، قم بتسجيل دخولك أو استخدم بريد آخر"]);
    exit;
}



// تشفير الباسوورد
$hashed = password_hash($pass, PASSWORD_DEFAULT);

// الإدخال
$stmt = $conn->prepare("
INSERT INTO users(full_name,email,phone,password,user_type)
VALUES(?,?,?,?,?)
");

$stmt->bind_param("sssss", $full, $email, $phone, $hashed, $type);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "تم إنشاء الحساب بنجاح! سيتم تحويلك الآن…"
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "حدث خطأ، حاول مرة أخرى"]);
exit;
?>