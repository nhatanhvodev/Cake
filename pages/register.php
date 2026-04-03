
<?php
/* =================================================================================
   PHẦN 1: PHP LOGIC (Xử lý Đăng ký)
   ================================================================================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} //

$error_message = '';

// Kiểm tra khi người dùng nhấn nút Đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/connect.php';
$conn->set_charset("utf8mb4"); //

    // Lọc dữ liệu đầu vào
    $username = trim($_POST['username']);
    $email    = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    // Validate dữ liệu
    if (!$email) {
        $error_message = "Email không hợp lệ!"; //
    } elseif (strlen($password) < 6) {
        $error_message = "Mật khẩu tối thiểu 6 ký tự!"; //
    } else {
        // Kiểm tra tên đăng nhập đã tồn tại chưa
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error_message = "Tên đăng nhập đã tồn tại!"; //
        } else {
            // Thêm người dùng mới
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)"); //
            $stmt->bind_param("sss", $username, $hash, $email); //
            
            if ($stmt->execute()) {
                // Đăng ký thành công -> Tự động đăng nhập & chuyển hướng
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['username'] = $username;
                header("Location: /Cake/index.php");
                exit; //
            } else {
                $error_message = "Lỗi đăng ký!";
            }
            $stmt->close();
        }
        $check->close();
    }
    $conn->close(); //
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản</title> <!-- -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Thư viện Icon & Font -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 (Cần thiết để chia cột col-md-6 hoạt động) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* - CSS GỐC TỪ NGUỒN */
        
        /* Reset cơ bản */
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif; background: #f0f2f5; }

        /* Wrapper căn giữa màn hình */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center; /* */
        }

        /* Card chứa 2 cột */
        .login-card {
            max-width: 980px;
            width: 95%;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,.18);
            background: #fff; /* */
        }

        /* ===== CỘT TRÁI (LEFT) ===== */
        .login-left {
            background: linear-gradient(135deg,#3f7f65,#6cc3a0);
            color: #fff;
            padding: 56px 46px; /* */
        }
        .login-left h2 { font-weight: 800; font-size: 30px; letter-spacing: .3px; }
        .login-left p { opacity: .95; line-height: 1.8; font-size: 16px; } /* */
        
        /* Icons trang trí */
        .login-icons { margin-top: 28px; }
        .login-icons i {
            font-size: 30px; margin-right: 18px; padding: 14px;
            border-radius: 50%; background: rgba(255,255,255,.18);
            transition: .3s ease;
        }
        .login-icons i:hover {
            background: rgba(255,255,255,.35); transform: translateY(-3px); /* */
        }

        /* ===== CỘT PHẢI (RIGHT - FORM) ===== */
        .login-right {
            padding: 56px 48px;
            background: #fff; /* */
        }
        .login-right h3 {
            color: #3f7f65; font-weight: 800; font-size: 26px;
            text-align: center; margin-bottom: 28px; /* */
        }

        /* Style cho Input Form (Đồng bộ với CSS nguồn) */
        .login-right .form-label { font-weight: 600; font-size: 14px; color: #555; margin-bottom: 8px; display: block; } /* */
        .login-right .form-control {
            width: 100%; padding: 14px 18px; border-radius: 14px;
            font-size: 15px; border: 1px solid #ddd; margin-bottom: 20px; /* */
        }
        .login-right .form-control:focus {
            border-color: #3f7f65; outline: none;
            box-shadow: 0 0 0 .15rem rgba(63,127,101,.25); /* */
        }

        /* Nút Đăng ký */
        .btn-login {
            width: 100%; background: #3f7f65; color: #fff;
            border-radius: 30px; padding: 14px; font-weight: 700;
            font-size: 16px; border: none; transition: .3s ease; cursor: pointer; /* */
        }
        .btn-login:hover { background: #ff6b9c; transform: translateY(-2px); } /* */

        /* Link chuyển trang */
        .login-right a { color: #888; transition: .3s; text-decoration: none; }
        .login-right a:hover { color: #3f7f65; } /* */
        
        /* Responsive Mobile */
        @media (max-width: 768px) {
            .login-left { text-align: center; }
            .login-icons i { margin-bottom: 10px; } /* */
        }
    </style>
</head>
<body>

    <!-- ===== BỐ CỤC CHÍNH ===== -->
    <div class="login-wrapper"> <!-- -->
        <div class="card login-card border-0"> <!-- -->
            <div class="row g-0">
                
                <!-- ===== CỘT TRÁI: GIỚI THIỆU (Intro) ===== -->
                <!-- Sử dụng class col-md-6 để chiếm 50% chiều rộng -->
                <div class="col-md-6 login-left d-flex flex-column justify-content-center"> <!-- -->
                    <h2 class="mb-3">
                        <i class="fa-solid fa-heart"></i> Chào mừng
                    </h2> <!-- -->
                    
                    <p class="mt-2">
                        Cùng thực hiện để đăng ký <strong>tài khoản</strong>, cập nhật
                        <strong>thông tin cá nhân</strong> và chia sẻ những
                        khoảnh khắc ngọt ngào cùng <strong>Gấu Bakery</strong>. <!-- -->
                    </p>
                    
                    <div class="login-icons mt-4">
                        <i class="fa-solid fa-cake-candles"></i>
                        <i class="fa-solid fa-cookie-bite"></i>
                        <i class="fa-solid fa-mug-hot"></i> <!-- -->
                    </div>
                    
                    <div class="mt-4 small opacity-75">
                        <i class="fa-solid fa-cake-candles" style="color: #ff6b9c;"></i> Mỗi chiếc bánh là một câu chuyện yêu thương <!-- -->
                    </div>
                </div>

                <!-- ===== CỘT PHẢI: FORM ĐĂNG KÝ ===== -->
                <!-- Thay vì dùng .register-card, ta dùng .col-md-6 .login-right để khớp với CSS bố cục -->
                <div class="col-md-6 login-right"> <!-- -->
                    
                    <h3><i class="fa-solid fa-user-plus"></i> Đăng ký tài khoản</h3> <!-- -->

                    <!-- Hiển thị lỗi PHP -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger text-center p-2 mb-3">
                            <?= htmlspecialchars($error_message) ?> <!-- -->
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Input 1: Tên đăng nhập -->
                        <div>
                            <label class="form-label"><i class="fa-regular fa-user" style="color: #8b4513;"></i> Tên đăng nhập</label> <!-- -->
                            <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required> <!-- -->
                        </div>

                        <!-- Input 2: Email -->
                        <div>
                            <label class="form-label"><i class="fa-solid fa-envelope" style="color: #8b4513;"></i> Email</label> <!-- -->
                            <input type="email" name="email" class="form-control" placeholder="Ví dụ: ten@email.com" required> <!-- -->
                        </div>

                        <!-- Input 3: Mật khẩu -->
                        <div>
                            <label class="form-label"><i class="fa-solid fa-lock" style="color: #d32f2f;"></i> Mật khẩu</label> <!-- -->
                            <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required> <!-- -->
                        </div>

                        <!-- Nút Submit -->
                        <button type="submit" class="btn-login mt-3"> <!-- Dùng class btn-login của CSS nguồn -->
                            <i class="fa-solid fa-user-check"></i> Tạo tài khoản <!-- -->
                        </button>
                    </form>

                    <!-- Footer chuyển sang đăng nhập -->
                    <div class="text-center mt-4">
                        Đã có tài khoản? <a href="/Cake/pages/login.php" style="font-weight: bold;">Đăng nhập ngay</a> <!-- -->
                    </div>
                </div>

            </div> <!-- End .row -->
        </div> <!-- End .login-card -->
    </div> <!-- End .login-wrapper -->

</body>
</html>
