
<?php

session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once 'config/connect.php';

$cartItems = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $sql = "
        SELECT c.*, b.ten_banh, b.hinh_anh, b.gia
        FROM cart c
        JOIN banh b ON c.banh_id = b.id
        WHERE c.user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function buildImageUrl(?string $path): string {
    if (!$path) return '/Cake/assets/img/no-image.jpg';
    if (strpos($path, 'assets/') === false && strpos($path, 'img/') === 0) {
        $path = str_replace('img/', 'assets/img/', $path);
    }
    return '/Cake/' . ltrim($path, '/');
}

function timeAgo($timestamp) {
    if (!$timestamp) return 'Không rõ thời gian';
    $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return floor($diff/60) . ' phút trước';
    if ($diff < 86400) return floor($diff/3600) . ' giờ trước';
    if ($diff < 172800) return 'Hôm qua';
    return date('d/m/Y', $time);
}

$isLoggedIn   = isset($_SESSION['user_id']);
$loggedInUser = $_SESSION['username'] ?? 'Khách';

if (isset($_POST['search_products'])) {
    header('Content-Type: application/json');
    $kw = trim($_POST['keyword']);
    $stmt = $conn->prepare("SELECT id, ten_banh, gia, hinh_anh FROM banh WHERE ten_banh LIKE ? LIMIT 5");
    $searchKw = "%$kw%";
    $stmt->bind_param("s", $searchKw);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($result as &$item) {
        $item['hinh_anh'] = buildImageUrl($item['hinh_anh']);
        $item['formatted_price'] = number_format($item['gia'], 0, ',', '.') . ' VNĐ';
    }
    
    echo json_encode(['success' => true, 'products' => $result]);
    exit;
}

if (isset($_POST['add_to_cart'])) {
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để mua hàng']);
        exit;
    }

    $banh_id = isset($_POST['banh_id']) ? (int)$_POST['banh_id'] : 0;
    $qty     = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $user_id = (int)$_SESSION['user_id'];
    
    if ($banh_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
        exit;
    }

    $sql = "INSERT INTO cart (user_id, banh_id, quantity) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống (SQL): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("iii", $user_id, $banh_id, $qty);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '<i class="fa-solid fa-cart-shopping" style="color: #ff6b9c;"></i> Đã thêm vào giỏ hàng thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm vào giỏ: ' . $stmt->error]);
    }
    exit;
}

$slides = [
    [
        'title' => 'Tết Đoàn Viên',
        'sub'   => 'Bánh thủ công tinh túy – trọn vị yêu thương cho mùa đoàn viên.',
        'img_noi' => 'assets/img/banhngot/i6.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #fff5f5, #ffe0e0)',
        'accent' => '#ff4d4d',
        'cta' => 'Khám phá ngay'
    ],
    [
        'title' => 'Ngọt Ngào Đam Mê',
        'sub'   => 'Hương vị Sô-cô-la bản sắc cho những khoảnh khắc thăng hoa.',
        'img_noi' => 'assets/img/banhngot/i7.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #fff0f6, #ffdae9)',
        'accent' => '#ff3385',
        'cta' => 'Mua quà ngọt'
    ],
    [
        'title' => 'Tươi Mới Mỗi Ngày',
        'sub'   => 'Thưởng thức vị tươi mát từ trái cây tự nhiên trên nền kem mịn.',
        'img_noi' => 'assets/img/banhngot/i8.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #f0f7ff, #d9e9ff)',
        'accent' => '#3399ff',
        'cta' => 'Thử ngay'
    ]
];

$res_prod = $conn->query("SELECT * FROM banh WHERE is_featured=1 ORDER BY id DESC LIMIT 10");
$products = ($res_prod) ? $res_prod->fetch_all(MYSQLI_ASSOC) : [];

