<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$role = $_SESSION['role'] ?? 'guest';

// Lấy số loại sản phẩm (số dòng) trong giỏ hàng
$cartItemCount = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
  $uid = (int) $_SESSION['user_id'];
  $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
  if ($stmtCount) {
    $stmtCount->bind_param("i", $uid);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result()->fetch_assoc();
    $cartItemCount = (int) ($resCount['total'] ?? 0);
    $stmtCount->close();
  }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= !empty($pageTitle) ? htmlspecialchars($pageTitle) . ' | Gấu Bakery' : 'Gấu Bakery' ?></title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <?php if (!empty($extraLinks))
    echo $extraLinks; ?>

  <style>
    #site-header {
      background: transparent;
      width: 100%;
      margin: 0;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    #site-header.scrolled .header-inner {
      box-shadow: 0 4px 15px rgba(0, 0, 0, .15);
      transition: 0.3s ease;
    }

    .header-inner {
      width: 100%;
      margin: 0;
      height: 100px;
      padding: 0 40px;

      display: flex;
      align-items: center;
      justify-content: space-between;

      background: #fff;
      border-radius: 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
    }

    .logo {
      display: flex;
      align-items: center;
      height: 100%;
    }

    .logo img {
      height: 80px;
      width: auto;
      object-fit: contain;
    }

    #main-nav {
      height: 100%;
      display: flex;
      align-items: center;
    }

    #main-nav ul {
      display: flex;
      align-items: center;
      list-style: none;
      margin: 0;
      padding: 0;
      gap: 22px;

      white-space: nowrap;
    }

    #main-nav li {
      margin: 0;
    }

    #main-nav a {
      font-size: 17px;
      font-weight: 500;
      color: #626A67;
      padding: 6px 4px;
      text-decoration: none;
      transition: 0.3s;
      line-height: 1;
    }

    #main-nav a:hover,
    #main-nav a.active {
      color: #8b4513;
      border-bottom: 3px solid #8b4513;
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      padding: 8px 36px 8px 14px;
      border-radius: 20px;
      border: 1px solid #ccc;
      outline: none;
      width: 180px;
    }

    .search-box i {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }

    .search-result {
      position: absolute;
      top: 40px;
      left: 0;
      width: 100%;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
      display: none;
    }

    .search-result div {
      padding: 10px;
      cursor: pointer;
    }

    .search-result div:hover {
      background: #f5f5f5;
    }

    .search-box button {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #888;
    }

    .search-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      text-decoration: none;
      color: #333;
    }

    .search-item img {
      width: 56px;
      height: 56px;
      min-width: 56px;
      min-height: 56px;
      object-fit: cover;
      border-radius: 8px;
      background: #f2f2f2;
    }

    .search-item:hover {
      background: #f5f5f5;
    }

    .search-info {
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .search-name {
      font-size: 14px;
      font-weight: 500;
      line-height: 1.3;
    }

    .search-price {
      font-size: 13px;
      color: #8b4513;
    }

    .notify-box {
      position: relative;
    }

    .notify-list {
      position: absolute;
      right: 0;
      top: 30px;
      width: 260px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
      display: none;
    }

    .notify-list div {
      padding: 12px;
      font-size: 14px;
      border-bottom: 1px solid #eee;
    }

    .notify-list div:last-child {
      border-bottom: none;
    }

    .notification-item {
      cursor: pointer;
      padding: 10px;
    }

    .notification-item:hover {
      background-color: #f2f2f2;
    }

    #user-actions {
      display: flex;
      align-items: center;
      height: 100%;
      gap: 18px;
    }

    #user-actions a {
      color: #626A67;
      font-size: 25px;
      transition: 0.3s;
      line-height: 1;
    }

    #user-actions a:hover {
      color: #8b4513;
    }

    @media (max-width: 768px) {
      .header-inner {
        height: auto;
        padding: 16px;
        flex-direction: column;
        gap: 14px;
      }

      #main-nav ul {
        flex-wrap: wrap;
        justify-content: center;
        white-space: normal;
      }
    }

    .notify-box {
      position: relative;
    }

    .notify-box i {
      color: #626A67;
      font-size: 25px;
      transition: 0.3s;
      cursor: pointer;
    }

    .notify-box i:hover {
      color: #8b4513;
    }

    .notify-list {
      position: absolute;
      right: 0;
      top: 30px;
      width: 260px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .15);
      display: none;
    }

    .notify-list div {
      padding: 12px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    .cart-wrapper {
      position: relative;
      display: inline-flex;
      align-items: center;
    }

    .cart-badge {
      position: absolute;
      top: -8px;
      right: -10px;
      background: #ff6b9c;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 11px;
      font-weight: bold;
      min-width: 18px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      transition: transform 0.2s;
    }

    .cart-badge.pop {
      transform: scale(1.3);
    }
  </style>


  <header id="site-header">
    <div class="header-inner">

      <div class="logo">
        <a href="/Cake/index.php">
          <img src="/Cake/assets/img/logo.jpg" alt="Gấu Bakery">
        </a>
      </div>

      <nav id="main-nav">
        <ul>
          <li><a href="/Cake/index.php"><i class="fa-solid fa-house"></i> TRANG CHỦ</a></li>
          <li><a href="/Cake/pages/product.php"><i class="fa-solid fa-bread-slice"></i> SẢN PHẨM</a></li>
          <li><a href="/Cake/pages/sukien.php"><i class="fa-solid fa-calendar-day"></i> SỰ KIỆN</a></li>
          <li><a href="/Cake/pages/blog.php"><i class="fa-solid fa-blog"></i> BLOG</a></li>
          <li><a href="#contact"><i class="fa-solid fa-envelope"></i> LIÊN HỆ</a></li>
        </ul>
      </nav>

      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Tìm bánh...">
        <button type="button" id="searchBtn">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        <div class="search-result" id="searchResult"></div>
      </div>
      <div id="user-actions">
        <a href="/Cake/pages/thongtinkh.php"><i class="fa-solid fa-user"></i></a>
        <a href="/Cake/pages/giohang.php" class="cart-wrapper">
          <i class="fa-solid fa-cart-shopping"></i>
          <span id="header-cart-badge" class="cart-badge"
            style="<?= $cartItemCount > 0 ? '' : 'display:none;' ?>"><?= $cartItemCount ?></span>
        </a>

        <div class="notify-box">
          <i class="fa-solid fa-bell" onclick="toggleNotify()"></i>
          <div class="notify-list" id="notifyList">
            <?php if ($role === 'admin'): ?>
              <div><i class="fa-solid fa-box-open" style="color: #8b4513;"></i> Có đơn hàng mới</div>
            <?php elseif ($role === 'user'): ?>
              <div><i class="fa-solid fa-truck-fast" style="color: #8b4513;"></i> Đơn hàng đang giao</div>
            <?php else: ?>
              <div class="notification-item" onclick="goToEvent()">
                <i class="fa-solid fa-champagne-glasses" style="color: #ffb703;"></i> Có sự kiện mới
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </header>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>

    $(document).ready(function () {

      function doSearch() {
        let keyword = $("#searchInput").val().trim();
        if (keyword.length < 2) {
          $("#searchResult").hide().html("");
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
<a class="search-item" href="/Cake/pages/product.php?search=${encodeURIComponent(p.ten_banh)}">
    <img src="${p.hinh_anh}" alt="${p.ten_banh}">
    <div class="search-info">
        <div class="search-name">${p.ten_banh}</div>
        <small class="search-price">${p.formatted_price}</small>
    </div>
</a>`;

              });
              $("#searchResult").html(html).show();
            } else {
              $("#searchResult").html("<div style='padding:10px'>Không tìm thấy</div>").show();
            }
          }
        });
      }

      $("#searchInput").on("keyup", doSearch);
      $("#searchBtn").on("click", doSearch);

      $("#searchInput").on("keypress", function (e) {
        if (e.which === 13) {
          e.preventDefault();
          doSearch();
        }
      });

      $(document).on("click", function (e) {
        if (!$(e.target).closest(".search-box").length) {
          $("#searchResult").hide();
        }
      });
    });

    function toggleNotify() {
      const box = document.getElementById("notifyList");
      box.style.display = box.style.display === "block" ? "none" : "block";
    }
    function goToEvent() {
      document.getElementById("notifyList").style.display = "none";
      window.location.href = "/Cake/pages/sukien.php";
    }

  </script>

  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

  <style>
    /* ===== Custom Confirm Dialog ===== */
    .confirm-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .45);
      backdrop-filter: blur(4px);
      z-index: 99999;
      justify-content: center;
      align-items: center;
    }

    .confirm-overlay.active {
      display: flex;
    }

    .confirm-box {
      background: #fff;
      border-radius: 22px;
      padding: 32px 28px 24px;
      max-width: 360px;
      width: 90%;
      text-align: center;
      box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
      animation: confirmPop .25s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes confirmPop {
      from {
        transform: scale(.85);
        opacity: 0;
      }

      to {
        transform: scale(1);
        opacity: 1;
      }
    }

    .confirm-icon {
      font-size: 40px;
      margin-bottom: 14px;
      color: #ff6b9c;
    }

    .confirm-box h4 {
      margin: 0 0 8px;
      font-size: 18px;
      color: #222;
    }

    .confirm-box p {
      margin: 0 0 22px;
      color: #666;
      font-size: 15px;
      line-height: 1.5;
    }

    .confirm-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .confirm-actions button {
      flex: 1;
      max-width: 140px;
      padding: 11px 0;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
    }

    .btn-confirm-ok {
      background: linear-gradient(135deg, #ff6b9c, #ff8fb3);
      color: #fff;
      box-shadow: 0 4px 12px rgba(255, 107, 156, .3);
    }

    .btn-confirm-ok:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 156, .4);
    }

    .btn-confirm-cancel {
      background: #f0f0f0;
      color: #555;
    }

    .btn-confirm-cancel:hover {
      background: #e0e0e0;
    }
  </style>

  <!-- Custom Confirm Dialog HTML -->
  <div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
      <div class="confirm-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <h4 id="confirmTitle">Xác nhận</h4>
      <p id="confirmMsg">Bạn có chắc chắn muốn thực hiện hành động này?</p>
      <div class="confirm-actions">
        <button class="btn-confirm-cancel" id="confirmCancelBtn">Hủy</button>
        <button class="btn-confirm-ok" id="confirmOkBtn">Xác nhận</button>
      </div>
    </div>
  </div>

  <script>
    // ===== Global Toast API =====
    window.showToast = function (msg, type) {
      type = type || 'success';
      let config = {
        success: { bg: 'linear-gradient(135deg, #00b09b, #96c93d)', icon: '✓' },
        error: { bg: 'linear-gradient(135deg, #ff416c, #ff4b2b)', icon: '✕' },
        info: { bg: 'linear-gradient(135deg, #3b82f6, #60a5fa)', icon: 'ℹ' },
        warning: { bg: 'linear-gradient(135deg, #f59e0b, #fbbf24)', icon: '⚠' },
      };
      let c = config[type] || config.success;
      Toastify({
        text: msg,
        duration: 3500,
        close: true,
        gravity: 'top',
        position: 'right',
        style: {
          background: c.bg,
          borderRadius: '14px',
          fontFamily: "'Inter', sans-serif",
          fontWeight: '500',
          fontSize: '14px',
          padding: '14px 20px',
          boxShadow: '0 8px 24px rgba(0,0,0,.18)',
          minWidth: '260px',
        }
      }).showToast();
    };

    // ===== Custom Confirm Dialog API =====
    window.showConfirm = function (message, title) {
      title = title || 'Xác nhận';
      return new Promise(function (resolve) {
        const overlay = document.getElementById('confirmOverlay');
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmMsg').innerText = message;
        overlay.classList.add('active');

        const okBtn = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');

        function cleanup() {
          overlay.classList.remove('active');
          okBtn.removeEventListener('click', onOk);
          cancelBtn.removeEventListener('click', onCancel);
        }
        function onOk() { cleanup(); resolve(true); }
        function onCancel() { cleanup(); resolve(false); }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);

        overlay.addEventListener('click', function (e) {
          if (e.target === overlay) { cleanup(); resolve(false); }
        }, { once: true });
      });
    };

    // ===== Cart Badge API =====
    // Đặt số loại sản phẩm chính xác (lấy từ server response)
    window.setCartBadge = function (count) {
      let badge = document.getElementById('header-cart-badge');
      if (!badge) return;
      let n = parseInt(count) || 0;
      if (n > 0) {
        badge.innerText = n;
        badge.style.display = 'inline-block';
        badge.classList.add('pop');
        setTimeout(function () { badge.classList.remove('pop'); }, 300);
      } else {
        badge.style.display = 'none';
        badge.innerText = '0';
      }
    };

    // Giữ lại updateCartBadge để tương thích ngược (chỉ tăng khi thêm mới)
    window.updateCartBadge = function (isNew) {
      if (!isNew) return; // Không tăng nếu chỉ cập nhật số lượng sp đã có
      let badge = document.getElementById('header-cart-badge');
      if (!badge) return;
      let current = parseInt(badge.innerText || '0');
      window.setCartBadge(current + 1);
    };
  </script>
  </body>

</html>