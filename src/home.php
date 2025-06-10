<?php
include './dbconn.php';
session_start();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShowTicket - 공연 예매 사이트</title>
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
            padding: -1rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            text-decoration: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-menu a {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        
        .nav-menu a:hover {
            color: #667eea;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border: 1px solid #eee;
            border-radius: 8px;
            z-index: 1000;
            top: 100%;
            left: 0;
            padding: 0.5rem 0;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-content a {
            color: #333 !important;
            padding: 0.8rem 1.2rem !important;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
            border-radius: 0 !important;
        }
        
        .dropdown-content a:hover {
            color: #667eea !important;
        }
        
        .user-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-info a:hover {
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .hero-section {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        
        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .hero-section p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }
        
        .genre-section {
            margin-bottom: 3rem;
        }
        
        .genre-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
            display: inline-block;
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .performance-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .performance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .card-image {
            width: 100%;
            height: 400px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .card-content {
            padding: 1rem;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .card-venue {
            color: #666;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .card-period {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .card-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .status-booking {
            background-color: #28a745;
            color: white;
        }
        
        .status-upcoming {
            background-color: #ffc107;
            color: #333;
        }
        
        .no-performances {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-style: italic;
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
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket</a>
            
            <nav class="nav-menu">
                <a href="home.php">홈</a>
                <div class="dropdown">
                    <a href="performances.php">공연</a>
                    <div class="dropdown-content">
                        <a href="performances.php">전체 공연</a>
                        <?php
                        // 데이터베이스에서 실제 존재하는 장르들을 조회
                        $genre_query = "SELECT DISTINCT genre FROM performances ORDER BY genre";
                        $genre_result = mysqli_query($connect, $genre_query);
                        
                        while ($genre_row = mysqli_fetch_assoc($genre_result)) {
                            $genre = $genre_row['genre'];
                            echo "<a href='performances.php?genre=" . urlencode($genre) . "'>{$genre}</a>";
                        }
                        mysqli_free_result($genre_result);
                        ?>
                    </div>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my_bookings.php">내 예매</a>
                    <?php if (isset($_SESSION['is_staff']) && $_SESSION['is_staff']): ?>
                        <a href="admin_performances.php">공연 관리</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
            
            <div class="user-info">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="mypage.php" style="color: #333; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; transition: background-color 0.3s;">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
                    </a>
                    <a href="logout.php" class="btn">로그아웃</a>
                <?php else: ?>
                    <a href="login.php" class="btn">로그인</a>
                    <a href="register.php" class="btn">회원가입</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <hr style="color: #667eea">

    <div class="container">
        <!-- 히어로 섹션 -->
        <section class="hero-section">
            <h1>최고의 공연을 만나보세요</h1>
            <p>뮤지컬, 연극, 콘서트, 오페라, 발레까지 다양한 공연을 한 곳에서</p>
            <a href="#performances" class="btn">공연 둘러보기</a>
        </section>

        <!-- 공연 목록 (장르별) -->
        <section id="performances">
            <?php
            $genres = ['뮤지컬', '연극', '콘서트', '오페라', '발레'];
            
            foreach ($genres as $genre) {
                // 현재 예매 가능하거나 곧 시작될 공연들 조회
                $query = "
                    SELECT p.*, v.venue_name 
                    FROM performances p
                    JOIN venues v ON p.venue_id = v.venue_id
                    WHERE p.genre = ? 
                    AND (p.status = 'booking' OR p.status = 'upcoming')
                    AND p.booking_end_date >= NOW()
                    ORDER BY p.performance_start_date ASC
                    LIMIT 6
                ";
                
                $stmt = mysqli_prepare($connect, $query);
                mysqli_stmt_bind_param($stmt, "s", $genre);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    echo "<div class='genre-section'>";
                    echo "<h2 class='genre-title'>🎭 {$genre}</h2>";
                    echo "<div class='performance-grid'>";
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $status_class = ($row['status'] == 'booking') ? 'status-booking' : 'status-upcoming';
                        $status_text = ($row['status'] == 'booking') ? '예매 중' : '예매 예정';
                        
                        // 최저 가격 계산
                        $prices = array_filter([$row['vip_price'], $row['r_price'], $row['s_price']]);
                        $min_price = !empty($prices) ? min($prices) : 0;
                        
                        echo "<div class='performance-card' onclick=\"location.href='performance_detail.php?id={$row['performance_id']}'\">";
                        
                        // 이미지 (나중에 실제 이미지로 교체 가능)
                        if (!empty($row['poster_image'])) {
                            echo "<img src='{$row['poster_image']}' alt='{$row['title']}' class='card-image'>";
                        } else {
                            echo "<div class='card-image'>{$row['title']}</div>";
                        }
                        
                        echo "<div class='card-content'>";
                        echo "<div class='status-badge {$status_class}'>{$status_text}</div>";
                        echo "<h3 class='card-title'>{$row['title']}</h3>";
                        echo "<p class='card-venue'>📍 {$row['venue_name']}</p>";
                        echo "<p class='card-period'>🗓️ {$row['performance_start_date']} ~ {$row['performance_end_date']}</p>";
                        
                        if ($min_price > 0) {
                            echo "<p class='card-price'>💰 " . number_format($min_price) . "원 ~</p>";
                        }
                        
                        echo "</div>";
                        echo "</div>";
                    }
                    
                    echo "</div>";
                    echo "</div>";
                } else {
                    // 해당 장르에 공연이 없는 경우는 섹션 자체를 표시하지 않음
                }
                
                mysqli_stmt_close($stmt);
            }
            ?>
        </section>
    </div>

    <!-- 푸터 -->
    <footer class="footer">
        <p>&copy; 2025 ShowTicket. 모든 권리 보유.</p>
    </footer>

    <script>
        // 부드러운 스크롤
        document.querySelector('a[href="#performances"]').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#performances').scrollIntoView({
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>