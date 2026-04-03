<?php
session_start(); //

/* =================================================================================
   PHẦN 1: KẾT NỐI DB & KHỞI TẠO
   ================================================================================= */
require_once '../config/connect.php';
//
$conn->set_charset("utf8mb4"); //

if ($conn->connect_error) {
    die("Lỗi kết nối DB: " . $conn->connect_error); //
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); //
    exit;
}

$user_id = (int) $_SESSION['user_id']; //
$error = '';
$success = '';

// Lấy thông báo từ session (nếu có)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']); //
}

/* =================================================================================
   PHẦN 2: XỬ LÝ FORM (POST REQUESTS)
   ================================================================================= */

// Lấy thông tin người dùng hiện tại để xử lý logic
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?"); //
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user)
    die("Không tìm thấy thông tin người dùng."); //

// --- LOGIC 1: CẬP NHẬT THÔNG TIN & AVATAR ---
if (isset($_POST['update_profile'])) {
    $ten = trim($_POST['ten']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $avatar_name = $user['avatar']; // Mặc định giữ ảnh cũ

    // Xử lý Upload Ảnh
    if (!empty($_FILES['avatar']['name'])) { //
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allow)) { //
            $new_name = time() . '_' . $user_id . '.' . $ext;
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true); //

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_name)) {
                $avatar_name = $new_name; //
            } else {
                $error = "Không thể lưu file ảnh.";
            }
        } else {
            $error = "Định dạng ảnh không hợp lệ (Chỉ nhận JPG, PNG, WEBP)."; //
        }
    }

    // Cập nhật DB nếu không có lỗi upload
    if (empty($error)) {
        $sql_update = "UPDATE users SET username=?, email=?, phone=?, avatar=? WHERE id=?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssssi", $ten, $email, $phone, $avatar_name, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "🎉 Cập nhật hồ sơ thành công!";
            $_SESSION['username'] = $ten;
            $_SESSION['avatar'] = $avatar_name;
            header("Location: thongtinkh.php");
            exit;
        } else {
            $error = "Lỗi hệ thống: " . $conn->error;
        }
        $stmt->close();
    }
}

// --- LOGIC 2: ĐỔI MẬT KHẨU ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (!password_verify($old_pass, $user['password'])) {
        $error = "Mật khẩu hiện tại không đúng.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Mật khẩu xác nhận không trùng khớp.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Mật khẩu mới quá ngắn (tối thiểu 6 ký tự).";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "🔐 Đổi mật khẩu thành công!";
            header("Location: thongtinkh.php");
            exit;
        } else {
            $error = "Lỗi khi cập nhật mật khẩu.";
        }
        $stmt->close();
    }
}

/* =================================================================================
   PHẦN 3: LẤY DỮ LIỆU HIỂN THỊ (GET REQUESTS)
   ================================================================================= */

