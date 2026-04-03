<?php
/**
 * ADMIN DASHBOARD - FINAL VERSION
 */

// 1. KẾT NỐI VÀ CẤU HÌNH
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kết nối Database (Nhúng trực tiếp để đảm bảo hoạt động)
require_once '../config/connect.php';
// Hàm tạo lại CSRF Token
function regenerateCsrfToken()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    regenerateCsrfToken();
}

// Hàm hỗ trợ set Toast Message
function setAdminToast($msg, $type = 'success')
{
    $_SESSION['admin_toast'] = ['msg' => $msg, 'type' => $type];
}

// Hàm xử lý đường dẫn ảnh (Kết hợp logic từ nguồn)
function buildImageUrl(string $relativePath): array
{
    $defaultImage = '/Cake/assets/img/no-image.jpg';
    $result = ['url' => $defaultImage];

    if (empty($relativePath))
        return $result;

    // Handle assets/ prefix if it's stored as img/ in the DB
    if (strpos($relativePath, 'assets/') === false && strpos($relativePath, 'img/') === 0) {
        $relativePath = 'assets/' . $relativePath;
    }

    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Cake/' . ltrim($relativePath, '/');
    if (file_exists($fullPath)) {
        $result['url'] = '/Cake/' . ltrim($relativePath, '/');
    }
    return $result;
}

// 2. XỬ LÝ LOGIC (POST REQUESTS)

/* --- ĐĂNG XUẤT --- */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

/* --- ĐĂNG NHẬP ADMIN --- */
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_error = 'Lỗi bảo mật CSRF!';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Kiểm tra tài khoản (Demo: admin/admin123) hoặc check DB nếu bảng users có phân quyền
        // Ở đây dùng logic DB để khớp với hệ thống
        $stmt = $conn->prepare(
            "SELECT id, password FROM admins WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();


        // Fallback: Nếu không có trong DB thì dùng tài khoản cứng để test (theo nguồn)
        if (($admin && password_verify($password, $admin['password'])) || ($username === 'admin' && $password === 'admin123')) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            unset($_SESSION['csrf_token']); // Reset token sau khi login
            header("Location: admin.php");
            exit;
        } else {
            $login_error = 'Sai tài khoản hoặc mật khẩu!';
        }
    }
}