$sql_review = "SELECT * FROM reviews ORDER BY timestamp DESC LIMIT 4";
$res_review = $conn->query($sql_review);
$reviews    = ($res_review) ? $res_review->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gấu Bakery - Trang Chủ</title>
    <!-- Fonts: Playfair Display for Titles, Outfit for Body -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <style>
        
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Outfit', sans-serif; color: #333; background-color: #e8f5f1;
            background-image:
                radial-gradient(circle at 10% 15%, rgba(255,255,255,.6) 0 40px, transparent 41px),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,.5) 0 35px, transparent 36px),
                url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
        }
        a { text-decoration: none; color: inherit; }
        .btn-primary { background: #ff6b9c; color: #fff; padding: 12px 32px; border-radius: 30px; font-weight: 600; transition: .3s; cursor: pointer; border:none; }
        .btn-primary:hover { background: #e05585; transform: translateY(-2px); }

        /* Banner Slider Styles */
        #banner { padding: 40px 20px; max-width: 1240px; margin: 0 auto; }
        .slider-container { position: relative; width: 100%; border-radius: 40px; overflow: hidden; box-shadow: 0 40px 100px rgba(0,0,0,0.1); }
        .slider-track { display: flex; transition: transform 0.6s cubic-bezier(0.65, 0, 0.35, 1); will-change: transform; }
        
        .slide-item { flex: 0 0 100%; width: 100%; min-height: 480px; display: flex; align-items: center; padding: 60px 80px; position: relative; }
        
        .banner-content { flex: 1; max-width: 50%; z-index: 5; position: relative; }
        .banner-card { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); padding: 40px; border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .hero-title { font-family: 'Playfair Display', serif; font-size: 64px; font-weight: 900; line-height: 1.1; margin-bottom: 20px; color: #222; letter-spacing: -0.5px; }
        .banner-sub { font-size: 18px; color: #555; line-height: 1.6; margin-bottom: 30px; }
        .banner-cta { display: inline-flex; align-items: center; gap: 10px; background: #333; color: #fff; padding: 14px 36px; border-radius: 50px; font-weight: 600; font-size: 16px; transition: 0.3s; box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
        .banner-cta:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.2); background: #000; }
        
        .banner-image { flex: 1; display: flex; justify-content: center; align-items: center; z-index: 2; position: relative; }
        .img-wrap { position: relative; width: 100%; max-width: 480px; filter: drop-shadow(0 30px 60px rgba(0,0,0,0.15)); animation: floatImg 6s ease-in-out infinite; }
        .img-wrap img { width: 100%; object-fit: contain; }
        
        /* Subtle Floating Decorations */
        .decor { position: absolute; font-size: 40px; opacity: 0.15; pointer-events: none; }
        
        .slider-nav { position: absolute; bottom: 30px; left: 80px; display: flex; align-items: center; gap: 15px; z-index: 10; }
        .slider-dots { display: flex; gap: 8px; }
        .dot { width: 40px; height: 6px; background: rgba(0,0,0,0.1); border-radius: 10px; cursor: pointer; transition: 0.3s; }
        .dot.active { width: 60px; background: #333; }
        
        .slider-arrows { display: flex; gap: 10px; margin-left: 20px; }
        .nav-btn { width: 44px; height: 44px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.6); backdrop-filter: blur(5px); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.3s; }
        .nav-btn:hover { background: #333; border-color: #333; color: #fff; }

        @keyframes floatImg { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-15px) rotate(2deg); } }
        
        @media (max-width: 900px) {
            .slide-item { flex-direction: column; text-align: center; padding: 40px 20px 80px; }
            .banner-content { max-width: 100%; margin-bottom: 40px; }
            .hero-title { font-size: 38px; }
            .banner-image { width: 100%; }
            .slider-nav { left: 50%; transform: translateX(-50%); bottom: 20px; }
        }

        #products, #reviews { max-width: 1200px; margin: 60px auto; padding: 0 20px; }
        .products-decor { position: relative; padding: 90px 24px; border-radius: 40px; overflow: hidden; background: linear-gradient(135deg, #f7fffc 0%, #f1fbf7 35%, #fff6f9 100%); box-shadow: 0 40px 80px rgba(63,127,101,.18); }
        .section-title { font-family: 'Playfair Display', serif; text-align: center; font-size: 38px; font-weight: 800; margin-bottom: 30px; position: relative; z-index: 2; color: #222; }
        
        .product-list { 
            display: flex; gap: 20px; overflow-x: auto; padding: 10px 5px 30px; 
            scroll-snap-type: x mandatory; scrollbar-width: thin; scrollbar-color: #ff6b9c #f0f0f0;
            -webkit-overflow-scrolling: touch; cursor: grab;
        }
        .product-list:active { cursor: grabbing; }
        .product-list::-webkit-scrollbar { height: 6px; }
        .product-list::-webkit-scrollbar-thumb { background: #ff6b9c; border-radius: 10px; }
        .product-list::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 10px; }

        .product-card { 
            min-width: 250px; scroll-snap-align: start; background: rgba(255,255,255,.94); 
            border-radius: 20px; padding: 20px 15px; text-align: center; 
            box-shadow: 0 10px 25px rgba(0,0,0,.06); transition: .3s; 
        }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,.1); }
        .product-card img { width: 100%; height: 180px; object-fit: cover; border-radius: 16px; margin-bottom: 15px; }
        .product-card h3 { font-family: 'Playfair Display', serif; font-size: 19px; font-weight: 700; margin: 4px 0 10px; color: #222; }
        .price { color: #ff6b9c; font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 17px; margin-bottom: 12px; }
        .add-to-cart { 
            width: 100%; background: #3f7f65; color: #fff; border: none; padding: 11px; 
            border-radius: 24px; cursor: pointer; font-family: 'Outfit', sans-serif; font-weight: 600; 
            transition: .2s;
        }
        .add-to-cart:hover { background: #2c5c49; transform: scale(1.02); }

#reviews.products-decor {
    max-width: 1200px;
    margin: 70px auto;
    padding: 56px 28px 60px;
    border-radius: 42px;
    background: linear-gradient(
        135deg,
        #f7fffc 0%,
        #f1fbf7 35%,
        #fff6f9 100%
    );
    box-shadow: 0 40px 80px rgba(63,127,101,.18);
    position: relative;
}

#reviews .section-title {
    text-align: center;
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 32px;
    color: #333;
}

.review-list {
    display: flex;
    gap: 28px;
    overflow-x: auto;
    padding: 10px 8px 30px;
    scroll-snap-type: x mandatory;
    padding-bottom: 12px; 
}

.review-list::-webkit-scrollbar {
    height: 8px;
}
.review-list::-webkit-scrollbar-thumb {
    background: #f3b6c6;
    border-radius: 10px;
}

.review-card {
    scroll-snap-align: start;
    min-width: 280px;
    max-width: 280px;
    min-height: 160px;
    background: rgba(255,255,255,.95);
    border-radius: 22px;
    padding: 22px 22px 20px;
    box-shadow: 0 14px 34px rgba(0,0,0,.09);
    display: flex;
    flex-direction: column;
    transition: .3s ease;
}

.review-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 44px rgba(0,0,0,.12);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.review-user {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #444;
}

.review-stars {
    font-size: 14px;
    color: #ffb400;
}

.review-body {
    font-size: 14px;
    line-height: 1.65;
    color: #444;
    margin: 6px 0 16px;
    flex-grow: 1;
}

.review-date {
    font-size: 12px;
    color: #999;
    text-align: right;
    margin-top: auto;
}

.write-review-btn {
    position: absolute;
    top: 40px;
    right: 40px;
    background: #ff6f91;
    color: #fff;
    border-radius: 999px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: .3s;
}

.write-review-btn:hover {
    background: #ff4f78;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    #reviews.products-decor {
        padding: 60px 18px;
        border-radius: 30px;
    }

    #reviews .section-title {
        font-size: 26px;
        margin-bottom: 34px;
    }

    .review-card {
        min-width: 250px;
        max-width: 250px;
        min-height: 170px;
    }

    .write-review-btn {
        top: 22px;
        right: 22px;
        padding: 8px 14px;
        font-size: 13px;
    }
}

.contact-section {
    padding: 60px 0 70px;
    background: transparent;
}

.contact-box {
    max-width: 1200px;
    margin: 0 auto;
    padding: 56px 28px 60px;
    border-radius: 42px;

    background: linear-gradient(
        135deg,
        #f7fffc 0%,
        #f1fbf7 35%,
        #fff6f9 100%
    );

    box-shadow: 0 40px 80px rgba(63,127,101,.18);
}

.contact-header {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 36px;
}

.contact-header .section-title {
    margin: 0;
}

.map-container {
    width: 72%;
    max-width: 820px;
    height: 360px;
    margin: 0 auto;

    border-radius: 26px;
    overflow: hidden;
    background: #fff;

    box-shadow: 0 20px 45px rgba(0,0,0,0.15);
}

.map-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

@media (max-width: 768px) {
    .contact-section {
        padding: 50px 0 60px;
    }

    .contact-box {
        padding: 42px 18px 46px;
        border-radius: 30px;
    }

    .map-container {
        width: 95%;
        height: 300px;
    }
}

.decor-icon {
    position: absolute;
    font-size: 42px;
    opacity: 0.16;
    filter: blur(1.8px);
    animation: decorFloat 9s ease-in-out infinite;
    user-select: none;
}

.decor-icon:nth-child(1) { top: 6%;  left: 6%;  animation-delay: 0s; }   
.decor-icon:nth-child(2) { top: 14%; right: 18%; animation-delay: 1s; }  
.decor-icon:nth-child(3) { bottom: 22%; left: 10%; animation-delay: 2s; } 

.decor-icon:nth-child(4) { top: 36%; left: 2%;  animation-delay: 3s; }    
.decor-icon:nth-child(5) { bottom: 10%; right: 8%; animation-delay: 4s; } 
.decor-icon:nth-child(6) { top: 8%;  right: 34%; animation-delay: 5s; }   

.decor-icon:nth-child(7) { bottom: 34%; right: 2%; animation-delay: 6s; } 
.decor-icon:nth-child(8) { top: 52%; right: 18%; animation-delay: 7s; }   
.decor-icon:nth-child(9) { bottom: 6%; left: 36%; animation-delay: 8s; }  

@keyframes decorFloat {
    0%, 100% {
        transform: translate(0, 0);
    }
    50% {
        transform: translate(8px, -18px);
    }
}

.contact-wrapper,
.contact-card {
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .contact-decor {
        display: none;
    }
}

        .decor-icon { position: absolute; font-size: 38px; opacity: 0.18; filter: blur(1.5px); animation: decorFloat 8s ease-in-out infinite; }
        .decor-icon:nth-child(1) { top: 6%; left: 8%; animation-delay: 0s; }
        @keyframes decorFloat { 0%, 100% { transform: translateY(0) translateX(0); } 50% { transform: translateY(-18px) translateX(6px); } }

        .review-modal { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .review-box { background: #fff; width: 100%; max-width: 420px; padding: 26px; border-radius: 22px; text-align: center; }
        .review-box textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 14px; margin-bottom: 15px; }
        .stars i { font-size: 26px; color: #ddd; cursor: pointer; margin: 0 5px; }
        .stars i.active { color: #ffb703; }

        @media (max-width: 992px) { .contact-card { grid-template-columns: 1fr; padding: 40px 36px; } .map-card iframe { height: 320px; } }
        @media (max-width: 768px) { 
            .banner-wrap { flex-direction: column; text-align: center; padding: 30px; }
            .banner-content, .banner-image { max-width: 100%; }
            .banner-image img { height: 240px; width: 100%; }
        }
        @media (max-width: 576px) { .contact-wrapper { padding: 0 20px; } .contact-title { font-size: 28px; } }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section id="banner">
    <div class="slider-container">
        <div class="slider-track" id="sliderTrack">
            <?php foreach($slides as $index => $slide): ?>
                <div class="slide-item" style="background: <?= $slide['color'] ?>;">
                    <div class="banner-content">
                        <div class="banner-card">
                            <h1 class="hero-title"><?= $slide['title'] ?></h1>
                            <p class="banner-sub"><?= $slide['sub'] ?></p>
                            <a href="<?= $slide['link'] ?>" class="banner-cta" style="background: <?= $slide['accent'] ?>; box-shadow: 0 10px 20px <?= $slide['accent'] ?>4D;">
                                <?= $slide['cta'] ?> <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="banner-image">
                        <div class="img-wrap">
                            <img src="<?= buildImageUrl($slide['img_noi']) ?>" alt="Banner Image">
                        </div>
                        <i class="fa-solid fa-star decor" style="top: 15%; right: 20%;"></i>
                        <i class="fa-solid fa-clover decor" style="bottom: 20%; left: 10%;"></i>
                        <i class="fa-solid fa-heart decor" style="top: 25%; left: 5%;"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="slider-nav">
            <div class="slider-dots" id="sliderDots">
                <?php foreach($slides as $index => $slide): ?>
                    <div class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $index ?>)"></div>
                <?php endforeach; ?>
            </div>
            <div class="slider-arrows">
                <button class="nav-btn" onclick="prevSlide()"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="nav-btn" onclick="nextSlide()"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </div>
</section>

<section id="products" class="products-decor">
    <h2 class="section-title">Sản phẩm bán chạy</h2>
    <div style="text-align:center; color:#666; margin-bottom:15px;">
        <i class="fa-solid fa-arrow-right"></i> Kéo sang để xem thêm
    </div>
    <div class="product-list">
        <?php if(!empty($products)): ?>
            <?php foreach($products as $p): ?>
            <div class="product-card">
                <img src="<?= buildImageUrl($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_banh']) ?>">
                <h3><?= htmlspecialchars($p['ten_banh']) ?></h3>
                <span class="price"><?= number_format($p['gia'], 0, ',', '.') ?> VNĐ</span>
                <button class="add-to-cart" data-id="<?= $p['id'] ?>">
                    <i class="fa-solid fa-cart-plus"></i> Chọn mua
                </button>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; width:100%;">Đang cập nhật sản phẩm...</p>
        <?php endif; ?>
    </div>
</section>

<section id="reviews" class="products-decor">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
        <h2 class="section-title" style="margin: 0 auto;">Khách hàng nói gì?</h2>
        <button onclick="openReviewForm()" class="btn-primary">
            <i class="fa-solid fa-pen"></i> Viết đánh giá
        </button>
    </div>
    <div class="review-list">
        <?php if (!empty($reviews)): ?>
            <?php foreach($reviews as $r): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="review-user">
                        <i class="fa-solid fa-circle-user" style="color: #ff6b9c;"></i>
                        <b><?= htmlspecialchars($r['name']) ?></b>

                    </div>
                    <div class="stars-show" style="color: #ffb703;">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="<?= $i <= ($r['rating'] ?? 5) ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="review-body">
                    "<?= htmlspecialchars($r['text']) ?>"
                </div>
                <div class="review-date" style="font-size:12px; color:#999; text-align:right;">
                    <i class="fa-regular fa-clock"></i> <?= timeAgo($r['timestamp']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; color: #666;">
                <p>Chưa có đánh giá nào. Hãy là người đầu tiên chia sẻ cảm nhận!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="contact" class="contact-section">
    <div class="contact-box">

        <div class="contact-header">
            <h2 class="section-title">Liên hệ với chúng tôi</h2>
        </div>

        <div class="map-container">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.314521786187!2d106.69414421018399!3d10.787205258951303!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f005001ccb3%3A0xdccf506f9c7ac11f!2zR-G6pFUgQmFrZXJ5!5e0!3m2!1svi!2s!4v1746437061125!5m2!1svi!2s"
                loading="lazy"
                allowfullscreen
            ></iframe>
        </div>

    </div>
</section>

<div id="reviewModal" class="review-modal">
    <div class="review-box">
        <h3>Đánh giá của bạn</h3>
        <div class="stars">
            <i class="fa-solid fa-star" data-star="1"></i>
            <i class="fa-solid fa-star" data-star="2"></i>
            <i class="fa-solid fa-star" data-star="3"></i>
            <i class="fa-solid fa-star" data-star="4"></i>
            <i class="fa-solid fa-star" data-star="5"></i>
        </div>
        <textarea placeholder="Cảm nhận của bạn về bánh..." rows="4"></textarea>
        <div class="review-actions" style="display:flex; gap:10px; justify-content:center;">
            <button class="btn-primary" onclick="submitReview()">Gửi</button>
            <button class="btn-primary" style="background:#e0e0e0; color:#333;" onclick="closeReviewForm()">Hủy</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.html'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    $("#searchInput").on("keyup", function () {
        let keyword = $(this).val().trim();
        if (keyword.length < 2) {
            $("#searchResult").html("").hide();
            return;
        }

        $.ajax({
            url: "/Cake/index.php",
            method: "POST",
            data: {
                search_products: true,
                keyword: keyword
            },
            dataType: "json",
            success: function (res) {
                if (res.success && res.products.length > 0) {
                    let html = "";
                    res.products.forEach(p => {
                        html += `
                            <a href="/Cake/pages/product.php?id=${p.id}" class="search-item">
                                <img src="${p.hinh_anh}">
                                <div>
                                    <div>${p.ten_banh}</div>
                                    <small>${p.formatted_price}</small>
                                </div>
                            </a>
                        `;
                    });
                    $("#searchResult").html(html).show();
                } else {
                    $("#searchResult").html("<div class='empty'>Không tìm thấy</div>").show();
                }
            }
        });
    });

    $(document).click(function(e){
        if(!$(e.target).closest(".search-box").length){
            $("#searchResult").hide();
        }
    });
});
</script>

<script>

    /* Modern Slider Logic */
    let sliderIndex = 0;
    const track = document.getElementById('sliderTrack');
    const dots = document.querySelectorAll('.dot');
    const totalSlides = dots.length;
    let autoPlayInterval;

    function updateSlider() {
        track.style.transform = `translateX(-${sliderIndex * 100}%)`;
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === sliderIndex);
        });
    }

    function goToSlide(n) {
        sliderIndex = n;
        updateSlider();
        resetTimer();
    }

    function nextSlide() {
        sliderIndex = (sliderIndex + 1) % totalSlides;
        updateSlider();
    }

    function prevSlide() {
        sliderIndex = (sliderIndex - 1 + totalSlides) % totalSlides;
        updateSlider();
        resetTimer();
    }

    function startTimer() {
        autoPlayInterval = setInterval(nextSlide, 5000);
    }

    function resetTimer() {
        clearInterval(autoPlayInterval);
        startTimer();
    }

    // Initialize
    if (totalSlides > 0) {
        startTimer();
        
        // Pause on hover
        const sliderContainer = document.querySelector('.slider-container');
        sliderContainer.addEventListener('mouseenter', () => clearInterval(autoPlayInterval));
        sliderContainer.addEventListener('mouseleave', () => startTimer());
    }

    $(document).ready(function() {
        $('.add-to-cart').click(function() {
            var btn = $(this);
            var originalText = btn.html();
            var banh_id = btn.data('id');

            btn.html('<i class="fa-solid fa-spinner fa-spin"></i> ...');
            btn.prop('disabled', true);

            $.ajax({
                url: '/Cake/index.php',
                type: 'POST',
                data: { add_to_cart: true, banh_id: banh_id, quantity: 1 },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.showToast('🧁 Đã thêm vào giỏ hàng!', 'success');
                        if (typeof response.cart_count !== 'undefined') {
                            window.setCartBadge(response.cart_count);
                        }
                    } else {
                        window.showToast(response.message || 'Không thể thêm sản phẩm!', 'error');
                    }
                    btn.html(originalText);
                    btn.prop('disabled', false);
                },
                error: function() {
                    window.showToast('Lỗi kết nối máy chủ!', 'error');
                    btn.html(originalText);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Drag to scroll for horizontal lists
    const scrollers = document.querySelectorAll('.product-list, .review-list');
    scrollers.forEach(slider => {
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('active');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });
        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('active');
        });
        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('active');
        });
        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2;
            slider.scrollLeft = scrollLeft - walk;
        });
    });

    let selectedStars = 0;
    function openReviewForm() { document.getElementById("reviewModal").style.display = "flex"; }
    function closeReviewForm() { document.getElementById("reviewModal").style.display = "none"; }
    
    document.querySelectorAll(".stars i").forEach(star => {
        star.addEventListener("click", function () {
            selectedStars = this.dataset.star;
            document.querySelectorAll(".stars i").forEach(s => {
                s.classList.toggle("active", s.dataset.star <= selectedStars);
            });
        });
    });
    
    function submitReview() {
        if (selectedStars == 0) {
            window.showToast('Vui lòng chọn số sao để đánh giá!', 'error');
            return;
        }
        window.showToast('Cảm ơn đánh giá của bạn! ❤️', 'success');
        closeReviewForm();
    }
</script>
</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>