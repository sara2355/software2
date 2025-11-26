<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================
// قراءة الفلاتر من GET
// =========================
$q         = $_GET['q']         ?? '';
$type      = $_GET['type']      ?? '';
$priceMin  = $_GET['priceMin']  ?? '';
$priceMax  = $_GET['priceMax']  ?? '';
$ratingMin = $_GET['ratingMin'] ?? '';

// =========================
// جلب الخدمات + متوسط التقييم
// جدول الخدمات: services
// جدول التقييمات: ratings
// =========================
$sql = "
  SELECT 
    s.id AS service_id,
    s.title,
    s.description,
    s.price,
    s.time,
    s.type,
    COALESCE(AVG(r.rating_value), 0) AS avg_rating
  FROM services s
  LEFT JOIN ratings r ON r.service_id = s.id
  WHERE 1 = 1
";

$params = [];
$types  = "";

// فلتر البحث
if ($q !== '') {
    $sql .= " AND (s.title LIKE ? OR s.description LIKE ? OR s.type LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "sss";
}

// فلتر النوع
if ($type !== '') {
    $sql .= " AND s.type = ?";
    $params[] = $type;
    $types   .= "s";
}

// فلتر السعر
if ($priceMin !== '') {
    $sql .= " AND s.price >= ?";
    $params[] = $priceMin;
    $types   .= "d";
}
if ($priceMax !== '') {
    $sql .= " AND s.price <= ?";
    $params[] = $priceMax;
    $types   .= "d";
}

// تجميع قبل HAVING
$sql .= " GROUP BY s.id";

// فلتر التقييم
if ($ratingMin !== '') {
    $sql .= " HAVING avg_rating >= ?";
    $params[] = $ratingMin;
    $types   .= "d";
}

// تنفيذ الاستعلام
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL error: " . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result       = $stmt->get_result();
$servicesRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// جلب الأنواع للفلاتر
$typeSql = "SELECT DISTINCT type FROM services ORDER BY type";
$typeRes = $conn->query($typeSql);
$allTypes = [];
while ($row = $typeRes->fetch_assoc()) {
    $allTypes[] = $row['type'];
}

