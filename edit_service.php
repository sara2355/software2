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



$service_id = (int) $_GET['id'];

$message = "";
$messageClass = "";

/* ====== معالجة التحديث ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['serviceName']);
    $description = trim($_POST['serviceDescription']);
    $price       = trim($_POST['servicePrice']);
    $time        = trim($_POST['serviceTime']);
    $type        = trim($_POST['serviceType']);

    if ($title === "" || $description === "" || $price === "" || $time === "" || $type === "") {
        $message = "❌ فضلاً املئي جميع الحقول.";
        $messageClass = "message error";
    } else {

        $sql = "UPDATE services 
                SET title=?, description=?, price=?, time=?, type=?
                WHERE id=? AND provider_id=?";
        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssdssii",
            $title,
            $description,
            $price,
            $time,
            $type,
            $service_id,
            $provider_id
        );

        if (mysqli_stmt_execute($stmt)) {

            if (mysqli_stmt_affected_rows($stmt) > 0) {

                $message = "✅ تم تحديث الخدمة بنجاح... سيتم تحويلك الآن.";
                $messageClass = "message";

                echo "<script>
                        setTimeout(function(){
                            window.location.href = 'provider.php';
                        }, 1500);
                      </script>";

            } else {
                $message = "ℹ️ لم يتم أي تغيير أو أن الخدمة لا تتبع حسابك.";
                $messageClass = "message error";
            }

        } else {
            $message = "❌ حدث خطأ أثناء التحديث.";
            $messageClass = "message error";
        }

        mysqli_stmt_close($stmt);
    }
}

/* ====== جلب بيانات الخدمة ====== */
$sql = "SELECT title, description, price, time, type
        FROM services
        WHERE id=? AND provider_id=?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $service_id, $provider_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$service = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

/* ====== في حالة عدم وجود الخدمة ====== */
//if (!$service) {
 //   die("<h3 style='text-align:center;margin-top:50px;color:#b33;'>الخدمة غير موجودة أو لا تتبع هذا الحساب.</h3>");
//}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تعديل الخدمة</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="mihn_style.css">

<style>
body {
    margin:0; padding:0;
    background:#e9e6e2;
    font-family: "Tajawal", sans-serif;
}
main { padding:30px; display:flex; justify-content:center; }

form {
    background:#f0f0f0; padding:30px;
    width:600px; max-width:95%;
    border-radius:15px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
}
label { font-weight:bold; margin-top:15px; display:block; }
input, select {
    width:100%; padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
button {
    margin-top:20px;
    padding:12px;
background: var(--accent, #007BFF);
        color: var(--white, #fff);    border:none; border-radius:8px;
    font-weight:bold; cursor:pointer;
}
button:hover { background:#2f423a; }

.message {
    background:#d4edda; color:#155724;
    padding:12px; border-radius:8px;
    margin-bottom:20px; text-align:center;
    border:1px solid #c3e6cb;
}
.error {
    background:#f8d7da; color:#721c24;
    border:1px solid #f5c6cb;
}


.header-right-img {
    position: absolute;
    right: 10px;   /* أقصى اليمين */
    top: 50%;
    transform: translateY(-50%);
    width: 70px;   /* حجم الصورة */
}
</style>
</head>
<body>

<header class="site-header">
      <a href="provider.php" aria-label="صفحة مقدم الخدمة">
    <img src="image/home.png" class="header-right-img" alt="مقدم الخدمة" style="width:70px;">
  </a>

    
  <a href="">
    <img src="image/logo.jpg" class="logo" alt="شعار مهن">
  </a>
</header>

<main>

<div style="width:100%; max-width:600px;">

<?php if ($message !== ""): ?>
    <div class="<?php echo $messageClass; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form method="post">
    <h2 style="text-align:center;">تحديث الخدمة</h2>

    <label>اسم الخدمة:</label>
    <input type="text" name="serviceName" value="<?= htmlspecialchars($service['title']); ?>" required>

    <label>الوصف:</label>
    <input type="text" name="serviceDescription" value="<?= htmlspecialchars($service['description']); ?>" required>

    <label>السعر (ريال):</label>
    <input type="number" step="0.01" name="servicePrice" value="<?= htmlspecialchars($service['price']); ?>" required>

    <label>الوقت المتوقع (مثال 04:00:00):</label>
    <input type="text" name="serviceTime" value="<?= htmlspecialchars($service['time']); ?>" required>

    <label>نوع الخدمة:</label>
    <select name="serviceType" required>
        <option value="">اختر النوع</option>
        <option value="صيانه المنازل"   <?= $service['type']=="صيانه المنازل" ? "selected" : "" ?>>صيانه المنازل</option>
        <option value="التنظيف"         <?= $service['type']=="التنظيف" ? "selected" : "" ?>>التنظيف</option>
        <option value="خدمات السيارات"  <?= $service['type']=="خدمات السيارات" ? "selected" : "" ?>>خدمات السيارات</option>
        <option value="صيانه الاجهزه"  <?= $service['type']=="صيانه الاجهزه" ? "selected" : "" ?>>صيانه الاجهزه</option>
        <option value="التوصيل"         <?= $service['type']=="التوصيل" ? "selected" : "" ?>>التوصيل</option>
        <option value="الخدمات الشخصيه"<?= $service['type']=="الخدمات الشخصيه" ? "selected" : "" ?>>الخدمات الشخصيه</option>
    </select>

    <button type="submit">حفظ التعديلات</button>
</form>

</div>

</main>

<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator">•</span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

</body>
</html>

<?php mysqli_close($conn); ?>