// Xử lý dữ liệu khi ĐÃ ĐĂNG NHẬP
if (isset($_SESSION['admin_logged_in'])) {

    // Tạo token mới nếu chưa có sau khi login
    if (empty($_SESSION['csrf_token']))
        regenerateCsrfToken();

    /* --- XỬ LÝ BLOG (Duyệt/Hủy) --- */
    if (isset($_POST['approve_blog']) || isset($_POST['reject_blog'])) {
        if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $status = isset($_POST['approve_blog']) ? 'approved' : 'rejected';
            // Nếu reject thì có thể xóa hoặc update status, ở đây update status
            $stmt = $conn->prepare("UPDATE blogs SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $_POST['blog_id']);
            $stmt->execute();
            regenerateCsrfToken();
        }
    }

    /* ===== UPLOAD HÌNH ẢNH SẢN PHẨM ===== */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['add_product']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        // ===== VALIDATE DỮ LIỆU =====
        $ten_banh = trim($_POST['ten_banh'] ?? '');
        $loai = $_POST['loai'] ?? '';
        $gia = isset($_POST['gia']) ? (float) $_POST['gia'] : 0;
        $feat = isset($_POST['is_featured']) ? 1 : 0;

        if ($ten_banh === '' || $loai === '' || $gia <= 0) {
            setAdminToast("Dữ liệu sản phẩm không hợp lệ", "error");
            header("Location: admin.php#products");
            exit;
        }

        /* ===== UPLOAD HÌNH ẢNH ===== */
        $hinh_anh = '';

        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === 0) {

            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/Cake/assets/img/banh{$loai}/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['hinh_anh']['name'], PATHINFO_EXTENSION));
            $allow = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allow)) {
                setAdminToast("Định dạng ảnh không hợp lệ (hỗ trợ: jpg, png, webp)", "error");
                header("Location: admin.php#products");
                exit;
            }

            $fileName = uniqid('banh_', true) . '.' . $ext;
            $targetPath = $upload_dir . $fileName;
            $hinh_anh = "assets/img/banh{$loai}/" . $fileName;

            if (!move_uploaded_file($_FILES['hinh_anh']['tmp_name'], $targetPath)) {
                setAdminToast("Không thể tải ảnh lên máy chủ", "error");
                header("Location: admin.php#products");
                exit;
            }
        }

        /* ===== INSERT DB ===== */
        $stmt = $conn->prepare(
            "INSERT INTO banh (ten_banh, loai, gia, hinh_anh, is_featured)
         VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssdsi",
            $ten_banh,
            $loai,
            $gia,
            $hinh_anh,
            $feat
        );

        $stmt->execute();

        setAdminToast("Thêm sản phẩm thành công!");
        header("Location: admin.php#products");
        exit;
    }


    /* --- XỬ LÝ ĐƠN HÀNG (Duyệt/Hủy) --- */
    if (isset($_POST['approve_order']) || isset($_POST['cancel_order'])) {
        if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $status = isset($_POST['approve_order']) ? 'approved' : 'cancelled';
            $id = isset($_POST['approve_order']) ? $_POST['approve_order_id'] : $_POST['cancel_order_id'];

            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();

            $msg = ($status === 'approved') ? "Đã xác nhận đơn hàng #$id" : "Đã hủy đơn hàng #$id";
            setAdminToast($msg);

            regenerateCsrfToken();
            header("Location: admin.php#orders");
            exit;
        }
    }

    /* --- XỬ LÝ KHUYẾN MÃI --- */
    if (isset($_POST['add_promotion']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $stmt = $conn->prepare("INSERT INTO promotions (banh_id, gia_khuyen_mai, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $_POST['banh_id'], $_POST['gia_khuyen_mai'], $_POST['ngay_bat_dau'], $_POST['ngay_ket_thuc']);
        $stmt->execute();
        regenerateCsrfToken();
    }

    if (isset($_GET['delete_promotion_id'])) {
        $id = (int) $_GET['delete_promotion_id'];
        $conn->query("DELETE FROM promotions WHERE id=$id");
    }
}

// 3. LẤY DỮ LIỆU HIỂN THỊ & CHUẨN BỊ BIỂU ĐỒ
$products = [];
$users = [];
$orders = [];
$order_items = [];
$promotions = [];
$blogs = [];
$total_revenue = 0;
$pending_count = 0;
$js_dates = '[]';
$js_revenues = '[]';

if (isset($_SESSION['admin_logged_in'])) {
    if (isset($_GET['delete_product_id'])) {
        $id = (int) $_GET['delete_product_id'];

        $stmt = $conn->prepare(
            "UPDATE banh SET is_deleted = 1 WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setAdminToast("Đã ngừng bán sản phẩm thành công!");
        header("Location: admin.php#products");
        exit;
    }
}


// Lấy dữ liệu từ DB
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Lấy đơn hàng kèm thông tin user (nếu có)
// Lưu ý: Nếu user_id null hoặc đã xóa user, vẫn nên hiện đơn hàng -> dùng LEFT JOIN
$orders = $conn->query("SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$order_items = $conn->query("SELECT oi.*, b.ten_banh FROM order_items oi LEFT JOIN banh b ON oi.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);
$promotions = $conn->query("SELECT p.*, b.ten_banh FROM promotions p JOIN banh b ON p.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);
$blogs = $conn->query("SELECT b.*, u.username FROM blogs b LEFT JOIN users u ON b.author_id = u.id ORDER BY b.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// --- LOGIC THỐNG KÊ & BIỂU ĐỒ ---
// Khởi tạo mảng doanh thu 7 ngày gần nhất = 0
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_data[$date] = 0;
}

foreach ($orders as $o) {
    if ($o['status'] === 'approved') {
        $total_revenue += $o['total_amount'];

        // Cộng tiền vào ngày tương ứng
        $order_date = date('Y-m-d', strtotime($o['created_at']));
        if (isset($chart_data[$order_date])) {
            $chart_data[$order_date] += $o['total_amount'];
        }
    }
    if ($o['status'] === 'pending')
        $pending_count++;
}

// Chuyển dữ liệu sang JSON để JS sử dụng
$js_dates = json_encode(array_keys($chart_data));
$js_revenues = json_encode(array_values($chart_data));
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gấu Bakery</title>

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Icons & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-green: #C8E6C9;
            --light-green: #E8F5E9;
            --dark-green: #2E7D32;
            --accent-green: #81C784;
            --sidebar-width: 250px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            margin: 0;
        }

        /* --- LOGIN STYLES --- */
        .admin-login-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #e8f5e9;
        }

        .admin-login-card {
            width: 420px;
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: fadeUp 0.4s ease;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-login-card .icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-green);
            font-size: 28px;
        }

        .btn-admin-login {
            background: var(--dark-green);
            color: #fff;
            border-radius: 30px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: 0.3s;
        }

        .btn-admin-login:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }

        /* --- DASHBOARD STYLES --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-green), #ffffff);
            height: 100vh;
            position: fixed;
            padding: 20px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }

        .sidebar h2 {
            color: var(--dark-green);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .nav-link {
            color: var(--dark-green);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-link:hover,
        .nav-link.active {
            background: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transform: translateX(5px);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: 0.3s;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            border-left: 5px solid var(--dark-green);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h5 {
            margin: 0;
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .stat-info h3 {
            margin: 5px 0 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-icon {
            font-size: 1.8rem;
            color: var(--dark-green);
            background: var(--light-green);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Tables & Tabs */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-table {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            background: var(--primary-green);
            color: var(--dark-green);
            padding: 15px;
            text-align: left;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-green {
            background: var(--dark-green);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: .2s;
        }

        .btn-green:hover {
            background: #1b5e20;
            color: white;
        }

        .btn-action {
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            margin-right: 5px;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-delete:hover {
            background: #c0392b;
        }
    </style>
</head>

<body class="<?= !isset($_SESSION['admin_logged_in']) ? 'admin-login-body' : '' ?>">

    <!-- ================= TRƯỜNG HỢP 1: CHƯA ĐĂNG NHẬP (HIỆN FORM LOGIN) ================= -->
    <?php if (!isset($_SESSION['admin_logged_in'])): ?>
        <div class="admin-login-card">
            <div class="icon"><i class="fa-solid fa-user-shield"></i></div>
            <h3>Admin Login</h3>
            <p>Hệ thống quản trị Gấu Bakery</p>

            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger text-center p-2 mb-3">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="admin_login" value="1">

                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Tên đăng nhập (admin)" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Mật khẩu (admin123)" required>
                </div>
                <button type="submit" class="btn-admin-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                </button>
            </form>
            <div class="mt-4 small text-muted">&copy; <?= date('Y') ?> Gấu Bakery Admin Panel</div>
        </div>

        <!-- ================= TRƯỜNG HỢP 2: ĐÃ ĐĂNG NHẬP (HIỆN DASHBOARD) ================= -->
    <?php else: ?>

        <!-- 1. SIDEBAR -->
        <div class="sidebar">
            <h2><i class="bi bi-flower1"></i> Bánh Store</h2>
            <nav class="nav flex-column">
                <a class="nav-link active" onclick="showTab(event, 'dashboard')"><i class="bi bi-speedometer2"></i>
                    Dashboard</a>
                <a class="nav-link" onclick="showTab(event, 'orders')"><i class="bi bi-cart-check"></i> Đơn hàng</a>
                <a class="nav-link" onclick="showTab(event, 'products')"><i class="bi bi-box-seam"></i> Sản phẩm</a>
                <a class="nav-link" onclick="showTab(event, 'users')"><i class="bi bi-people"></i> Khách hàng</a>
                <a class="nav-link" onclick="showTab(event, 'promotions')"><i class="bi bi-tags"></i> Khuyến mãi</a>
                <a class="nav-link" onclick="showTab(event, 'blogs')"><i class="bi bi-newspaper"></i> Bài viết</a>
            </nav>
        </div>

        <!-- 2. MAIN CONTENT -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
                <h3 class="m-0 text-success fw-bold">Quản Trị Hệ Thống</h3>
                <a href="?logout=1" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Đăng
                    xuất</a>
            </div>

            <!-- TAB 1: DASHBOARD -->
            <div id="dashboard" class="tab-content active">
                <!-- Thẻ thống kê -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng doanh thu</h5>
                                <h3><?= number_format($total_revenue, 0, ',', '.') ?>đ</h3>
                            </div>
                            <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Đơn chờ xử lý</h5>
                                <h3 class="text-warning"><?= $pending_count ?></h3>
                            </div>
                            <div class="stat-icon bg-warning text-white"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng sản phẩm</h5>
                                <h3><?= count($products) ?></h3>
                            </div>
                            <div class="stat-icon bg-info text-white"><i class="bi bi-box"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Khách hàng</h5>
                                <h3><?= count($users) ?></h3>
                            </div>
                            <div class="stat-icon bg-secondary text-white"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ doanh thu -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-3">
                            <h5 class="text-success mb-3"><i class="bi bi-graph-up-arrow"></i> Biểu đồ doanh thu 7 ngày qua
                            </h5>
                            <div style="height: 350px;">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bảng đơn hàng mới nhất -->
                <div class="custom-table">
                    <h5 class="mb-3">Đơn hàng mới nhất</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orders, 0, 5) as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td><?= htmlspecialchars($o['username'] ?? 'Khách lẻ') ?></td>
                                    <td><?= number_format($o['total_amount']) ?>đ</td>
                                    <td>
                                        <?php
                                        $statusData = match (strtolower($o['status'])) {
                                            'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                            'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ'],
                                            'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                            'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                            'confirmed', 'approved' => ['badge' => 'primary', 'label' => 'Đã xác nhận'],
                                            default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: ORDERS -->
            <div id="orders" class="tab-content">
                <h3 class="mb-4 text-success">Quản Lý Đơn Hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Chi tiết SP</th>
                                <th>Tổng tiền</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($o['username'] ?? 'N/A') ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($o['email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php foreach ($order_items as $i):
                                            if ($i['order_id'] == $o['id']): ?>
                                                <div class="small">- <?= htmlspecialchars($i['ten_banh']) ?> (x<?= $i['quantity'] ?>)
                                                </div>
                                            <?php endif; endforeach; ?>
                                    </td>
                                    <td class="fw-bold"><?= number_format($o['total_amount']) ?>đ</td>
                                    <td>
                                        <?php
                                        $statusData = match (strtolower($o['status'])) {
                                            'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                            'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ'],
                                            'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                            'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                            'confirmed', 'approved' => ['badge' => 'primary', 'label' => 'Đã xác nhận'],
                                            default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>

                                        <?php if ($o['status'] == 'pending'): ?>
                                            <div class="mt-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="approve_order_id" value="<?= $o['id'] ?>">
                                                    <button name="approve_order" class="btn btn-sm btn-success" title="Xác nhận"><i
                                                            class="bi bi-check-lg"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline"
                                                    onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn này?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="cancel_order_id" value="<?= $o['id'] ?>">
                                                    <button name="cancel_order" class="btn btn-sm btn-danger" title="Hủy"><i
                                                            class="bi bi-x-lg"></i></button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: PRODUCTS -->
            <div id="products" class="tab-content">
                <h3 class="mb-4 text-success">Danh Sách Sản Phẩm</h3>
                <div class="card p-4 mb-4 border-0 shadow-sm">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="col-md-3">
                            <label class="form-label">Tên bánh</label>
                            <input type="text" name="ten_banh" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Loại</label>
                            <select name="loai" class="form-select">
                                <option value="ngot">Bánh ngọt</option>
                                <option value="man">Bánh mặn</option>
                                <option value="kem">Bánh kem</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="gia" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hình ảnh</label>
                            <input type="file" name="hinh_anh" class="form-control" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="featCheck">
                                <label class="form-check-label" for="featCheck">Nổi bật</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button name="add_product" class="btn btn-green"><i class="bi bi-plus-circle"></i> Thêm Sản
                                Phẩm</button>
                        </div>
                    </form>
                </div>

                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên</th>
                                <th>Loại</th>
                                <th>Giá</th>
                                <th>Nổi bật</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p):
                                $img = buildImageUrl($p['hinh_anh']); ?>
                                <tr>
                                    <td><img src="<?= $img['url'] ?>" width="50" height="50" style="object-fit:cover"
                                            class="rounded"></td>
                                    <td><?= htmlspecialchars($p['ten_banh']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $p['loai'] ?></span></td>
                                    <td><?= number_format($p['gia']) ?>đ</td>
                                    <td><?= $p['is_featured'] ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td>
                                    <td>
                                        <a href="?delete_product_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Ngừng bán sản phẩm này?')">
                                            <i class="bi bi-trash"></i>
                                        </a>


                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: USERS -->
            <div id="users" class="tab-content">
                <h3 class="mb-4 text-success">Khách Hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: PROMOTIONS -->
            <div id="promotions" class="tab-content">
                <h3 class="mb-4 text-success">Chương Trình Khuyến Mãi</h3>
                <form method="POST" class="card p-3 border-0 shadow-sm mb-3 row g-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-md-4">
                        <select name="banh_id" class="form-select">
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['ten_banh']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" name="gia_khuyen_mai" class="form-control"
                            placeholder="Giá KM" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_bat_dau" class="form-control" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_ket_thuc" class="form-control" required></div>
                    <div class="col-md-1"><button name="add_promotion" class="btn btn-green w-100"><i
                                class="bi bi-plus-lg"></i></button></div>
                </form>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Giá KM</th>
                                <th>Thời gian</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($promo['ten_banh']) ?></td>
                                    <td><?= number_format($promo['gia_khuyen_mai']) ?>đ</td>
                                    <td><?= date('d/m', strtotime($promo['ngay_bat_dau'])) ?> ->
                                        <?= date('d/m', strtotime($promo['ngay_ket_thuc'])) ?>
                                    </td>
                                    <td><a href="?delete_promotion_id=<?= $promo['id'] ?>" class="text-danger"
                                            onclick="return confirm('Xóa?')"><i class="bi bi-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 6: BLOGS -->
            <div id="blogs" class="tab-content">
                <h3 class="mb-4 text-success">Quản Lý Bài Viết</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Tiêu đề</th>
                                <th>Tác giả</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blogs as $blog): ?>
                                <tr>
                                    <td><?= htmlspecialchars($blog['title']) ?></td>
                                    <td><?= htmlspecialchars($blog['username']) ?></td>
                                    <td>
                                        <?php
                                        $blogStatus = match (strtolower($blog['status'])) {
                                            'approved' => ['badge' => 'success', 'label' => 'Đã duyệt'],
                                            'rejected' => ['badge' => 'danger', 'label' => 'Từ chối'],
                                            'pending' => ['badge' => 'warning', 'label' => 'Chờ duyệt'],
                                            default => ['badge' => 'secondary', 'label' => ucfirst($blog['status'])]
                                        };
                                        ?>
                                        <span class="badge bg-<?= $blogStatus['badge'] ?>"><?= $blogStatus['label'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($blog['status'] == 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                                                <button name="approve_blog" class="btn-action btn-green" title="Duyệt"><i
                                                        class="bi bi-check"></i></button>
                                                <button name="reject_blog" class="btn-action btn-delete" title="Từ chối"><i
                                                        class="bi bi-x"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- End Main Content -->

        <!-- JAVASCRIPT LOGIC -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

        <script>
            // Global Toast Logic
            window.showToast = function (msg, type = 'success') {
                let color = "#333";
                if (type === 'success') color = "linear-gradient(to right, #00b09b, #96c93d)";
                if (type === 'error') color = "linear-gradient(to right, #ff5f6d, #ffc371)";
                if (type === 'warning') color = "linear-gradient(to right, #f59e0b, #facc15)";

                Toastify({
                    text: msg,
                    duration: 3500,
                    close: true,
                    gravity: "top",
                    position: "right",
                    stopOnFocus: true,
                    style: {
                        background: color,
                        borderRadius: "10px",
                        fontWeight: "600",
                        boxShadow: "0 5px 15px rgba(0,0,0,0.1)"
                    }
                }).showToast();
            };

            <?php if (isset($_SESSION['admin_toast'])): ?>
                showToast("<?= $_SESSION['admin_toast']['msg'] ?>", "<?= $_SESSION['admin_toast']['type'] ?>");
                <?php unset($_SESSION['admin_toast']); ?>
            <?php endif; ?>

            // 1. Logic chuyển Tab
            function showTab(evt, tabName) {
                // Ẩn tất cả tab
                var tabContent = document.getElementsByClassName("tab-content");
                for (var i = 0; i < tabContent.length; i++) {
                    tabContent[i].classList.remove("active");
                }
                // Bỏ active ở link sidebar
                var navLinks = document.getElementsByClassName("nav-link");
                for (var i = 0; i < navLinks.length; i++) {
                    navLinks[i].classList.remove("active");
                }
                // Hiện tab được chọn
                document.getElementById(tabName).classList.add("active");
                evt.currentTarget.classList.add("active");
            }

            // 2. Vẽ biểu đồ Chart.js (Chỉ chạy khi đã login)
            document.addEventListener("DOMContentLoaded", function () {
                const chartCanvas = document.getElementById('revenueChart');
                if (chartCanvas) {
                    const ctx = chartCanvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= $js_dates ?>, // Dữ liệu ngày từ PHP
                            datasets: [{
                                label: 'Doanh thu (VNĐ)',
                                data: <?= $js_revenues ?>, // Dữ liệu tiền từ PHP
                                backgroundColor: 'rgba(46, 125, 50, 0.6)', // Màu xanh pastel đậm
                                borderColor: 'rgba(46, 125, 50, 1)',
                                borderWidth: 1,
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function (value) { return value.toLocaleString('vi-VN') + 'đ'; } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) { return context.raw.toLocaleString('vi-VN') + ' VNĐ'; }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>

    <?php endif; ?>
</body>

</html>
<?php $conn->close(); ?>