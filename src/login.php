<?php
session_start();

// 이미 로그인된 경우 메인 페이지로 리다이렉트
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$error_message = "";

// 폼 제출 처리
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include './dbconn.php';
    
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];
    
    // 입력값 검증
    if (empty($user_id) || empty($password)) {
        $error_message = "아이디와 비밀번호를 모두 입력해주세요.";
    } else {
        // 사용자 정보 조회
        $query = "SELECT user_id, password, name, is_staff FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($connect, $query);
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // 비밀번호 확인 (단순 비교 - 우리 DB 구조에 맞게)
            if ($password === $user['password']) {
                // 로그인 성공 - 세션 설정
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['is_staff'] = $user['is_staff'];
                
                // 메인 페이지로 리다이렉트
                header("Location: home.php");
                exit();
            } else {
                $error_message = "비밀번호가 올바르지 않습니다.";
            }
        } else {
            $error_message = "존재하지 않는 아이디입니다.";
        }
        
        mysqli_stmt_close($stmt);
        mysqli_close($connect);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - ShowTicket</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: white;
            min-height: 100vh;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            text-decoration: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-left: -1rem;
        }
        
        .container {
            max-width: 400px;
            margin: 4rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        
        .form-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: transparent;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            border: 2px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            width: 100%;
            font-size: 1.1rem;
            padding: 1rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            border-color: #5a6268;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .form-links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .form-links a:hover {
            text-decoration: underline;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border-radius: 8px;
            border: 1px solid #e0e6ff;
        }
        
        .welcome-section h2 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .welcome-section p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .login-features {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #e9ecef;
        }
        
        .login-features h4 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .login-features ul {
            list-style: none;
            padding: 0;
        }
        
        .login-features li {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            padding-left: 1rem;
            position: relative;
        }
        
        .login-features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket</a>
            <nav>
                <a href="register.php" class="btn">회원가입</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- 환영 섹션 -->
        <div class="welcome-section">
            <h2>환영합니다!</h2>
            <p>ShowTicket에서 최고의 공연을 만나보세요</p>
        </div>

        <h1 class="form-title">로그인</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="user_id">아이디 <span class="required">*</span></label>
                <input type="text" id="user_id" name="user_id" class="form-control" 
                       placeholder="아이디를 입력하세요" required 
                       value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">비밀번호 <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="비밀번호를 입력하세요" required>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">로그인 상태 유지</label>
            </div>

            <button type="submit" class="btn btn-primary">로그인</button>
        </form>

        <!-- 로그인 후 이용 가능한 기능 안내 -->
        <div class="login-features">
            <h4>로그인 후 이용 가능한 서비스</h4>
            <ul>
                <li>공연 예매 및 좌석 선택</li>
                <li>예매 내역 확인 및 관리</li>
                <li>개인 정보 수정</li>
                <li>관리자 전용 기능 (관리자만)</li>
            </ul>
        </div>

        <div class="form-links">
            아직 계정이 없으신가요? <a href="register.php">회원가입하기</a><br>
            <a href="home.php">메인 페이지로 돌아가기</a>
        </div>
    </div>

    <script>
        // 폼 제출 전 검증
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const userId = document.getElementById('user_id').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!userId) {
                e.preventDefault();
                alert('아이디를 입력해주세요.');
                document.getElementById('user_id').focus();
                return;
            }
            
            if (!password) {
                e.preventDefault();
                alert('비밀번호를 입력해주세요.');
                document.getElementById('password').focus();
                return;
            }
        });

        // 로그인 필드 자동 포커스
        window.addEventListener('load', function() {
            document.getElementById('user_id').focus();
        });
    </script>
</body>
</html>
