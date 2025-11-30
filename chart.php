<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php'; // ملف الاتصال

// في المستقبل: تأكدي أن اليوزر (المستفيد) مخزن في السيشن
$recipientId = $_SESSION['user_id'] ?? null;

// نضمن وجود مصفوفة السلة في السيشن
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ========== دالة مساعدة ==========
function redirect($to) {
    header("Location: $to");
    exit;
}

// ========== معالجة POST (إضافة / تحديث وقت / حذف / إتمام الدفع) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1) إضافة خدمة للسلة (من services.php)
    if ($action === 'add') {
        $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

        if ($serviceId > 0) {
            // نجيب بيانات الخدمة من جدول services
            $stmt = $conn->prepare("
                SELECT id, provider_id, title, description, price, time
                FROM services
                WHERE id = ?
            ");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                // نخزن الخدمة في السلة، باستخدام service_id كمفتاح
                if (!isset($_SESSION['cart'][$serviceId])) {
                    $_SESSION['cart'][$serviceId] = [
                        'service_id'     => (int)$row['id'],
                        'provider_id'    => (int)$row['provider_id'],
                        'title'          => $row['title'],
                        'description'    => $row['description'],
                        'price'          => (float)$row['price'],
                        'time'           => $row['time'],       // الوقت الأصلي
                        'scheduled_time' => $row['time'],       // وقت مجدول قابل للتعديل من اليوزر
                    ];
                }
            }

            $stmt->close();
        }

        redirect('chart.php');
    }

    // 2) حفظ تعديل وقت الخدمة في السلة (لا يُخزن في الـDB، فقط في السيشن)
    if ($action === 'update') {
        $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
        $scheduled = $_POST['scheduled_time'] ?? '';

        if ($serviceId > 0 && isset($_SESSION['cart'][$serviceId])) {
            if ($scheduled === '') {
                $scheduled = '00:00';
            }
            $_SESSION['cart'][$serviceId]['scheduled_time'] = $scheduled;
        }

        redirect('chart.php');
    }

    // 3) حذف عنصر من السلة
    if ($action === 'delete') {
        $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

        if ($serviceId > 0 && isset($_SESSION['cart'][$serviceId])) {
            unset($_SESSION['cart'][$serviceId]);
        }

        redirect('chart.php');
    }

    // 4) إتمام الدفع → إنشاء طلبات في جدول requests ثم تفريغ السلة
    if ($action === 'checkout') {
        $cart = $_SESSION['cart'];

        // لو السلة مو فاضية وفي مستفيد مسجّل
        if (!empty($cart) && $recipientId !== null) {

            // تقدرون تخزنون pm في جدول منفصل لاحقًا، الآن مجرد متغير
            $payMethod = $_POST['pm'] ?? null;

            /*  
             * جدول الطلبات المتوقع:
             * requests(request_id, service_id, provider_id, recipient_id, status, request_date)
             */
            $stmt = $conn->prepare("
                INSERT INTO requests (service_id, provider_id, recipient_id, status, request_date)
                VALUES (?, ?, ?, 'under_processing', NOW())
            ");

            foreach ($cart as $item) {
                $serviceId   = (int)$item['service_id'];
                $providerId  = (int)$item['provider_id'];
                $recId       = (int)$recipientId;

                $stmt->bind_param("iii", $serviceId, $providerId, $recId);
                $stmt->execute();
            }

            $stmt->close();
        }

        // تفريغ السلة
        $_SESSION['cart'] = [];

        // تحويل لصفحة طلبات المستفيد
        redirect('requests.php'); 
    }
}

// ========== مرحلة العرض ==========
$cart = $_SESSION['cart'];
$totalPrice = 0;
foreach ($cart as $item) {
    $totalPrice += (float)$item['price']; // كل خدمة مرة وحدة، ما عندنا كمية
}

// تحويل HH:MM:SS → HH:MM لعرض الـtime input
function toTimeValue($t) {
    if (!$t) return '00:00';
    $parts = explode(':', $t);
    $hh = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
    $mm = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
    return "$hh:$mm";
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>مِهَن | السلة</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --bg:#e9e6e2; --card:#fff; --text:#2d2d2d; --muted:#555; --chip:#E6D5B8;
  --accent:#5A8DA8; --accent-hover:#4F7E97;
  --danger:#C62828; --danger-hover:#A61717;
  --border:#8B4513; --radius:20px;
  --shadow:0 4px 12px rgba(0,0,0,.08); --shadow-lg:0 8px 20px rgba(0,0,0,.15);
}
*{box-sizing:border-box}
body{
  font-family:'Tajawal',sans-serif;background:var(--bg);
  margin:0;color:var(--text);display:flex;flex-direction:column;min-height:100vh
}

/* Header */
.site-header{
  background:#d8d5d0;border-bottom:2px solid #b9b6b2;
  padding:15px 20px;text-align:center;position:relative;
  box-shadow:0 2px 6px rgba(0,0,0,.05)
}
.logo{width:120px}
.logout-btn{position:absolute;right:20px;top:50%;transform:translateY(-50%)}
.logout-btn img{width:55px;height:55px;cursor:pointer;transition:.3s}
.logout-btn img:hover{transform:scale(1.1)}


main{padding:24px;flex:1}
.page-title{
  display:flex;align-items:center;gap:10px;
  margin:0 0 18px;font-size:1.25rem;font-weight:800
}

.cart-wrap{display:grid;grid-template-columns:1fr 360px;gap:22px}
@media (max-width:980px){.cart-wrap{grid-template-columns:1fr}}

