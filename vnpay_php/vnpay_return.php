<?php
require_once("config.php");
require_once("../config/connect.php");

$vnp_SecureHash = $_GET['vnp_SecureHash'];
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Kết quả thanh toán VNPAY</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #e8f5f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }

        h2 {
            color: #457762;
        }

        .success {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }

        .error {
            color: #dc3545;
            font-size: 60px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #ff6b9c;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 20px;
            transition: 0.3s;
        }

        .btn:hover {
            background: #ff8fb3;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php
        if ($secureHash == $vnp_SecureHash) {
            $order_id = $_GET['vnp_TxnRef'];
            if ($_GET['vnp_ResponseCode'] == '00') {
                // Thành công
                $stmt = $conn->prepare("UPDATE orders SET status = 'pending' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                echo "<div class='success'>✅</div>";
                echo "<h2>Thanh toán thành công!</h2>";
                echo "<p>Mã đơn hàng: <strong>#" . htmlspecialchars($order_id) . "</strong></p>";
                echo "<p>Số tiền: <strong>" . number_format($_GET['vnp_Amount'] / 100, 0, ',', '.') . " VNĐ</strong></p>";
                echo "<p>Đơn hàng của bạn đã được ghi nhận và đang chờ Admin xác nhận.</p>";
            } else {
                // Thất bại
                $stmt = $conn->prepare("UPDATE orders SET status = 'failed' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                echo "<div class='error'>❌</div>";
                echo "<h2>Thanh toán thất bại!</h2>";
                echo "<p>Mã lỗi: " . htmlspecialchars($_GET['vnp_ResponseCode']) . "</p>";
                echo "<p>Đơn hàng của bạn đã bị hủy hoặc thanh toán không thành công.</p>";
            }
        } else {
            echo "<div class='error'>⚠️</div>";
            echo "<h2>Chữ ký không hợp lệ!</h2>";
            echo "<p>Hệ thống có thể đang gặp sự cố bảo mật.</p>";
        }
        ?>
        <a href="../index.php" class="btn">Quay lại trang chủ</a>
    </div>
</body>

</html>