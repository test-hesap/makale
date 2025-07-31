<?php
session_start();
require_once '../includes/config.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Tüm alanları doldurunuz.';
    } else {
        try {            $stmt = $db->prepare("SELECT id, username, password, role, is_premium, premium_until FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);            if ($user && password_verify($password, $user['password'])) {                // Kullanıcının avatar bilgisini de alalım
                $avatarStmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
                $avatarStmt->execute([$user['id']]);
                $avatarInfo = $avatarStmt->fetch(PDO::FETCH_ASSOC);
                $avatar = $avatarInfo['avatar'] ?? 'default-avatar.jpg';
                
                // Avatar değerinde tam yol varsa, sadece dosya adını al
                if (strpos($avatar, 'uploads/avatars/') === 0) {
                    $avatar = str_replace('uploads/avatars/', '', $avatar);
                    
                    // Aynı zamanda veritabanını da güncelle
                    $updateAvatarStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $updateAvatarStmt->execute([$avatar, $user['id']]);
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $avatar; // Avatar bilgisini session'a ekle
                $_SESSION['is_premium'] = $user['is_premium'] ?? 0;
                $_SESSION['premium_until'] = $user['premium_until'] ?? null;
                
                // Avatarın veritabanında ve session'da tutarlı olduğunu garantileyelim
                ensureUserAvatar($user['id']);
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre.';
            }
        } catch(PDOException $e) {
            $error = 'Giriş hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Girişi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Admin Girişi</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı:</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Giriş Yap</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
