<?php
// requests.php
session_start();
require 'db.php'; 



$recipient_id = $_SESSION['user_id'];
$requests_data = [];
$error_message = '';

try {
    // Data Retrieval Query (SCRUM-16)
    $sql = "
        SELECT 
            r.request_id,
            s.title AS service_title,
            u.full_name AS provider_name,
            r.status,
            r.request_date,
            s.description,
            ra.rating_id IS NOT NULL AS is_rated
        FROM requests r
        JOIN services s ON r.service_id = s.id
        JOIN users u ON r.provider_id = u.id
        LEFT JOIN ratings ra ON r.request_id = ra.request_id
        WHERE r.recipient_id = ?
        ORDER BY r.request_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requests_data = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_message = "لا يوجد لديك طلبات خدمات حاليًا.";
    }

} catch (Exception $e) {
    $error_message = "حدث خطأ في جلب البيانات: " . $e->getMessage();
}

/**
 * Helper function to return Arabic text and CSS class based on request status.
 */
function get_status_display($status, $is_rated) {
    $class = 'pending';
    $text = 'قيد الدراسة';
    
    // Custom logic for status and rating
    if ($status === 'accepted') {
        $class = 'accepted';
        $text = 'مقبول';
    } elseif ($status === 'rejected') {
        $class = 'rejected';
        $text = 'مرفوض';
    }
    
    // An accepted request that has a rating record is considered completed
    if ($is_rated && $status === 'accepted') {
        $class = 'completed';
        $text = 'مكتمل ومُقيّم';
    }
    
    return ['class' => $class, 'text' => $text];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MIHN | طلبات الخدمات</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<style>

/* --- MIHN Universal Styling --- */
:root{
    --bg:#e9e6e2; 
    --card:#f6f5f3; 
    --text:#2d2d2d;
    --muted:#555;
    --chip-bg:#f8f9fa; 
    --accent:#5A8DA8; 
    --accent-hover:#4F7E97;
    --rate-button-bg: #5a7d68; 
    --rate-button-hover: #4a675a;
    --danger:#C62828;
    --danger-hover:#A61717;
    --border:#c7c5c2; 
    --radius:14px; 
    --shadow:0 3px 10px rgba(0,0,0,0.04);
    --shadow-lg:0 5px 15px rgba(0,0,0,0.08);
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
  display:flex;          /* مهم */
  flex-direction:column; /* فوق/تحت */
  min-height:100vh;
}

.site-header {
    background: #d8d5d0;
    border-bottom: 2px solid #b9b6b2;
    padding: 15px 20px;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    position: relative; /* Crucial for positioning absolute links */
}
.logo { width: 120px; }

/* --- Header Links Positioning --- */

/* Back to Services (Top Left) */
.logout-btn{position:absolute;right:20px;top:50%;transform:translateY(-50%)}
.logout-btn img{width:55px;height:55px;cursor:pointer;transition:.3s}
.logout-btn img:hover{transform:scale(1.1)}


/* --- Main Content Styling --- */
 main{
  padding:24px;
  max-width:1200px;
  margin:0 auto;   /* بس وسط أفقياً */
  width:100%;
  flex:1;          /* هذا اللي يدز الفوتر لتحت */
}

h1 { text-align: center; margin-bottom: 35px; font-weight: 700; color: #3e3e3e; }

.requests { max-width: 850px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }

.request {
    background: var(--card);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
    cursor: pointer;
}
.request:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}
.request h3 { margin: 0; font-size: 1.25rem; color: #3c3c3c; font-weight: 700; margin-bottom: 8px;}

.details { display: none; color: var(--muted); font-size: 15px; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ddd;}
.details p { margin: 6px 0; }
.details strong { color: var(--text); }

.status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    margin-top: 0;
}
/* Status Colors */
.pending { background: #fff3cd; color: #856404; }
.accepted { background: #eaf1ed; color: #4a675a; }
.rejected { background: #f2dada; color: #8b3f3f; }
.completed { background: #d1ecf1; color: #0c5460; }

/* --- Rate Button Alignment Fix --- */

.actions {
    /* Clear default flow issues, allow vertical stacking below details */
    display: block; 
    margin-top: 15px; /* Add space between details and button */
    padding-top: 15px;
    border-top: 1px dashed #ddd; /* Separator line like in .details */
}

.btn-rate {
    padding: 8px 16px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    color: #fff;
    font-size: 15px;
    background-color: var(--rate-button-bg);
    transition: all 0.25s ease;
    text-decoration: none;
    display: inline-block; /* Aligns horizontally with text flow */
    text-align: center;
    /* Removed margin-top: 15px; from previous code */
}

.btn-rate:hover { 
    background-color: var(--rate-button-hover); 
    transform: scale(1.02); 
}

.btn-rate.rated { 
    background-color: #6c757d; 
    cursor: not-allowed; 
    /* Also removed margin-top: 15px; */
}

/* Ensure original button and status styles start flush */
.status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    margin-top: 0; /* Ensures status is not pushed down */
    margin-bottom: 8px; /* Added slight space below the status */
}
/* --- Footer Styling --- */
.site-footer {
  background: #d8d5d0;
  border-top: 2px solid #b9b6b2;
  padding: 15px;
  text-align: center;
  color: #4b4b4b;
  font-size: 15px;
  box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
}

.footer-email { color: #3e3e3e; text-decoration: none; font-weight: bold; }
.separator { margin: 0 8px; color: #999; }

/* Remove the redundant back-btn at the bottom of the page and back-link from original CSS */
.back-btn { display: none !important; }

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
  <h1>طلبات الخدمات</h1>
  <div class="requests">

    <?php if (!empty($error_message)): ?>
        <p style="text-align: center; color: #8b3f3f; background: #f2dada; padding: 15px; border-radius: 10px;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php foreach ($requests_data as $request): 
        $datetime = new DateTime($request['request_date']);
        $date_formatted = $datetime->format('Y-m-d');
        $time_formatted = $datetime->format('H:i A');
        $status_display = get_status_display($request['status'], $request['is_rated']);
    ?>
    <div class="request" data-req-id="<?php echo $request['request_id']; ?>">
    <h3><?php echo htmlspecialchars($request['service_title']); ?></h3>
    
    <div class="status <?php echo $status_display['class']; ?>">
        <?php echo $status_display['text']; ?> | <?php echo $time_formatted; ?>
    </div>

    <div class="details">
        <p><strong>مقدم الخدمة:</strong> <?php echo htmlspecialchars($request['provider_name']); ?></p>
        <p><strong>تاريخ الطلب:</strong> <?php echo $date_formatted; ?></p>
        <p><strong>وصف الخدمة:</strong> <?php echo htmlspecialchars($request['description']); ?></p>
    </div>
    
    <?php if ($request['status'] === 'accepted' && !$request['is_rated']): ?>
    <div class="actions">
        <a class="btn-rate" href="rate.php?reqId=<?php echo $request['request_id']; ?>">تقييم الخدمة</a>
    </div>
    <?php elseif ($request['is_rated']): ?>
    <div class="actions">
        <button class="btn-rate rated" disabled>تم التقييم ✓</button>
    </div>
    <?php endif; ?>
</div>
    <?php endforeach; ?>

  </div>

    <a href="services.php" class="back-btn">العودة إلى الخدمات </a>
</main>

<footer class="site-footer">
  <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
  <span class="separator">•</span>
  <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

<script>
// Toggle details on click (Kept from original HTML, now applies to PHP-generated content)
document.querySelectorAll('.request h3').forEach(heading => {
  heading.addEventListener('click', () => {
    const card = heading.closest('.request');
    card.classList.toggle('active');
    const details = card.querySelector('.details');
    if (details) {
      details.style.display = details.style.display === "block" ? "none" : "block";
    }
  });
});
</script>

</body>
</html>
