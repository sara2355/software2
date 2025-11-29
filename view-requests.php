<?php
session_start();
require 'db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ====== تسجيل الدخول (بعد دمج شغل البنات) ====== */
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    header("Location: login.php");
    exit();
}

// ✅ مهم جداً: نستخدم نفس رقم المستخدم المخزن في السيشن كمقدم خدمة
$provider_id = $_SESSION['user_id'];




// ===== معالجة ضغط أزرار قبول / رفض =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int) $_POST['request_id'];
    $action = $_POST['action'] === 'accept' ? 'accepted' : 'rejected';

    $updateSql = "UPDATE requests 
                  SET status = ? 
                  WHERE request_id = ? AND provider_id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "sii", $action, $request_id, $provider_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // نرجع لنفس الصفحة عشان ما يعيد إرسال الفورم
    header("Location: view-requests.php");
    exit();
}

// ===== جلب الطلبات الخاصة بهذا المقدم =====
$sql = "
    SELECT 
        r.request_id,
        r.request_date,
        r.status,
        s.title,
        s.description,
        u.full_name AS recipient_name
    FROM requests r
    JOIN services s ON r.service_id = s.id
    JOIN users u ON r.recipient_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.request_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $provider_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MIHN | طلبات الخدمات</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<style>
    html, body {
    height: 100%;
}

  body {
    font-family: 'Tajawal', sans-serif;
    background-color: #e9e6e2;
    margin: 0;
    padding: 0;
    color: #2d2d2d;
      display: flex;
    flex-direction: column;
  }

  /* ===== Header ===== */
  .site-header {
    background: #d8d5d0;
    border-bottom: 2px solid #b9b6b2;
    padding: 15px 20px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  }
  .logo {
    width: 120px;
  }

  /* ===== Main ===== */
  main {
    padding: 40px 20px 80px;
        flex: 1;

  }
  h1 {
    text-align: center;
    margin-bottom: 35px;
    font-weight: 700;
    color: #3e3e3e;
  }

  .requests {
    max-width: 850px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .request {
    background: #f6f5f3;
    border: 2px solid #c7c5c2;
    border-radius: 14px;
    padding: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0,0,0,0.04);
  }
  .request:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
  }
  .request h3 {
    margin: 0 0 5px;
    font-size: 1.15rem;
    color: #3c3c3c;
  }
  .details {
    color: #5a5a5a;
    font-size: 15px;
    margin-bottom: 12px;
  }

  /* ===== Buttons ===== */
  .actions {
    display: flex;
    gap: 10px;
  }
  .btn {
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    color: #fff;
    font-size: 15px;
    transition: all 0.25s ease;
  }
  .btn-accept { background-color: #4a675a; }
  .btn-reject { background-color: #8b3f3f; }
  .btn:hover { transform: scale(1.05); }

  /* ===== حالة القبول والرفض ===== */
  .accepted {
    background-color: #eaf1ed !important;
    border-color: #4a675a !important;
  }
  .rejected {
    background-color: #f2dada !important;
    border-color: #8b3f3f !important;
  }

  /* ===== Footer ===== */
  .site-footer {
    background: #d8d5d0;
    border-top: 2px solid #b9b6b2;
    padding: 15px;
    text-align: center;
    color: #4b4b4b;
    font-size: 15px;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
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

  /* ===== Back Button ===== */
  .back-btn {
    display: block;
    width: fit-content;
    margin: 40px auto 0;
    background-color: #3f3f3f;
    color: #fff;
    text-decoration: none;
    padding: 10px 22px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
  }
  .back-btn:hover {
    background-color: #5a5a5a;
    transform: translateY(-2px);
  }

  .no-requests {
    text-align: center;
    color: #666;
    margin-top: 20px;
    font-size: 15px;
  }
</style>
</head>
<body>

<!-- ===== Header ===== -->
<header class="site-header">
  <a href="provider.php" aria-label="الصفحة الرئيسية">
    <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
  </a>
</header>

<!-- ===== Main ===== -->
<main>
  <h1>طلبات الخدمات</h1>

  <div class="requests">
    <?php if (mysqli_num_rows($result) === 0): ?>
      <p class="no-requests">لا توجد طلبات حالياً.</p>
    <?php else: ?>
      <?php while ($row = mysqli_fetch_assoc($result)): 
        $cardClass = '';
        if ($row['status'] === 'accepted') {
            $cardClass = 'accepted';
        } elseif ($row['status'] === 'rejected') {
            $cardClass = 'rejected';
        }

        $time = date('h:i a', strtotime($row['request_date']));
      ?>
      <div class="request <?php echo $cardClass; ?>">
        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
        <p class="details">
          العميل: <?php echo htmlspecialchars($row['recipient_name']); ?> |
          <?php echo $time; ?> |
          <?php echo htmlspecialchars($row['description']); ?>
        </p>

        <div class="actions">
          <form method="post">
            <input type="hidden" name="request_id" value="<?php echo (int)$row['request_id']; ?>">

            <button 
              type="submit" 
              name="action" 
              value="accept" 
              class="btn btn-accept"
              <?php echo $row['status'] === 'accepted' ? 'disabled' : ''; ?>
            >
              قبول
            </button>

            <button 
              type="submit" 
              name="action" 
              value="reject" 
              class="btn btn-reject"
              <?php echo $row['status'] === 'rejected' ? 'disabled' : ''; ?>
            >
              رفض
            </button>
          </form>
        </div>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

  <!-- زر العودة -->
  <a href="provider.php" class="back-btn">العودة إلى الصفحة الرئيسية</a>
</main>

<!-- ===== Footer ===== -->
<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator">•</span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

