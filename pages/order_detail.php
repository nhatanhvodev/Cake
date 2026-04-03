<?php
session_start();
require_once '../config/connect.php';
if ($conn->connect_error) die("Lỗi DB");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ===== LẤY ĐƠN HÀNG ===== */
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die("Không có quyền xem đơn này");

/* ===== TRẠNG THÁI ===== */
$status = strtolower($order['status']);
$statusClass = match ($status) {
    'completed', 'thanh cong' => 'success',
    'cancelled', 'huy'       => 'danger',
    default                  => 'warning'
};

/* ===== CHI TIẾT BÁNH ===== */
$stmt = $conn->prepare("
    SELECT b.ten_banh, oi.quantity, oi.price
    FROM order_items oi
    JOIN banh b ON oi.banh_id = b.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng #<?= $order_id ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
/* ===== BACKGROUND ===== */
body{
    background-color:#e8f5f1;
    background-image:
        radial-gradient(circle at 10% 15%, rgba(255,255,255,.6) 0 40px, transparent 41px),
        radial-gradient(circle at 80% 20%, rgba(255,255,255,.5) 0 35px, transparent 36px),
        radial-gradient(circle at 30% 80%, rgba(255,255,255,.5) 0 45px, transparent 46px),
        url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
}

/* ===== WRAP ===== */
.bill-wrap{
    max-width:1000px;
    margin:40px auto;
    background:rgba(255,255,255,.45);
    backdrop-filter: blur(10px);
    border-radius:26px;
    padding:30px;
}

/* ===== INFO CARD ===== */
.info-card{
    background:#fff;
    border-radius:18px;
    padding:24px;
    position:relative;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    margin-bottom:30px;
}
.info-card::before{
    content:"<i class="fa-solid fa-receipt" style="color: #8b4513;"></i>";
    position:absolute;
    top:-18px;
    left:20px;
    width:44px;height:44px;
    border-radius:50%;
    background:linear-gradient(135deg,#ffb6c1,#ffd6dc);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    box-shadow:0 6px 16px rgba(0,0,0,.18);
}

/* ===== TABLE ===== */
.table{
    background:#fff;
    border-radius:16px;
    overflow:hidden;
}
.table thead{
    background:#dff0ea;
    color:#355e4f;
}

/* ===== TOTAL ===== */
.total-box{
    background:#fff;
    border-radius:16px;
    padding:16px 20px;
    text-align:right;
    font-size:18px;
    font-weight:700;
    box-shadow:0 6px 16px rgba(0,0,0,.08);
}

/* ===== MODAL ===== */
.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    justify-content:center;
    align-items:center;
    z-index:1000;
}
.modal.show{ display:flex; }
.modal-box{
    background:#fff;
    width:90%;
    max-width:420px;
    border-radius:18px;
    padding:20px;
    text-align:center;
    animation:zoomIn .3s ease;
}
@keyframes zoomIn{
    from{transform:scale(.7);opacity:0}
    to{transform:scale(1);opacity:1}
}

/* ===== PRINT ===== */
@media print{
    body{background:#fff!important}
    .bill-wrap{background:#fff;box-shadow:none;padding:0}
    button,.modal{display:none!important}
}
</style>
</head>

<body>

<div class="bill-wrap">

<h4 class="fw-bold mb-4">
    <i class="fa-solid fa-receipt" style="color: #8b4513;"></i> Chi tiết đơn hàng 
    <span class="badge bg-secondary">#<?= $order_id ?></span>
</h4>

<!-- INFO -->
<div class="info-card">
    <p><strong><i class="fa-regular fa-user" style="color: #8b4513;"></i> Người nhận:</strong> <?= htmlspecialchars($order['recipient_name']) ?></p>
    <p><strong><i class="fa-solid fa-phone" style="color: #8b4513;"></i> SĐT:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong><i class="fa-solid fa-location-dot" style="color: #d32f2f;"></i> Địa chỉ:</strong> <?= htmlspecialchars($order['address']) ?></p>

    <iframe
        width="100%"
        height="180"
        style="border-radius:14px;border:0;margin:10px 0"
        loading="lazy"
        src="https://www.google.com/maps?q=<?= urlencode($order['address']) ?>&output=embed">
    </iframe>

    <p><strong><i class="fa-regular fa-calendar-days" style="color: #8b4513;"></i> Ngày đặt:</strong> <?= date("d/m/Y H:i", strtotime($order['created_at'])) ?></p>
    <p>
        <strong><i class="fa-solid fa-thumbtack" style="color: #ff6b9c;"></i> Trạng thái:</strong>
        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($order['status']) ?></span>
    </p>
    <p><strong><i class="fa-regular fa-credit-card" style="color: #8b4513;"></i> Thanh toán:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
</div>

<!-- TABLE -->
<div class="table-responsive mb-3">
<table class="table align-middle">
<thead>
<tr>
    <th><i class="fa-solid fa-cake-candles" style="color: #ff6b9c;"></i> Tên bánh</th>
    <th>Số lượng</th>
    <th>Giá</th>
    <th>Thành tiền</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $it): ?>
<tr>
    <td><?= htmlspecialchars($it['ten_banh']) ?></td>
    <td><?= $it['quantity'] ?></td>
    <td><?= number_format($it['price']) ?> đ</td>
    <td class="fw-bold text-danger">
        <?= number_format($it['price']*$it['quantity']) ?> đ
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="total-box mb-3">
    Tổng tiền:
    <span class="text-success"><?= number_format($order['total_amount']) ?> đ</span>
</div>

<!-- BUTTONS -->
<div class="d-flex justify-content-end gap-2">
    <button class="btn btn-outline-primary btn-sm" onclick="openBill()"><i class="fa-regular fa-eye" style="color: #8b4513;"></i> Xem nhanh</button>
    <button class="btn btn-outline-success btn-sm" onclick="window.print()"><i class="fa-solid fa-print" style="color: #8b4513;"></i> In</button>
    <button class="btn btn-outline-danger btn-sm" onclick="exportPDF()">📄 PDF</button>
    <a href="/Cake/pages/thongtinkh.php" class="btn btn-secondary btn-sm">← Quay lại</a>
</div>

</div>

<!-- MODAL -->
<div id="billModal" class="modal">
<div class="modal-box">
    <h5><i class="fa-solid fa-receipt" style="color: #8b4513;"></i> Hóa đơn #<?= $order_id ?></h5>
    <p><?= htmlspecialchars($order['recipient_name']) ?></p>
    <hr>
    <?php foreach($items as $it): ?>
        <p><?= $it['ten_banh'] ?> × <?= $it['quantity'] ?>
        = <strong><?= number_format($it['price']*$it['quantity']) ?> đ</strong></p>
    <?php endforeach; ?>
    <hr>
    <h6>Tổng: <?= number_format($order['total_amount']) ?> đ</h6>
    <button class="btn btn-secondary btn-sm mt-3" onclick="closeBill()">Đóng</button>
</div>
</div>

<script>
function openBill(){ document.getElementById('billModal').classList.add('show'); }
function closeBill(){ document.getElementById('billModal').classList.remove('show'); }

function exportPDF(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Hoa don #<?= $order_id ?>",20,20);
    doc.text("Nguoi nhan: <?= $order['recipient_name'] ?>",20,30);
    let y=45;
    <?php foreach($items as $it): ?>
        doc.text("<?= $it['ten_banh'] ?> x <?= $it['quantity'] ?>",20,y);
        doc.text("<?= number_format($it['price']*$it['quantity']) ?> đ",150,y);
        y+=10;
    <?php endforeach; ?>
    doc.text("Tong tien: <?= number_format($order['total_amount']) ?> đ",20,y+10);
    doc.save("hoa-don-<?= $order_id ?>.pdf");
}
</script>

</body>
</html>
