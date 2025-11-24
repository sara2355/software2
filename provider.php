<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ====== تسجيل الدخول (مؤقت للاختبار) ====== */
// بعد ما تجهزون اللوق إن الحقيقي غيري السطرين اللي تحت:
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: login.php");
    exit();
}

$provider_id = $_SESSION['user_id'];



$message = "";
$messageClass = "";

/* ====== حذف خدمة إذا وصل طلب حذف ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];

    $sql = "DELETE FROM services WHERE id = ? AND provider_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $delete_id, $provider_id);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $message = "✅ تم حذف الخدمة بنجاح.";
            $messageClass = "message";
        } else {
            $message = "⚠️ لا يمكن حذف هذه الخدمة (قد لا تتبع حسابك).";
            $messageClass = "message error";
        }
    } else {
        $message = "❌ حدث خطأ أثناء الحذف.";
        $messageClass = "message error";
    }
    mysqli_stmt_close($stmt);
}

/* ====== جلب خدمات هذا المقدم ====== */
$sql = "SELECT id, title, description, price, time, type 
        FROM services
        WHERE provider_id = ?
        ORDER BY id DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $provider_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لوحة مقدم الخدمة</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="mihn_style.css">
<style>
   body {
    background-color: #e9e6e2;
    margin: 0;
    padding: 0;
    color: #2d2d2d;
    
  }
main { padding:30px; flex: 1;  }
.top-bar { justify-content:flex-end; margin-bottom:20px; flex-direction:column; align-items:flex-end; gap:10px; }
.add-btn {
    padding: 10px 20px;
    background: var(--accent, #007BFF);
    color: #fff;
    border-radius: 8px;
    font-weight: bold;
    transition: filter 0.2s;
    width: auto; 
    text-decoration:none;
    cursor:pointer;
      font-family: "Tajawal", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;

}
.add-btn:hover { filter: brightness(0.9); }
.services-container { display:flex; flex-wrap:wrap; gap:20px; justify-content:center; }
.service-card {
    background: var(--white, #fff);
    padding: 20px;
    border-radius: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    width: 280px;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s, box-shadow 0.3s;
border: 2px solid #8B4513; /* 🔹 بني غامق */
}
.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        color: #3e3e3e;
            background: #F5F5DC;


}
.service-card h3 { margin:0 0 10px; color:#333; font-size:1.3em; display:flex; align-items:center; }
.service-card p { margin:5px 0; color:#555; font-size:0.95em; line-height:1.4; }
.service-info { display:flex; justify-content:space-between; background:#E6D5B8; padding:5px 10px; border-radius:10px; margin-top:10px; font-weight:bold; color:#333; }
.btn { 
    padding:6px 12px; 
    margin:10px 5px 0 0; 
    border:none; 
    border-radius:8px; 
    cursor:pointer; 
    font-weight:bold; 
    transition:0.3s; 
    text-decoration:none; 
    text-align:center; 
    display:inline-block;

}
.edit-btn { background: var(--accent, #007BFF); color: var(--white);  font-family: "Tajawal", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
 }
.edit-btn:hover { background: #555;  font-family: "Tajawal", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
 }
.delete-btn { background: #C62828 ; color: #fff;   font-family: "Tajawal", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
 }
.delete-btn:hover { background: #A61717; }
.message {
    text-align:center;
    font-weight:bold;
    background:#d4edda;
    color:#155724;
    border:1px solid #c3e6cb;
    padding:10px;
    border-radius:8px;
    margin-bottom:20px;
}
.error { background:#f8d7da; color:#721c24; border-color:#f5c6cb; }
 .site-footer {
    background: #d8d5d0;
    border-top: 2px solid #b9b6b2;
    padding: 15px;
    text-align: center;
    color: #4b4b4b;
    font-size: 15px;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
  }
  
  
    /* ===== Header ===== */

   .site-header {
    background: #d8d5d0;
    border-bottom: 2px solid #b9b6b2;
    padding: 15px 20px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    position: relative; /* حتى نقدر نثبت زر الخروج */
  }

  .logo {
    width: 120px;
  }

   .logout-btn {
    position: absolute;
    right: 20px; /* أقصى اليمين */
    top: 50%;
    transform: translateY(-50%);
  }

  .logout-btn img {
    width: 55px;
    height: 55px;
    cursor: pointer;
    transition: transform 0.3s;
  }

  .logout-btn img:hover {
    transform: scale(1.1);
  }
  
  
  
  
  
  .footer-email {
    color: #3e3e3e;
    text-decoration: none;
    font-weight: bold;
  }
  .separator {
    margin: 0 8px;
    color: #999;
  }
</style>
</head>
<body>

<header class="site-header">
    <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
    <a href="logout.php" aria-label="تسجيل الخروج" class="logout-btn">
        <img src="image/logout.png" alt="خروج">
    </a>
</header>

<main>
    <div class="top-bar">
        <button class="add-btn" onclick="window.location.href='add_service.php'">➕ إضافة خدمة جديدة</button>
        <button class="add-btn" onclick="window.location.href='view-requests.php'">📄 قائمة الطلبات</button>
    </div>

    <?php if ($message !== ""): ?>
<div id="statusMessage" class="<?php echo $messageClass; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
    <?php endif; ?>

    <div class="services-container">
        <?php if (mysqli_num_rows($result) == 0): ?>
            <p>لا توجد خدمات مضافة بعد.</p>
        <?php else: ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="service-card">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><?php echo htmlspecialchars($row['description']); ?></p>

                    <div class="service-info">
                        <span>⏱️ <?php echo htmlspecialchars($row['time']); ?></span>
                        <span>💰 <?php echo htmlspecialchars($row['price']); ?> ريال</span>
                    </div>

                    <!-- أزرار تعديل وحذف -->
                    <div>
                        <button class="btn edit-btn"
                                onclick="window.location.href='edit_service.php?id=<?php echo $row['id']; ?>'">
                            تعديل
                        </button>

                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn delete-btn"
                                    onclick="return confirm('هل أنت متأكد من حذف هذه الخدمة؟');">
                                حذف
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer" role="contentinfo" aria-label="تذييل الموقع">
    <div class="footer-inner">
      <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihn.sa</a>
      <span class="separator">•</span>
      <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
    </div>
</footer>

    <script>
document.addEventListener("DOMContentLoaded", function () {
    const msg = document.getElementById("statusMessage");
    if (msg) {
        setTimeout(() => {
            msg.style.opacity = "0";
            msg.style.transition = "opacity 0.5s";
            setTimeout(() => msg.remove(), 500);
        }, 2000); // 3 ثواني
    }
});
</script>

</body>
</html>