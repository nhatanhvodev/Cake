
<?php
/* =================================================================================
   PHẦN 1: PHP LOGIC (XỬ LÝ ĐĂNG NHẬP & BẢO MẬT)
   ================================================================================= */
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Kết nối cơ sở dữ liệu
require_once '../config/connect.php';
//

// 2. Khởi tạo biến & CSRF Token
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
} //

// 3. Kiểm tra nếu đã đăng nhập thì chuyển hướng ngay
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: /Cake/admin/admin.php");
    } else {
        header("Location: /Cake/index.php");
    }
    exit;
} //

// 4. Xử lý khi người dùng nhấn nút Đăng nhập (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Yêu cầu không hợp lệ (Lỗi bảo mật CSRF)!';
    } else {
        // Lấy dữ liệu từ form
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']); //
        $ip          = $_SERVER['REMOTE_ADDR'];

        // Truy vấn thông tin user
        $stmt = $conn->prepare(
    "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1"
);

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close(); //

        // Xác thực mật khẩu
        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công -> Lưu Session
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            unset($_SESSION['csrf_token']); //

            // Xử lý "Ghi nhớ đăng nhập" (Remember Me)
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $exp   = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $conn->prepare("INSERT INTO login_tokens(user_id, token, expiry) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user['id'], $token, $exp);
                $stmt->execute();
                $stmt->close();

                // Lưu cookie trong 30 ngày
                setcookie('login_token', $token, time() + 30 * 86400, '/', '', false, true); 
            } //,

            // Ghi log đăng nhập
            $stmt = $conn->prepare("INSERT INTO login_logs(user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
            $stmt->bind_param("is", $user['id'], $ip);
            $stmt->execute();
            $stmt->close(); //

            // Chuyển hướng theo quyền hạn
            if ($user['role'] === 'admin') {
                header("Location: /Cake/admin/admin.php");
            } else {
                header("Location: /Cake/index.php");
            }
            exit;
        } else {
            $error_message = 'Tên đăng nhập hoặc mật khẩu không đúng!';
        }
    }
}
$conn->close();
?>

<!-- =================================================================================
   PHẦN 2: GIAO DIỆN HTML (VIEW)
   ================================================================================= -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập | Gấu Bakery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> <!-- -->

    <style>
        /* CSS Tùy chỉnh */
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        .login-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { max-width: 900px; width: 95%; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.15); background: #fff; }
        
        /* Cột Trái (Giới thiệu) */
        .login-left { background: linear-gradient(135deg, #3f7f65, #6cc3a0); color: #fff; padding: 50px; text-align: center; } /* */
        .login-left h2 { font-weight: 700; font-size: 28px; margin-bottom: 20px; }
        .login-icons i { font-size: 32px; margin: 0 10px; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 50%; transition: .3s; }
        .login-icons i:hover { transform: translateY(-5px); background: rgba(255,255,255,0.4); } /* */
        
        /* Cột Phải (Form) */
        .login-right { padding: 50px; background: #fff; }
        .login-right h3 { color: #3f7f65; font-weight: 700; margin-bottom: 30px; text-align: center; }
        .form-control { padding: 12px 15px; border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus { border-color: #3f7f65; box-shadow: 0 0 0 0.2rem rgba(63,127,101,0.25); } /* */
        .btn-login { background: #3f7f65; color: #fff; border-radius: 30px; padding: 12px; font-weight: 600; width: 100%; border: none; transition: .3s; }
        .btn-login:hover { background: #2c5c49; transform: translateY(-2px); } /* */
        .links a { text-decoration: none; color: #3f7f65; font-weight: 500; font-size: 14px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="card login-card border-0">
        <div class="row g-0">
            
            <!-- ===== CỘT TRÁI: GIỚI THIỆU ===== -->
            <div class="col-md-6 login-left d-flex flex-column justify-content-center">
                <h2><i class="fa-solid fa-heart"></i> Chào mừng trở lại</h2>
                <p>
                    Đăng nhập để quản lý <strong>đơn hàng</strong>, cập nhật
                    <strong>thông tin cá nhân</strong> và chia sẻ những
                    khoảnh khắc ngọt ngào cùng <strong>Gấu Bakery</strong>.
                </p> <!-- -->
                
                <div class="login-icons mt-4">
                    <i class="fa-solid fa-cake-candles"></i>
                    <i class="fa-solid fa-cookie-bite"></i>
                    <i class="fa-solid fa-mug-hot"></i>
                </div> <!-- -->
                
                <div class="mt-4 small opacity-75">
                    <i class="fa-solid fa-cake-candles" style="color: #ff6b9c;"></i> Mỗi chiếc bánh là một câu chuyện yêu thương
                </div>
            </div>

            <!-- ===== CỘT PHẢI: FORM ĐĂNG NHẬP ===== -->
            <div class="col-md-6 login-right">
                <h3><i class="fa-solid fa-user-lock"></i> Đăng nhập</h3>

                <!-- Hiển thị thông báo lỗi (nếu có) -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger text-center p-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?> <!-- -->

                <form method="POST" action="">
                    <!-- CSRF Token (Bảo mật) -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <!-- Tên đăng nhập -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên đăng nhập</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <!-- Chú ý: name="username" khớp với PHP -->
                            <input type="text" name="username" class="form-control border-start-0" placeholder="Nhập username..." required>
                        </div>
                    </div> <!-- -->

                    <!-- Mật khẩu -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                        </div>
                    </div> <!-- -->

                    <!-- Ghi nhớ & Quên mật khẩu -->
                    <div class="d-flex justify-content-between align-items-center mb-4 small">
                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                            <!-- Chú ý: name="remember_me" khớp với PHP -->
                            <input type="checkbox" name="remember_me" class="form-check-input mt-0"> 
                            Ghi nhớ đăng nhập
                        </label>
                        <a href="/Cake/pages/forgot_password.php" class="text-secondary">Quên mật khẩu?</a>
                    </div> <!-- -->

                    <!-- Nút Submit -->
                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                    </button>
                </form>

                <!-- Link Đăng ký -->
                <div class="links text-center mt-4">
                    Chưa có tài khoản? <a href="/Cake/pages/register.php">Đăng ký ngay</a>
                </div>
            </div> <!-- Kết thúc Cột Phải -->
            
        </div>
    </div>
</div>

</body>
</html>