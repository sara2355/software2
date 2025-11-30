<?php
// rate.php
session_start();
require 'db.php'; 

// 1. Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recipient') {
    header("Location: login.php");
    exit();
}

$recipient_id = $_SESSION['user_id'];
$request_id = isset($_GET['reqId']) ? (int)$_GET['reqId'] : 0;
$request_details = null;
$message = ['type' => '', 'text' => ''];

// --- Handle Rating Submission (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $rating_value = isset($_POST['rating_value']) ? (int)$_POST['rating_value'] : 0;
    $comment = $conn->real_escape_string($_POST['comment']);

    // Input validation (Acceptance Criteria: If I submit without a rating, I should see "Please provide a rating.")
    if ($post_request_id === 0 || $rating_value === 0 || $rating_value < 1 || $rating_value > 5) {
        $message = ['type' => 'error', 'text' => 'يرجى تحديد تقييم بالنجوم.'];
    } else {
        try {
            // Fetch service_id associated with the request
            $sql_fetch = "SELECT service_id FROM requests WHERE request_id = ? AND recipient_id = ?";
            $stmt_fetch = $conn->prepare($sql_fetch);
            $stmt_fetch->bind_param("ii", $post_request_id, $recipient_id);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();
            
            if ($result_fetch->num_rows === 1) {
                $data = $result_fetch->fetch_assoc();
                $service_id = $data['service_id'];

                // Check if rating already exists for this request
                $sql_check_rating = "SELECT COUNT(*) FROM ratings WHERE request_id = ?";
                $stmt_check = $conn->prepare($sql_check_rating);
                $stmt_check->bind_param("i", $post_request_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->fetch_row()[0] > 0) {
                    $message = ['type' => 'error', 'text' => 'تم تقييم هذا الطلب مسبقاً.'];
                } else {
                    // Insert the new rating (SCRUM-15: As a service recipient, I should be able to give a rating)
                    $sql_insert_rating = "INSERT INTO ratings (request_id, service_id, rating_value, comment) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert_rating);
                    $stmt_insert->bind_param("iiis", $post_request_id, $service_id, $rating_value, $comment);
                    $stmt_insert->execute();

                    // Success handling (Acceptance Criteria: After submission, I should see "Thank you for your feedback.")
                    // New Code: Immediate PHP redirect
// Note: We don't need to set $message here since the redirect happens immediately.

// Set a status flag in the session (optional, for displaying a success message 
// on the requests page if you choose to implement that there later)
$_SESSION['rating_status'] = 'success'; 

// Perform an IMMEDIATE PHP redirect, stopping all further rendering
header("Location: requests.php");
exit(); // CRUCIAL: Stops script execution immediately
                }
            } else {
                $message = ['type' => 'error', 'text' => 'خطأ: لم يتم العثور على الطلب أو غير مصرح لك بتقييمه.'];
            }
        } catch (Exception $e) {
            $message = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء حفظ التقييم.'];
        }
    }
}

