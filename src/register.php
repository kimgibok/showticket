<?php
session_start();

// 이미 로그인된 경우 메인 페이지로 리다이렉트
if (isset($_SESSION['user_id'])) {
    header("Location: mypage.php");
    exit();
}

$error_message = "";
$success_message = "";

// 폼 제출 처리
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include './dbconn.php';
    
    // 입력값 받기
    $user_id = trim($_POST['user_id']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $terms_agree = isset($_POST['terms_agree']) ? 1 : 0;
    $is_staff = isset($_POST['is_staff']) ? 1 : 0;
    
    // 유효성 검사
    if (empty($user_id) || empty($password) || empty($name) || empty($phone) || empty($email) || empty($address)) {
        $error_message = "모든 필수 정보를 입력해주세요.";
    } elseif (strlen($user_id) < 4) {
        $error_message = "아이디는 4글자 이상이어야 합니다.";
    } elseif (strlen($password) < 6) {
        $error_message = "비밀번호는 6글자 이상이어야 합니다.";
    } elseif ($password !== $confirm_password) {
        $error_message = "비밀번호가 일치하지 않습니다.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "올바른 이메일 형식이 아닙니다.";
    } elseif (!$terms_agree) {
        $error_message = "이용약관에 동의해주세요.";
    } else {
        // 아이디 중복 확인
        $check_query = "SELECT user_id FROM users WHERE user_id = ?";
        $check_stmt = mysqli_prepare($connect, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_message = "이미 사용 중인 아이디입니다.";
        } else {

            $hashed_password = $password; 
            
            $insert_query = "INSERT INTO users (user_id, password, name, phone, address, email, is_staff) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($connect, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "ssssssi", $user_id, $hashed_password, $name, $phone, $address, $email, $is_staff);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $success_message = "회원가입이 완료되었습니다! 로그인해주세요.";
            } else {
                $error_message = "회원가입 중 오류가 발생했습니다: " . mysqli_error($connect);
            }
            
            mysqli_stmt_close($insert_stmt);
        }
        
        mysqli_stmt_close($check_stmt);
        mysqli_close($connect);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 - ShowTicket</title>
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
            max-width: 600px;
            margin: 2rem auto;
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
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .genre-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
            margin-top: 0.5rem;
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
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        
        .admin-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 0.8rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket</a>
            <nav>
                <a href="login.php" class="btn">로그인</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="form-title">회원가입</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <!-- 아이디 & 비밀번호 -->
            <div class="form-row">
                <div class="form-group">
                    <label for="user_id">아이디 <span class="required">*</span></label>
                    <input type="text" id="user_id" name="user_id" class="form-control" 
                           placeholder="4글자 이상 입력" minlength="4" required 
                           value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="name">이름 <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" 
                           placeholder="실명을 입력하세요" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">비밀번호 <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="6글자 이상 입력" minlength="6" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">비밀번호 확인 <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="비밀번호 다시 입력" required>
                </div>
            </div>

            <!-- 연락처 정보 -->
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">전화번호 <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           placeholder="010-1234-5678" required
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">이메일 <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="example@email.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <!-- 주소 (textarea) -->
            <div class="form-group">
                <label for="address">주소 <span class="required">*</span></label>
                <textarea id="address" name="address" class="form-control" rows="3" 
                          placeholder="상세 주소를 입력해주세요" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>


            <!-- 수신 동의 -->
            <div class="form-group">
                <label>알림 수신 동의</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="email_agree" name="email_agree" value="1">
                        <label for="email_agree">이메일 수신 동의 (공연 정보, 할인 혜택 등)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="sms_agree" name="sms_agree" value="1">
                        <label for="sms_agree">SMS 수신 동의 (예매 확인, 공연 안내 등)</label>
                    </div>
                </div>
            </div>

            <!-- 관리자 권한 (개발/테스트용) -->
            <div class="form-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="is_staff" name="is_staff" value="1"
                           <?php echo isset($_POST['is_staff']) ? 'checked' : ''; ?>>
                    <label for="is_staff">관리자 권한 신청 (개발/테스트용)</label>
                </div>
                <div class="admin-notice">
                    ⚠️ 관리자 권한은 공연 등록/수정/삭제 권한을 부여합니다. 개발 및 테스트 목적으로만 사용하세요.
                </div>
            </div>

            <!-- 이용약관 동의 (필수) -->
            <div class="form-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="terms_agree" name="terms_agree" value="1" required>
                    <label for="terms_agree">이용약관 및 개인정보 처리방침에 동의합니다 <span class="required">*</span></label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">회원가입 완료</button>
        </form>

        <div class="form-links">
            이미 계정이 있으신가요? <a href="login.php">로그인하기</a>
        </div>
    </div>

    <script>
        // 비밀번호 확인 체크
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password && confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // 폼 제출 전 최종 검증
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const termsAgree = document.getElementById('terms_agree').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('비밀번호가 일치하지 않습니다.');
                return;
            }
            
            if (!termsAgree) {
                e.preventDefault();
                alert('이용약관에 동의해주세요.');
                return;
            }
        });
    </script>
</body>
</html>