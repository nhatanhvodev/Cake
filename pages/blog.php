<?php

session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$uid = $isLoggedIn ? $_SESSION['user_id'] : 0;
$loggedInUser = $isLoggedIn ? ($_SESSION['username'] ?? 'Người dùng') : null;

require_once '../config/connect.php';
if ($conn->connect_error)
    die("Lỗi kết nối DB: " . $conn->connect_error);

if ($isLoggedIn && isset($_GET['like'])) {
    $blogId = $_GET['like'];

    $checkStmt = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND blog_id=?");
    $checkStmt->bind_param("ii", $uid, $blogId);
    $checkStmt->execute();

    if ($checkStmt->get_result()->num_rows > 0) {

        $delStmt = $conn->prepare("DELETE FROM likes WHERE user_id=? AND blog_id=?");
        $delStmt->bind_param("ii", $uid, $blogId);
        $delStmt->execute();
    } else {

        $insStmt = $conn->prepare("INSERT INTO likes(user_id, blog_id, created_at) VALUES(?, ?, NOW())");
        $insStmt->bind_param("ii", $uid, $blogId);
        $insStmt->execute();
    }

    header("Location: blog.php#post-" . $blogId);
    exit;
}

if ($isLoggedIn && isset($_POST['submit_blog'])) {
    $mediaPath = null;
    $mediaType = null;

    if (!empty($_FILES['media']['name'])) {
        $dir = "uploads/blog/";
        if (!is_dir($dir))
            mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        $file = uniqid() . "." . $ext;

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $mediaType = 'image';
        } elseif ($ext === 'mp4') {
            $mediaType = 'video';
        }

        if ($mediaType) {
            move_uploaded_file($_FILES['media']['tmp_name'], $dir . $file);
            $mediaPath = $dir . $file;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO blogs(title, content, author_id, status, media_path, media_type, created_at, updated_at)
        VALUES(?, ?, ?, 'pending', ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param("ssiss", $_POST['title'], $_POST['content'], $uid, $mediaPath, $mediaType);

    if ($stmt->execute()) {
        $_SESSION['flash_msg'] = '<i class="fa-solid fa-circle-check" style="color: #2e7d32;"></i> Đăng bài thành công! Bài viết đã được gửi tới Admin để duyệt.';
    }
    header("Location: blog.php");
    exit;
}

if ($isLoggedIn && isset($_POST['submit_comment'])) {
    $stmt = $conn->prepare("INSERT INTO comments(blog_id, user_id, content, created_at) VALUES(?, ?, ?, NOW())");
    $stmt->bind_param("iis", $_POST['blog_id'], $uid, $_POST['comment_content']);
    $stmt->execute();
    header("Location: blog.php#post-" . $_POST['blog_id']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        b.*, u.username, 
        COUNT(l.blog_id) AS like_count,
        MAX(CASE WHEN l.user_id=? THEN 1 ELSE 0 END) AS liked
    FROM blogs b
    JOIN users u ON b.author_id=u.id
    LEFT JOIN likes l ON b.id=l.blog_id
    WHERE b.status='approved'
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$blogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$comments = [];
foreach ($blogs as $b) {
    $c = $conn->prepare("
    SELECT c.*, u.username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.blog_id=?
    ORDER BY c.created_at ASC
");

    $c->bind_param("i", $b['id']);
    $c->execute();
    $comments[$b['id']] = $c->get_result()->fetch_all(MYSQLI_ASSOC);
}

$myPostCount = 0;
if ($isLoggedIn) {
    $c = $conn->prepare("SELECT COUNT(*) total FROM blogs WHERE author_id=?");
    $c->bind_param("i", $uid);
    $c->execute();
    $myPostCount = $c->get_result()->fetch_assoc()['total'];
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Blog – Gấu Bakery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #e8f5f1;
            background-image:
                radial-gradient(circle at 10% 15%, rgba(255, 255, 255, .6) 0 40px, transparent 41px),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, .5) 0 35px, transparent 36px),
                radial-gradient(circle at 30% 80%, rgba(255, 255, 255, .5) 0 45px, transparent 46px),
                url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
            background-repeat: repeat;
            background-size: auto, auto, auto, 220px 220px;
            font-family: Arial, sans-serif;
        }

        .blog-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .blog-layout {
            display: grid;
            grid-template-columns: 4fr 1fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .blog-layout {
                grid-template-columns: 1fr;
            }
        }

        .write-post {
            background: #ffffff;
            border-radius: 22px;
            padding: 30px;
            margin-bottom: 40px;
            border: 2px solid #d9efe7;
            box-shadow: 0 12px 30px rgba(69, 119, 98, .12);
            position: relative;
            overflow: hidden;
        }

        .write-post::before {
            content: "<i class=" fa-solid fa-pen-nib" style=" color: #ff6b9c;
            "></i>️";
            position: absolute;
            top: -20px;
            left: 24px;
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, #ffb6c1, #ffd6dc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .2);
        }

        .write-post h2 {
            color: #457762;
            margin-bottom: 16px;
        }

        .write-post input,
        .write-post textarea {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            border: 1.5px solid #cfe7de;
            margin-bottom: 14px;
        }

        .write-post button {
            background: linear-gradient(135deg, #457762, #5fae92);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
        }

        .post {
            background: #ffffff;
            border-radius: 22px;
            padding: 28px;
            margin-bottom: 36px;
            border: 2px solid #d9efe7;
            box-shadow: 0 12px 28px rgba(69, 119, 98, .12);
            position: relative;
            scroll-margin-top: 100px;
        }

        .post::before {
            content: "<i class=" fa-solid fa-cake-candles" style=" color: #ff6b9c;
            "></i>";
            position: absolute;
            top: -18px;
            right: 20px;
            font-size: 30px;
        }

        .post h3 {
            color: #2f6f55;
        }

        .post-media img,
        .post-media video {
            max-width: 100%;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .1);
            margin-top: 10px;
        }

        .blog-actions {
            margin-top: 15px;
        }

        .blog-actions a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            text-decoration: none;
        }

        .blog-actions .liked {
            color: #ff4757;
        }

        .blog-actions .unliked {
            color: #888;
        }

        .comments {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px dashed #cfe7de;
        }

        .comment {
            display: flex;
            gap: 12px;
            background: #f0faf6;
            padding: 14px;
            border-radius: 14px;
            margin-bottom: 10px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #457762;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-box {
            background: #ffffff;
            padding: 22px;
            border-radius: 20px;
            border: 2px solid #d9efe7;
            box-shadow: 0 10px 22px rgba(69, 119, 98, .12);
            margin-bottom: 22px;
        }

        .login-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #457762, #5fae92);
            color: #fff;
            border-radius: 20px;
            text-decoration: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <?php include '../includes/header.php'; ?>

    <main class="blog-container">

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div class="alert-success">
                <?= $_SESSION['flash_msg']; ?>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <section class="write-post">
                <h2><i class="fa-regular fa-newspaper" style="color: #8b4513;"></i> Bài viết mới nhất</h2>
                <p style="color:#457762;margin-bottom:20px">Chia sẻ trải nghiệm – kiến thức – cảm hứng mỗi ngày</p>

                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="title" placeholder="Tiêu đề bài viết" required>
                    <div style="margin-bottom:10px;">
                        <label style="color:#555; font-weight:bold;"><i class="fa-solid fa-camera"
                                style="color: #8b4513;"></i> Thêm Ảnh/Video (Tùy chọn):</label><br>
                        <input type="file" name="media" accept="image/*,video/mp4" style="margin-top:5px;">
                    </div>
                    <textarea name="content" rows="6" placeholder="Nội dung bài viết" required></textarea>
                    <button name="submit_blog"><i class="fa-solid fa-paper-plane"></i> Gửi duyệt</button>
                </form>
            </section>
        <?php endif; ?>

        <div class="blog-layout">

            <div>
                <h2><i class="fa-regular fa-newspaper" style="color: #8b4513;"></i> Bảng tin</h2>
                <?php foreach ($blogs as $b): ?>
                    <div class="post" id="post-<?= $b['id'] ?>">

                        <h3><?= htmlspecialchars($b['title']) ?></h3>
                        <div class="meta" style="color:#777; font-size:0.9em; margin-bottom:10px;">
                            <i class="fa-solid fa-user"></i> <?= htmlspecialchars($b['username']) ?> ·
                            <i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($b['created_at'])) ?>
                        </div>

                        <p><?= nl2br(htmlspecialchars($b['content'])) ?></p>

                        <?php if (!empty($b['media_path'])): ?>
                            <div class="post-media">
                                <?php if ($b['media_type'] === 'image'): ?>
                                    <img src="<?= htmlspecialchars($b['media_path']) ?>" alt="Blog media">
                                <?php elseif ($b['media_type'] === 'video'): ?>
                                    <video controls>
                                        <source src="<?= htmlspecialchars($b['media_path']) ?>" type="video/mp4">
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="blog-actions" style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <?php if ($isLoggedIn): ?>
                                    <a href="?like=<?= $b['id'] ?>" class="<?= $b['liked'] ? 'liked' : 'unliked' ?>">
                                        <i class="<?= $b['liked'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                                        <?= $b['like_count'] ?>
                                    </a>
                                <?php else: ?>
                                    <a href="/Cake/pages/login.php?redirect=blog.php" class="unliked">
                                        <i class="fa-regular fa-heart"></i> <?= $b['like_count'] ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($isLoggedIn && $b['author_id'] == $uid): ?>
                                <button onclick="deleteBlog(<?= $b['id'] ?>, this)" class="btn btn-sm"
                                    style="background:#ff4757; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;">
                                    <i class="fa fa-trash"></i> Xóa
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="comments">
                            <h4><i class="fa-regular fa-comment-dots" style="color: #8b4513;"></i> Bình luận</h4>
                            <?php foreach ($comments[$b['id']] ?? [] as $c): ?>
                                <div class="comment">
                                    <div class="avatar">
                                        <?php if (!empty($c['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($c['avatar']) ?>" alt="avatar">
                                        <?php else: ?>
                                            <?= strtoupper(mb_substr($c['username'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($c['username']) ?></strong><br>
                                        <?= htmlspecialchars($c['content']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($isLoggedIn): ?>
                                <form class="comment-form" method="POST" style="margin-top:10px; display:flex; gap:10px;">
                                    <input type="hidden" name="blog_id" value="<?= $b['id'] ?>">
                                    <textarea name="comment_content" rows="1" placeholder="Viết bình luận..." required
                                        style="flex:1; padding:10px; border-radius:10px; border:1px solid #ccc;"></textarea>
                                    <button name="submit_comment"
                                        style="padding:0 20px; border-radius:10px; background:#457762; color:white; border:none; cursor:pointer;">Gửi</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <aside>
                <div class="sidebar-box">
                    <?php if ($isLoggedIn): ?>
                        <h4><a href="/Cake/pages/thongtinkh.php" style="text-decoration:none; color:inherit;"><i
                                    class="fa-regular fa-user" style="color: #8b4513;"></i> Tài khoản</a></h4>
                        <p>Xin chào, <strong><?= htmlspecialchars($loggedInUser) ?></strong></p>
                    <?php else: ?>
                        <h4><i class="fa-regular fa-user" style="color: #8b4513;"></i> Tài khoản</h4>
                        <p>Bạn chưa đăng nhập.</p>
                        <a href="/Cake/pages/login.php?redirect=thongtinkh.php" class="login-btn">🔐 Đăng nhập</a>
                    <?php endif; ?>
                </div>
                <div class="sidebar-box">
                    <h4><i class="fa-solid fa-chart-simple" style="color: #8b4513;"></i> Thống kê cá nhân</h4>
                    <?php if ($isLoggedIn): ?>
                        <p><i class="fa-regular fa-clipboard" style="color: #8b4513;"></i> Bạn đã viết:
                            <strong><?= $myPostCount ?></strong> bài</p>
                    <?php else: ?>
                        <p>Đăng nhập để xem thống kê.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <?php include '../includes/footer.html'; ?>

    <script>
        function deleteBlog(blogId, btn) {
            // confirm replaced by custom dialog below

            fetch("blog_delete.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + blogId
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.closest(".post").remove();
                        window.showToast("Đã xóa bài viết thành công", 'success');
                    } else {
                        window.showToast(data.message || 'Lỗi khi xóa bài', 'error');
                    }
                })
                .catch(() => {
                    window.showToast("Không kết nối được server", 'error');
                });
        }
    </script>

</body>

</html>
<?php $conn->close(); ?>