// عدد السلة من السيشن مؤقتاً
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>مِهَن | الخدمات</title>

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
    --danger:#C62828;
    --danger-hover:#A61717;
    --border:#8B4513;
    --radius:20px;
    --shadow:0 4px 12px rgba(0,0,0,.08);
  }

  *{box-sizing:border-box}
  body{font-family:"Tajawal",sans-serif;background:var(--bg);margin:0;color:var(--text);}

  .site-header {
    background:#d8d5d0;
    border-bottom:2px solid #b9b6b2;
    padding:15px 20px;
    text-align:center;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
    position:relative;
  }
  .logo { width:120px; }
  .logout-btn {
    position:absolute; right:20px; top:50%; transform:translateY(-50%);
  }
  .logout-btn img { width:55px; height:55px; cursor:pointer; transition:.3s; }
  .logout-btn img:hover { transform:scale(1.1); }

  main{padding:24px;max-width:1200px;margin:auto}

  .toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
  .search{display:flex;gap:8px;align-items:center}
  .search input{
    padding:12px 14px;border:1px solid #ccc;border-radius:12px;min-width:260px;font-size:15px
  }
  #searchBtn{
    background-color:var(--accent);
    color:#fff;border:none;border-radius:10px;padding:10px 18px;cursor:pointer;
    font-family:"Tajawal",sans-serif;font-weight:700;transition:.2s
  }
  #searchBtn:hover{background:#4F7E97}

  .filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .filters select,.filters input{
    padding:10px 12px;border:1px solid #ccc;border-radius:12px;background:#fff;min-width:140px;
    font-weight:700;color:#444
  }
  #clearAll{
    background:#777;color:#fff;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;font-weight:700
  }

  .cart-btn{
    padding:10px 14px;border-radius:12px;background:#2d2d2d;color:#fff;
    text-decoration:none;font-weight:800;display:inline-flex;align-items:center;gap:8px
  }
  .cart-btn.orders{ background:var(--accent); }
  .cart-btn.orders:hover{ background:var(--accent-hover); }
  .badge{background:#ff5252;color:#fff;border-radius:999px;padding:2px 8px;font-size:.85rem}

  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:22px}
  .card{
    background:var(--card);border:2px solid var(--border);border-radius:20px;box-shadow:var(--shadow);
    padding:20px;text-align:center;transition:.25s
  }
  .card:hover{transform:translateY(-4px);background:#F5F5DC;color:#3e3e3e}
  .card h3{margin:0 0 10px;font-size:1.6rem}
  .info{
    display:flex;justify-content:space-between;align-items:center;background:var(--chip);border-radius:12px;
    padding:10px 14px;font-weight:800;margin-bottom:10px
  }
  .btn{display:inline-block;padding:10px 14px;border:none;border-radius:10px;font-weight:800;cursor:pointer;text-decoration:none}
  .primary{background:var(--accent);color:#fff}
  .primary:hover{background:var(--accent-hover)}
  .add-to-cart{
    background-color:#4a675a;color:#fff;border:none;border-radius:10px;padding:10px 14px;
    font-family:"Tajawal",sans-serif;font-weight:700;cursor:pointer;transition:.2s
  }
  .add-to-cart:hover{ background:#3e594f }

  .empty-state{
    background:#fff;border:2px dashed #b9b6b2;border-radius:16px;padding:28px;text-align:center;color:#666;
    box-shadow:0 4px 12px rgba(0,0,0,0.06);grid-column:1/-1
  }
  .empty-state .hint{margin-top:6px;font-size:.95rem;color:#888}

  .site-footer {
    background:#d8d5d0;border-top:2px solid #b9b6b2;padding:15px;text-align:center;
    color:#4b4b4b;font-size:15px;box-shadow:0 -2px 6px rgba(0,0,0,0.05);
  }
  .footer-email{color:#3e3e3e;text-decoration:none;font-weight:bold}
  .separator{margin:0 8px;color:#999}
</style>
</head>
<body>
<header class="site-header">
  <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
  <a href="index.html" aria-label="تسجيل الخروج" class="logout-btn">
    <img src="image/logout.png" alt="خروج">
  </a>
</header>

<main>
  <form class="toolbar" id="filterForm" method="get" action="services.php">
    <div class="search">
      <input
        id="q"
        name="q"
        type="search"
        placeholder="ابحث عن خدمة… (كهرباء، سباكة، تنظيف…)"
        value="<?php echo htmlspecialchars($q); ?>"
      >
<button id="searchBtn" type="submit">بحث</button>

    </div>

    <div class="filters">
      <select id="typeFilter" name="type" title="نوع الخدمة">
        <option value="">كل الفئات</option>
        <?php foreach ($allTypes as $t): ?>
          <option value="<?php echo htmlspecialchars($t); ?>"
            <?php if ($type === $t) echo 'selected'; ?>>
            <?php echo htmlspecialchars($t); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <span>السعر من</span>
      <input
        id="priceMin"
        name="priceMin"
        type="number"
        min="0"
        placeholder="الحد الأدنى"
        style="width:120px"
        value="<?php echo htmlspecialchars($priceMin); ?>"
      >
      <span>إلى</span>
      <input
        id="priceMax"
        name="priceMax"
        type="number"
        min="0"
        placeholder="الحد الأقصى"
        style="width:120px"
        value="<?php echo htmlspecialchars($priceMax); ?>"
      >

      <select id="ratingMin" name="ratingMin" title="التقييم الأدنى">
        <option value="">كل التقييمات</option>
        <option value="3"   <?php if($ratingMin==='3')   echo 'selected'; ?>>3 ★ وما فوق</option>
        <option value="4"   <?php if($ratingMin==='4')   echo 'selected'; ?>>4 ★ وما فوق</option>
        <option value="4.5" <?php if($ratingMin==='4.5') echo 'selected'; ?>>4.5 ★ وما فوق</option>
      </select>

      <button id="clearAll" type="button" onclick="window.location='services.php';">
        مسح الفلاتر
      </button>
    </div>

    <div style="display:flex;gap:10px;align-items:center;margin-inline-start:auto;">
      <a class="cart-btn orders" href="requests.php">📄 قائمة الطلبات</a>
      <a class="cart-btn" href="chart.php" id="cartBtn">
        🛒 السلة <span class="badge" id="cartCount"><?php echo $cartCount; ?></span>
      </a>
    </div>
  </form>

  <section class="grid" id="grid">
    <?php
    if (empty($servicesRows)) {
        if ($q !== '') {
            echo '<div class="empty-state">
                    <div>🔎 لم يتم العثور على نتائج.</div>
                    <div class="hint">يمكنك تعديل البحث أو مسح الفلاتر.</div>
                  </div>';
        } elseif ($type !== '') {
            echo '<div class="empty-state">
                    <div>🔎 لم يتم العثور على خدمات لهذا النوع.</div>
                    <div class="hint">يمكنك تغيير النوع أو مسح الفلاتر.</div>
                  </div>';
        } elseif ($priceMin !== '' || $priceMax !== '') {
            echo '<div class="empty-state">
                    <div>🔎 لم يتم العثور على خدمات لهذا النطاق السعري.</div>
                    <div class="hint">جرّبي تغيير قيم السعر.</div>
                  </div>';
        } elseif ($ratingMin !== '') {
            echo '<div class="empty-state">
                    <div>🔎 لم يتم العثور على خدمات لهذا التصنيف.</div>
                    <div class="hint">اختاري تقييم أقل أو امسحي الفلتر.</div>
                  </div>';
        } else {
            echo '<div class="empty-state">
                    <div>لا توجد خدمات متاحة حاليًّا.</div>
                  </div>';
        }
    } else {
        foreach ($servicesRows as $s):
    ?>
      <div class="card">
        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
        <div class="info">
          <span>⏱️ <?php echo htmlspecialchars($s['time']); ?></span>
          <span>💰 <?php echo htmlspecialchars($s['price']); ?> ر.س</span>
        </div>
        <div style="display:flex;gap:8px;justify-content:center">
          <a class="btn primary" href="details.php?id=<?php echo $s['service_id']; ?>">
            عرض التفاصيل
          </a>

          <form method="post" action="chart.php" style="margin:0;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="service_id" value="<?php echo $s['service_id']; ?>">
            <button type="submit" class="add-to-cart">إضافة إلى السلة</button>
          </form>
        </div>
      </div>
    <?php
        endforeach;
    }
    ?>
  </section>
</main>

<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator">•</span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>


</body>
</html>

