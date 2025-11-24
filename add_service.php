<?php

session_start();
require 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);


if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'provider') {
    $provider_id = $_SESSION['user_id']; 
} else {
    die("❌ لا يوجد مقدم خدمة مسجّل الدخول.");
}



// معالجة POST عند الإرسال
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['serviceTitle']);
    $description = trim($_POST['serviceDescription']);
    $price = trim($_POST['servicePrice']);
    $time = trim($_POST['serviceTime']);
    $type = trim($_POST['serviceType']);

    if (empty($title) || empty($description) || empty($price) || empty($time) || empty($type)) {
        $msg = "<div class='msg' style='color:red'>❌ يرجى تعبئة جميع الحقول.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO services (provider_id, title, description, price, time, type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $provider_id, $title, $description, $price, $time, $type);

        if ($stmt->execute()) {
            $msg = "<div class='msg' style='color:green'>✅ تمت إضافة الخدمة بنجاح!</div>";
            // إعادة التوجيه بعد ثانيتين
            $redirect = "<script>setTimeout(()=>{ window.location.href='provider.php'; }, 2000);</script>";
        } else {
            $msg = "<div class='msg' style='color:red'>❌ حدث خطأ أثناء إضافة الخدمة.</div>";
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إضافة خدمة جديدة</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="mihn_style.css">
<style>
/* نفس التنسيق السابق */
body { font-family: "Tajawal", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:0; padding:0; min-height:100vh; }
main { padding:30px; display:flex; justify-content:center; flex:1;}
form { background:#f0f0f0; padding:30px; border-radius:15px; box-shadow:0 4px 12px rgba(0,0,0,0.15); width:600px; display:flex; flex-direction:column; }
label { margin:15px 0 5px; color:#333; font-weight:bold; }
input, select { width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; box-sizing:border-box; }
 button {
        margin-top:20px; width:100%; padding:12px;
        background: var(--accent, #007BFF);
        color: var(--white, #fff);
        border:none; border-radius:8px;
        font-weight:bold; cursor:pointer; transition:0.3s;
    }
    button:hover { background:#555; }
    .msg { text-align:center; font-weight:bold; margin-bottom:15px; }
.msg { text-align:center; font-weight:bold; margin-bottom:15px; }
h2 { text-align:center; font-size:24px; }
.site-footer, .site-header { background: #d8d5d0; border:2px solid #b9b6b2; padding: 15px; text-align:center; box-shadow: 0 2px 6px rgba(0,0,0,0.05);}
.logo { width:120px; }
.footer-email { color: #3e3e3e; text-decoration:none; font-weight:bold; }
.separator { margin: 0 8px; color:#999; }
</style>
</head>
<body>

<header class="site-header">
  <a href="provider.php" aria-label="الصفحة الرئيسية">
    <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
  </a>
</header>

<main>
  <form method="POST" action="">
     <h2>️ إضافة خدمة جديدة</h2>

     <?php
     if(isset($msg)) echo $msg;
     if(isset($redirect)) echo $redirect;
     ?>

    <label>اسم الخدمة:</label>
    <input type="text" name="serviceTitle" required>

    <label>الوصف:</label>
    <input type="text" name="serviceDescription" required>

    <label>السعر (ريال):</label>
    <input type="number" step="0.01" name="servicePrice" required>

    <label>الوقت المتوقع (بالساعات):</label>
    <input type="text" name="serviceTime" required>

    <label>نوع الخدمة:</label>
    <select name="serviceType" required>
        <option value="">اختر النوع</option>
        <option value="صيانه المنازل">صيانه المنازل</option>
        <option value="التنظيف">التنظيف</option>
        <option value="خدمات السيارات">خدمات السيارات</option>
        <option value="صيانه الاجهزه">صيانه الاجهزه</option>
        <option value="التوصيل">التوصيل</option>
        <option value="الخدمات الشخصيه">الخدمات الشخصيه</option>
    </select>

    <button type="submit">إضافة الخدمة</button>
  </form>
</main>

<footer class="site-footer" role="contentinfo" aria-label="تذييل الموقع">
    <div class="footer-inner">
      <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihn.sa</a>
      <span class="separator">•</span>
      <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
    </div>
</footer>

</body>
</html>