// 1. Lấy Lịch sử đơn hàng
$stmt = $conn->prepare("SELECT id, total_amount, status, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC"); //
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2. Lấy Blog đã viết
$stmt = $conn->prepare("SELECT id, title, created_at FROM blogs WHERE author_id=? ORDER BY created_at DESC"); //
$stmt->bind_param("i", $user_id);
$stmt->execute();
$blogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang cá nhân - <?= htmlspecialchars($user['username']) ?></title> <!-- -->

    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        /* --- GIAO DIỆN SWEET / CAKE THEME --- */
        body {
            background-color: #e8f5f1;
            background-image:
                radial-gradient(circle at 15% 20%, rgba(255, 255, 255, .6) 0 40px, transparent 41px),
                radial-gradient(circle at 80% 30%, rgba(255, 255, 255, .5) 0 35px, transparent 36px),
                radial-gradient(circle at 30% 85%, rgba(255, 255, 255, .5) 0 45px, transparent 46px),
                url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
            background-repeat: repeat;
            background-size: auto, auto, auto, 220px 220px;
        }

        /* Sidebar Profile Card */
        .profile-card {
            background: #fff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(69, 119, 98, .12);
            border: 2px solid #d9efe7;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .profile-card::before {
            content: "<i class=" fa-solid fa-cookie-bite" style=" color: #ff6b9c;
            "></i>";
            position: absolute;
            top: -18px;
            left: 24px;
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #ffb6c1, #ffd6dc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
        }

        .profile-card::after {
            content: "<i class=" fa-solid fa-cake-candles" style=" color: #ff6b9c;
            "></i> <i class=" fa-solid fa-circle-notch" style=" color: #ff6b9c;
            "></i> <i class=" fa-solid fa-candy-cane" style=" color: #ff6b9c;
            "></i> <i class=" fa-brands fa-raspberry-pi" style=" color: #d32f2f;
            "></i>";
            position: absolute;
            bottom: -12px;
            right: -12px;
            font-size: 42px;
            opacity: .08;
            transform: rotate(-10deg);
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .15);
            margin-bottom: 14px;
        }

        .profile-card h4 {
            font-weight: 700;
            color: #355e4f;
        }

        /* Main Content Area */
        .content-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 10px 30px rgba(69, 119, 98, .12);
            border: 2px solid #d9efe7;
            min-height: 400px;
        }

        /* Nav Tabs Custom */
        .nav-tabs {
            border-bottom: none;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 12px;
            font-weight: 600;
            color: #457762;
            background: #f0faf6;
            margin-right: 10px;
            padding: 10px 18px;
            transition: .25s;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #457762, #5fae92);
            color: #fff;
            box-shadow: 0 6px 18px rgba(69, 119, 98, .25);
        }

        .section-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .table {
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead {
            background: #f0faf6;
            color: #355e4f;
        }
    </style>
</head>

<body>

    <?php if (file_exists("header.php"))
        include '../includes/header.php'; ?>
    <?php if (file_exists("navbar.php"))
        include "navbar.php"; ?> <!-- -->

    <div class="container my-5">

        <script>
            window.showToast = function (msg, type = 'info') {
                let color = "#333";
                if (type === 'success') color = "linear-gradient(to right, #00b09b, #96c93d)";
                if (type === 'error') color = "linear-gradient(to right, #ff5f6d, #ffc371)";
                Toastify({ text: msg, duration: 3000, gravity: "top", position: "right", style: { background: color } }).showToast();
            }

            <?php if ($success): ?>
                window.showToast("<?= $success ?>", "success");
            <?php endif; ?>
            <?php if ($error): ?>
                window.showToast("<?= $error ?>", "error");
            <?php endif; ?>
        </script>

        <!-- Nút Quay lại Trang Chủ -->
        <div class="mb-4">
            <a href="/Cake/index.php" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 12px; border: 2px solid #d9efe7; background: #fff; color: #457762; font-weight: 600;">
                <i class="fa-solid fa-house"></i> Quay lại trang chủ
            </a>
        </div>

        <div class="row g-4">
            <!-- CỘT TRÁI: THÔNG TIN TÓM TẮT & LOGOUT -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <img src="uploads/<?= !empty($user['avatar']) ? $user['avatar'] : 'default.png' ?>"
                        class="profile-avatar">
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['username']) ?></h4>
                    <p class="text-muted mb-3"><?= htmlspecialchars($user['email']) ?></p>

                    <!-- Thống kê -->
                    <div class="d-flex justify-content-center gap-3 text-start mt-4">
                        <div class="px-3 border-end">
                            <span class="d-block fw-bold fs-5 text-primary"><?= count($orders) ?></span>
                            <small class="text-secondary">Đơn hàng</small>
                        </div>
                        <div class="px-3">
                            <span class="d-block fw-bold fs-5 text-danger"><?= count($blogs) ?></span>
                            <small class="text-secondary">Bài viết</small>
                        </div>
                    </div>

                    <!-- Nút Đăng Xuất -->
                    <form action="logout.php" method="POST" class="mt-4">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                        </button>
                    </form>
                </div>
            </div>

            <!-- CỘT PHẢI: TAB CHỨC NĂNG -->
            <div class="col-lg-8">
                <div class="content-card">
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#orders-tab">
                                <i class="fa-solid fa-receipt"></i> Đơn hàng
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#blogs-tab">
                                <i class="fa-solid fa-pen-nib"></i> Blog của tôi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings-tab">
                                <i class="fa-solid fa-gear"></i> Cài đặt
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- TAB 1: LỊCH SỬ ĐƠN HÀNG -->
                        <div class="tab-pane fade show active" id="orders-tab">
                            <h5 class="section-title">Lịch sử mua hàng</h5>
                            <?php if (count($orders) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Mã ĐH</th>
                                                <th>Ngày đặt</th>
                                                <th>Tổng tiền</th>
                                                <th>Trạng thái</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $o): ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary">#<?= $o['id'] ?></span></td>
                                                    <td><?= date("d/m/Y", strtotime($o['created_at'])) ?></td>
                                                    <td class="fw-bold text-success"><?= number_format($o['total_amount']) ?> đ
                                                    </td>
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
                                                        <span class="badge bg-<?= $statusData['badge'] ?>">
                                                            <?= $statusData['label'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="/Cake/pages/order_detail.php?id=<?= $o['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">Xem</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-cart-arrow-down fa-3x mb-3"></i>
                                    <p>Bạn chưa có đơn hàng nào.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: BLOG ĐÃ VIẾT -->
                        <div class="tab-pane fade" id="blogs-tab">
                            <h5 class="section-title">Bài viết đã chia sẻ</h5>
                            <?php if (count($blogs) > 0): ?>
                                <ul class="list-group list-group-flush" id="blogList">
                                    <?php foreach ($blogs as $b): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?= htmlspecialchars($b['title']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="fa-regular fa-clock"></i>
                                                    <?= date("d/m/Y H:i", strtotime($b['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $blogStatus = match (strtolower($b['status'])) {
                                                    'approved' => ['badge' => 'success', 'label' => 'Đã duyệt'],
                                                    'rejected' => ['badge' => 'danger', 'label' => 'Từ chối'],
                                                    'pending' => ['badge' => 'warning', 'label' => 'Chờ duyệt'],
                                                    default => ['badge' => 'secondary', 'label' => ucfirst($b['status'])]
                                                };
                                                ?>
                                                <span class="badge bg-<?= $blogStatus['badge'] ?> me-3"><?= $blogStatus['label'] ?></span>
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteBlog(<?= $b['id'] ?>, this)">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-newspaper fa-3x mb-3"></i>
                                    <p>Bạn chưa viết bài blog nào.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 3: CÀI ĐẶT -->
                        <div class="tab-pane fade" id="settings-tab">
                            <div class="row">
                                <!-- Form Cập nhật thông tin -->
                                <div class="col-md-12 mb-4">
                                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-pen"></i> Cập nhật
                                        thông tin</h6>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">Ảnh đại diện mới</label>
                                                <input type="file" name="avatar" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Họ và tên</label>
                                                <input type="text" name="ten" class="form-control"
                                                    value="<?= htmlspecialchars($user['username']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small text-muted">Số điện thoại</label>
                                                <input type="text" name="phone" class="form-control"
                                                    value="<?= htmlspecialchars($user['phone']) ?>">
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">Email</label>
                                                <input type="email" name="email" class="form-control"
                                                    value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="update_profile"
                                                    class="btn btn-primary btn-sm px-4">Lưu thay đổi</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <hr>
                                <!-- Form Đổi mật khẩu -->
                                <div class="col-md-12">
                                    <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-shield-halved"></i> Bảo
                                        mật</h6>
                                    <form method="POST">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Mật khẩu cũ</label>
                                                <input type="password" name="old_password" class="form-control"
                                                    required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Mật khẩu mới</label>
                                                <input type="password" name="new_password" class="form-control"
                                                    required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Xác nhận</label>
                                                <input type="password" name="confirm_password" class="form-control"
                                                    required>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="change_password"
                                                    class="btn btn-danger btn-sm px-4">Đổi mật khẩu</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div> <!-- End Tab Content -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (file_exists("footer.html"))
        include '../includes/footer.html'; ?> <!-- -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script Xử lý xóa Blog bằng Ajax -->
    <script>
        function deleteBlog(blogId, btn) {
            // Use custom confirm

            fetch("blog_delete.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + encodeURIComponent(blogId)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.closest(".post, .list-group-item").remove();
                        window.showToast(data.message, 'info');
                    } else {
                        window.showToast(data.message, 'info');
                    }
                })
                .catch(() => {
                    window.showToast("Không kết nối được server", 'error');
                });
        }

        // Hàm hiển thị thông báo động
        function showAlert(message, type) {
            const alertBox = document.createElement("div");
            alertBox.className = `alert alert-${type} alert-dismissible fade show shadow-sm position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertBox.style.zIndex = "1050";
            alertBox.innerHTML = `
            <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'circle-exclamation'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
            document.body.appendChild(alertBox);

            // Tự động tắt sau 3 giây
            setTimeout(() => alertBox.remove(), 3000);
        }
    </script>
</body>

</html>