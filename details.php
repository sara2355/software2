<<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) الحصول على ID الخدمة
$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($serviceId <= 0) {
    die("<h3 style='text-align:center;margin-top:40px;'>الخدمة غير موجودة.</h3>");
}

// 2) جلب بيانات الخدمة
$stmt = $conn->prepare("
    SELECT id, provider_id, title, description, price, time, type
    FROM services
    WHERE id = ?
");
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    die("<h3 style='text-align:center;margin-top:40px;'>الخدمة غير موجودة.</h3>");
}

// 3) جلب تقييمات الخدمة
$rev = $conn->prepare("
    SELECT rating_value, comment, rating_date
    FROM ratings
    WHERE service_id = ?
    ORDER BY rating_id DESC
");
$rev->bind_param("i", $serviceId);
$rev->execute();
$reviews = $rev->get_result()->fetch_all(MYSQLI_ASSOC);
$rev->close();

// متوسط التقييم
$avgRating = 0;
if ($reviews) {
    $sum = 0;
    foreach ($reviews as $r) $sum += $r['rating_value'];
    $avgRating = round($sum / count($reviews), 1);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>تفاصيل الخدمة | مِهَن</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#e9e6e2;
  --card:#fff;
  --text:#2d2d2d;
  --muted:#555;
  --chip:#E6D5B8;
  --accent:#5A8DA8;
  --accent-hover:#4F7E97;
  --border:#8B4513;
  --radius:20px;
  --shadow:0 4px 12px rgba(0,0,0,.08);
}

*{box-sizing:border-box}

html, body{
  height:100%;
  margin:0;
  padding:0;
}

body{
  font-family:"Tajawal",sans-serif;
  background:var(--bg);
  color:var(--text);
  display:flex;
  flex-direction:column;
}

/* Header */
.site-header{
  background:#d8d5d0;
  border-bottom:2px solid #b9b6b2;
  padding:15px 20px;
  text-align:center;
  position:relative;
}
.logo{width:120px}
.logout-btn{
  position:absolute;
  right:20px;
  top:50%;
  transform:translateY(-50%);
}
.logout-btn img{
  width:55px;
  cursor:pointer;
}

/* Main content */
main{
  padding:24px;
  max-width:1200px;
  width:100%;
  flex:1;              /* يخلي الفوتر دايمًا تحت */
}

.card{
  background:var(--card);
  border:2px solid var(--border);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  max-width:650px;
  width:100%;
  padding:24px;
  margin:40px auto;     /* ✅ رجّع الكرت بالنص مع مسافة من فوق */
}

.card h2{
  margin-bottom:10px;
  font-size:1.8rem;
}

.desc{
  color:var(--muted);
  margin-bottom:14px;
  line-height:1.7;
}

.info-box{
  background:var(--chip);
  padding:12px;
  border-radius:12px;
  display:flex;
  justify-content:space-between;
  font-weight:800;
  margin-bottom:16px;
}

.rating{
  font-size:20px;
  margin-bottom:10px;
}

.review{
  background:#faf8f5;
  border:1px solid #e8e2da;
  border-radius:12px;
  padding:12px;
  margin-bottom:10px;
}

.muted{
  color:var(--muted);    /* ✅ عشان نص "لا توجد تقييمات بعد" يطلع صح */
}

.btn{
  background:var(--accent);
  color:#fff;
  padding:12px 20px;
  border-radius:10px;
  border:none;
  cursor:pointer;
  font-weight:800;
  display:block;
  width:100%;
  font-size:1.1rem;
}

/* Footer */
.site-footer {
  background: #d8d5d0;
  border-top: 2px solid #b9b6b2;
  padding: 15px;
  text-align: center;
  color: #4b4b4b;
  font-size: 15px;
  box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
}
.footer-email{color:#3e3e3e;font-weight:bold;text-decoration:none}
.separator{margin:0 8px;color:#666}

</style>
</head>

<body>

<!-- HEADER -->
<header class="site-header">
  <!-- الشعار صار رابط يرجع لصفحة الخدمات (هوم المستفيد) -->
  <a href="services.php">
    <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
  </a>

  <!-- زر تسجيل الخروج -->
  <a href="index.php" aria-label="تسجيل الخروج" class="logout-btn">
    <img src="image/logout.png" alt="خروج">
  </a>
</header>

<!-- CONTENT -->
<main>
  <article class="card">

    <h2><?= htmlspecialchars($service['title']) ?></h2>

    <p class="desc"><?= nl2br(htmlspecialchars($service['description'])) ?></p>

    <div class="info-box">
      <span>⏱️ <?= $service['time'] ?></span>
      <span>💰 <?= $service['price'] ?> ر.س</span>
      <span>🏷️ <?= htmlspecialchars($service['type']) ?></span>
    </div>

    <h3>التقييمات (<?= $avgRating ?: "لا يوجد" ?> ★)</h3>

    <?php if(empty($reviews)): ?>
      <p class="muted">لا توجد تقييمات بعد.</p>
    <?php else: ?>
      <?php foreach($reviews as $r): ?>
        <div class="review">
          ⭐ <?= $r['rating_value'] ?><br>
          <?= htmlspecialchars($r['comment']) ?><br>
          <small><?= $r['rating_date'] ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- زر الطلب -->
    <form method="post" action="chart.php">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
      <button class="btn">طلب الخدمة</button>
    </form>

  </article>
</main>

<!-- FOOTER -->
<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator"> • </span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

</body>
</html>