// --- Fetch Request Details (GET request) ---
if ($request_id > 0) {
    $sql_details = "
        SELECT 
            r.request_id, 
            s.title AS service_title, 
            u.full_name AS provider_name, 
            r.request_date
        FROM requests r
        JOIN services s ON r.service_id = s.id
        JOIN users u ON r.provider_id = u.id
        WHERE r.request_id = ? AND r.recipient_id = ?
    ";
    
    $stmt_details = $conn->prepare($sql_details);
    $stmt_details->bind_param("ii", $request_id, $recipient_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    
    if ($result_details->num_rows === 1) {
        $request_details = $result_details->fetch_assoc();
        
        $datetime = new DateTime($request_details['request_date']);
        $request_details['date_formatted'] = $datetime->format('Y-m-d');
        $request_details['time_formatted'] = $datetime->format('H:i A');
    } else {
        $message = ['type' => 'error', 'text' => 'لم يتم العثور على الطلب أو غير مصرح لك بتقييمه.'];
    }
} else {
    $message = ['type' => 'error', 'text' => 'معرف الطلب غير موجود.'];
}

?>

<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>مِهَن | تقييم الخدمة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<style>
/* --- MIHN Unified Styling for Rating Page --- */
:root{
    --bg:#e9e6e2; /* Unified background */
    --card:#fff;
    --text:#2d2d2d;
    --muted:#555;
    --accent:#5A8DA8; /* Primary buttons/accents */
    --accent-hover:#4F7E97;
    --success:#5a7d68; /* Use rate button color for submission */
    --success-hover:#4a675a;
    --danger:#C62828;
    --danger-hover:#A61717;
    --border:#c7c5c2; 
    --radius:14px; /* Unified radius */
    --shadow:0 3px 10px rgba(0,0,0,0.04);
    --shadow-lg:0 8px 20px rgba(0,0,0,.15);
}

*{box-sizing:border-box}
body{
    font-family: "Tajawal", Arial, sans-serif; 
    background:var(--bg); 
    margin:0; 
    min-height:100vh; 
    display:flex; 
    flex-direction:column;
    color: var(--text);
}

.site-header {
    background: #d8d5d0;
    border-bottom: 2px solid #b9b6b2;
    padding: 15px 20px;
    text-align: center;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.logo { width: 120px; }

/* Back to Requests Link (Top Left - Consistent with requests.php) */
.back-link {
    position: absolute;
    left: 20px; 
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    color: var(--accent);
    text-decoration: none;
    font-weight: 700;
    transition: .2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.back-link:hover {
    color: var(--accent-hover);
    transform: translateY(-50%) translateX(-2px);
}

main{padding:24px; flex:1; display:flex; justify-content:center; align-items:center;}

.rate-container{
    background:var(--card); 
    padding:40px; 
    border-radius:var(--radius); /* Using the smaller, unified radius */
    box-shadow:var(--shadow-lg);
    max-width:550px;
    width:100%;
}

h1{text-align:center; color:var(--text); margin-bottom:10px; font-size:1.8rem;}
.subtitle{text-align:center; color:var(--muted); margin-bottom:30px; font-size:1rem;}

.service-info{
    background:#f8f9fa;
    padding:16px;
    border-radius:12px;
    margin-bottom:24px;
    border: 1px solid #ddd; /* Added subtle border for definition */
}
.service-info p{margin:6px 0; color:var(--text); font-size:0.95rem;}
.service-info p strong{color:var(--dark);}

.rating-section{margin-bottom:24px;}
.rating-section label{
    display:block;
    margin-bottom:10px;
    font-weight:700;
    color:var(--text);
    font-size:1.05rem;
}

.stars{
    display:flex;
    gap:8px;
    font-size:2.5rem;
    justify-content:center;
    margin-bottom:6px;
}
.star{
    cursor:pointer;
    color:#ddd;
    transition:color 0.2s, transform 0.2s;
}
.star.selected,
.star:hover{
    color:#ffc107;
    transform:scale(1.1);
}

.comment-section{margin-bottom:24px;}
.comment-section label{
    display:block;
    margin-bottom:8px;
    font-weight:700;
    color:var(--text);
    font-size:1.05rem;
}
.comment-section textarea{
    width:100%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:10px;
    font-size:1rem;
    font-family:inherit;
    resize:vertical;
    min-height:120px;
}

.actions{
    display:flex;
    gap:12px;
    justify-content:center;
}

.btn{
    padding:12px 24px; 
    border:none; 
    border-radius:10px; 
    cursor:pointer; 
    font-weight:700; 
    font-size:1rem;
    text-decoration:none;
    display:inline-block;
    text-align:center;
    transition: all 0.3s ease;
}
/* Use success color for Submit */
.btn-submit{background:var(--success); color:#fff;}
.btn-submit:hover{background:var(--success-hover);}

/* Use muted/gray for Cancel */
.btn-cancel{background:#6c757d; color:#fff;}
.btn-cancel:hover{background:#5a6268;}

.message{
    padding:12px 20px; 
    border-radius:10px; 
    margin-bottom:20px; 
    text-align:center; 
    font-weight:600;
}
.message.success{background:#d4edda; color:#155724;}
.message.error{background:#f8d7da; color:#721c24;}

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
    <a href="requests.php" class="back-link">
        <span aria-hidden="true">←</span>
        العودة للطلبات
    </a>
    <div>
        <a href="">
          <img src="image/logo.jpg" alt="شعار مِهَن" class="logo">
        </a>
    </div>
</header>

<main>
    <div class="rate-container">
        <h1>تقييم الخدمة</h1>
        <p class="subtitle">نسعد بمعرفة رأيك في الخدمة المقدمة</p>

        <div id="messageBox" class="message" style="display:none;"></div> 
        <?php if (!empty($message['text'])): ?>
            <div class="message <?php echo $message['type']; ?>"><?php echo $message['text']; ?></div>
        <?php endif; ?>


        <div class="service-info" id="serviceInfo">
            <?php if ($request_details): ?>
                <p><strong>الخدمة:</strong> <?php echo $request_details['service_title']; ?></p>
                <p><strong>مقدم الخدمة:</strong> <?php echo $request_details['provider_name']; ?></p>
                <p><strong>تاريخ الطلب:</strong> <?php echo $request_details['date_formatted']; ?></p>
                <p><strong>وقت الطلب:</strong> <?php echo $request_details['time_formatted']; ?></p>
            <?php else: ?>
                <p style="color: #d9534f;">لم يتم العثور على الطلب. يرجى العودة وإعادة المحاولة.</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="rate.php" onsubmit="return validateRating()">
            <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
            <input type="hidden" id="ratingValue" name="rating_value" value="0">
            
            <div class="rating-section">
                <label>التقييم بالنجوم:</label>
                <div class="stars" id="starsContainer">
                    <span class="star" data-value="1">★</span>
                    <span class="star" data-value="2">★</span>
                    <span class="star" data-value="3">★</span>
                    <span class="star" data-value="4">★</span>
                    <span class="star" data-value="5">★</span>
                </div>
            </div>

            <div class="comment-section">
                <label for="comment">تعليقك (اختياري):</label>
                <textarea id="comment" name="comment" placeholder="شاركنا تجربتك مع هذه الخدمة..."></textarea>
            </div>

            <div class="actions">
                <button class="btn btn-submit" type="submit" <?php echo $request_details ? '' : 'disabled'; ?>>إرسال التقييم</button>
                <a class="btn btn-cancel" href="requests.php">إلغاء</a>
            </div>
        </form>
    </div>
</main>

<footer class="site-footer">
    <a href="mailto:contact@mihan.sa" class="footer-email">contact@mihan.sa</a>
    <span class="separator">•</span>
    <span>© 2025 مِهَن — جميع الحقوق محفوظة</span>
</footer>

<script>
let selectedRating = 0;
const stars = document.querySelectorAll('.star');
const ratingValueInput = document.getElementById('ratingValue');
const messageBox = document.getElementById('messageBox'); // Target for JS error messages

function updateStars(){
  stars.forEach((star, index) => {
    if(index < selectedRating){
      star.classList.add('selected');
    } else {
      star.classList.remove('selected');
    }
  });
}

stars.forEach(star => {
  star.addEventListener('click', function(){
    selectedRating = parseInt(this.getAttribute('data-value'));
    ratingValueInput.value = selectedRating; 
    updateStars();
    // Hide error message on selection
    if (messageBox) messageBox.style.display = 'none';
  });
});

function validateRating(){
  // Client-side check before submission
  if(selectedRating === 0){
    if(messageBox){
        messageBox.textContent = 'يرجى تحديد تقييم بالنجوم.';
        messageBox.className = 'message error';
        messageBox.style.display = 'block';
    }
    return false; // Prevent form submission
  }
  if (messageBox) messageBox.style.display = 'none';
  return true; // Allow form submission
}
</script>

</body>
</html>