.item-card{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);box-shadow:var(--shadow);
  padding:18px;margin-bottom:16px;transition:.25s
}
.item-card:hover{box-shadow:var(--shadow-lg);background:#F5F5DC}
.item-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.item-header h3{margin:0;font-size:1.1rem}
.item-emoji{font-size:22px}
.item-desc{color:var(--muted);margin:6px 0 10px}
.item-chip{
  display:flex;justify-content:space-between;align-items:center;gap:10px;
  background:var(--chip);padding:8px 12px;border-radius:12px;font-weight:700;color:#333
}

.item-actions{
  display:flex;flex-wrap:wrap;gap:8px;margin-top:10px
}
.btn{
  padding:10px 14px;border:none;border-radius:10px;cursor:pointer;
  font-weight:700;text-decoration:none;display:inline-block;text-align:center
}
.btn-blue{background:var(--accent);color:#fff}
.btn-blue:hover{background:var(--accent-hover)}
.btn-red{background:var(--danger);color:#fff}
.btn-red:hover{background:var(--danger-hover)}

.summary{
  background:var(--card);border:2px solid var(--border);
  border-radius:var(--radius);box-shadow:var(--shadow);
  padding:18px;height:fit-content
}
.summary h4{margin:0 0 12px}
.total-row{display:flex;justify-content:space-between;font-weight:800;margin:10px 0 16px}
.pay-methods{display:grid;gap:8px;margin:10px 0 16px}
.pay-methods label{display:flex;align-items:center;gap:8px;cursor:pointer}
.info{
  background:#fff8e1;border:1px solid #f1df9a;color:#7a5d00;
  padding:10px;border-radius:10px;margin:10px 0;font-size:.95rem
}

.empty{
  background:#fff;border:2px dashed #b9b6b2;border-radius:16px;
  padding:26px;text-align:center;color:#666
}

/* Footer */
.site-footer{
  background:#d8d5d0;border-top:2px solid #b9b6b2;
  padding:15px;text-align:center;color:#4b4b4b;
  font-size:15px;box-shadow:0 -2px 6px rgba(0,0,0,.05)
}
.site-footer a{color:#3e3e3e;font-weight:bold;text-decoration:none}
.separator{margin:0 8px;color:#999}

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
    
     <a href="services.php" aria-label="صفحة مقدم الخدمة">
    <img src="image/home.png" class="header-right-img" alt="مقدم الخدمة" style="width:70px;">
  </a>
  <!-- الشعار صار رابط يرجع لصفحة الخدمات (هوم المستفيد) -->
  <a href="">
    <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
  </a>

  
</header>

<main>
  <h1 class="page-title">🧺 سلة المشتريات</h1>

  <div class="cart-wrap">
    <!-- قائمة العناصر -->
    <section id="cartItems">
      <?php if (empty($cart)): ?>
        <div class="empty">
          السلة فارغة حاليًا. <br>
          ابدأ بإضافة خدمات من
          <a href="services.php" class="btn btn-blue" style="margin-top:10px">صفحة الخدمات</a>.
        </div>
      <?php else: ?>
        <?php foreach ($cart as $item): ?>
          <article class="item-card">
            <div class="item-header">
              <div class="item-emoji">🧰</div>
              <h3><?php echo htmlspecialchars($item['title'] ?? 'خدمة'); ?></h3>
            </div>

            <?php if (!empty($item['description'])): ?>
              <p class="item-desc"><?php echo htmlspecialchars($item['description']); ?></p>
            <?php endif; ?>

            <div class="item-chip">
              <span>⏱️ <?php echo htmlspecialchars($item['time'] ?? 'غير محدد'); ?></span>
              <span>💰 <?php echo htmlspecialchars($item['price']); ?> ر.س</span>
            </div>

            <form method="post" class="item-actions">
              <input type="hidden" name="service_id" value="<?php echo (int)$item['service_id']; ?>">

              <label style="display:flex;align-items:center;gap:8px">
                <span>الوقت:</span>
                <input
                  class="time-input"
                  type="time"
                  name="scheduled_time"
                  value="<?php echo htmlspecialchars(toTimeValue($item['scheduled_time'] ?? $item['time'])); ?>"
                >
              </label>

              <button class="btn btn-blue" type="submit" name="action" value="update">
                حفظ التعديل
              </button>

              <button class="btn btn-red" type="submit" name="action" value="delete">
                حذف
              </button>
            </form>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- ملخص الطلب -->
    <aside class="summary">
      <h4>ملخص الطلب</h4>
      <div class="total-row">
        <span>الإجمالي</span>
        <span id="totalPrice"><?php echo $totalPrice; ?> ر.س</span>
      </div>

      <div class="info">اختر طريقة الدفع ثم اضغط “إتمام الدفع”.</div>

      <form method="post">
        <div class="pay-methods" id="payMethods">
          <label><input type="radio" name="pm" value="mada"> مدى</label>
          <label><input type="radio" name="pm" value="visa"> فيزا / ماستر</label>
          <label><input type="radio" name="pm" value="apple"> Apple Pay</label>
          <label><input type="radio" name="pm" value="cod"> الدفع عند الاستلام</label>
        </div>

        <input type="hidden" name="action" value="checkout">
        <button class="btn btn-blue" style="width:100%">إتمام الدفع</button>
      </form>
    </aside>
  </div>
</main>

<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator">•</span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

</body>
</html>

