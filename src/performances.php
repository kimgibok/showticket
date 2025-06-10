<?php
include './dbconn.php';
session_start();

// 선택된 장르 가져오기
$selected_genre = $_GET['genre'] ?? '';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_genre ? $selected_genre . ' - ' : ''; ?>공연 목록 - ShowTicket</title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .page-subtitle {
            font-size: 1.2rem;
            color: #666;
        }
        
        .genre-filter {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }
        
        .genre-btn {
            padding: 0.8rem 1.5rem;
            border: 2px solid #e9ecef;
            background: white;
            color: #666;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .genre-btn:hover, .genre-btn.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
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
            position: relative;
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
            object-fit: cover;
            position: relative;
        }
        
        .genre-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(102, 126, 234, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }
        
        .performance-card:hover .genre-overlay {
            opacity: 1;
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
        
        .status-closed {
            background-color: #6c757d;
            color: white;
        }
        
        .status-finished {
            background-color: #dc3545;
            color: white;
        }
        
        .no-performances {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .no-performances h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .no-performances p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .results-info {
            text-align: center;
            margin-bottom: 2rem;
            color: #666;
            font-size: 1.1rem;
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

    <div class="container">
        <!-- 페이지 헤더 -->
        <div class="page-header">
            <h1 class="page-title">
                <?php echo $selected_genre ? $selected_genre : '전체 공연'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $selected_genre ? $selected_genre . ' 장르의 모든 공연을 만나보세요' : '다양한 장르의 공연을 한눈에 확인하세요'; ?>
            </p>
        </div>

        <!-- 장르 필터 -->
        <div class="genre-filter">
            <a href="performances.php" class="genre-btn <?php echo empty($selected_genre) ? 'active' : ''; ?>">전체</a>
            <?php
            // 데이터베이스에서 실제 존재하는 장르들만 조회
            $genre_query = "SELECT DISTINCT genre FROM performances ORDER BY genre";
            $genre_result = mysqli_query($connect, $genre_query);
            
            while ($genre_row = mysqli_fetch_assoc($genre_result)) {
                $genre = $genre_row['genre'];
                $active_class = ($selected_genre === $genre) ? 'active' : '';
                echo "<a href='performances.php?genre=" . urlencode($genre) . "' class='genre-btn {$active_class}'>{$genre}</a>";
            }
            mysqli_free_result($genre_result);
            ?>
        </div>

        <!-- 공연 목록 -->
        <?php
        // 쿼리 조건 설정
        $where_condition = "";
        $params = [];
        $param_types = "";

        if (!empty($selected_genre)) {
            $where_condition = "WHERE p.genre = ?";
            $params[] = $selected_genre;
            $param_types .= "s";
        }

        // 공연 목록 조회 (예매중, 예매예정, 종료된 공연 모두 포함)
        $query = "
            SELECT p.*, v.venue_name 
            FROM performances p
            JOIN venues v ON p.venue_id = v.venue_id
            $where_condition
            ORDER BY 
                CASE p.status 
                    WHEN 'booking' THEN 1 
                    WHEN 'upcoming' THEN 2 
                    WHEN 'closed' THEN 3 
                    WHEN 'finished' THEN 4 
                END,
                p.performance_start_date ASC
        ";

        $stmt = mysqli_prepare($connect, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $total_count = mysqli_num_rows($result);
        ?>

        <?php if ($total_count > 0): ?>
            <div class="results-info">
                총 <strong><?php echo $total_count; ?></strong>개의 공연이 있습니다.
            </div>

            <div class="performance-grid">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $status_class = match($row['status']) {
                        'booking' => 'status-booking',
                        'upcoming' => 'status-upcoming', 
                        'closed' => 'status-closed',
                        'finished' => 'status-finished',
                        default => 'status-upcoming'
                    };
                    
                    $status_text = match($row['status']) {
                        'booking' => '예매 중',
                        'upcoming' => '예매 예정',
                        'closed' => '예매 마감',
                        'finished' => '공연 종료',
                        default => '예매 예정'
                    };
                    
                    // 최저 가격 계산
                    $prices = array_filter([$row['vip_price'], $row['r_price'], $row['s_price']]);
                    $min_price = !empty($prices) ? min($prices) : 0;
                    ?>

                    <div class="performance-card" onclick="location.href='performance_detail.php?id=<?php echo $row['performance_id']; ?>'">
                        <!-- 포스터 이미지 -->
                        <?php if (!empty($row['poster_image'])): ?>
                            <div class="card-image">
                                <img src="<?php echo $row['poster_image']; ?>" alt="<?php echo $row['title']; ?>" style="width:100%; height:100%; object-fit:cover;">
                                <div class="genre-overlay"><?php echo $row['genre']; ?></div>
                            </div>
                        <?php else: ?>
                            <div class="card-image">
                                <?php echo $row['title']; ?>
                                <div class="genre-overlay"><?php echo $row['genre']; ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 공연 정보 -->
                        <div class="card-content">
                            <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                            <h3 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p class="card-venue">📍 <?php echo htmlspecialchars($row['venue_name']); ?></p>
                            <p class="card-period">🗓️ <?php echo $row['performance_start_date']; ?> ~ <?php echo $row['performance_end_date']; ?></p>
                            
                            <?php if ($min_price > 0): ?>
                                <p class="card-price">💰 <?php echo number_format($min_price); ?>원 ~</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="no-performances">
                <h3>😔 공연이 없습니다</h3>
                <p><?php echo $selected_genre ? $selected_genre . ' 장르의 공연이 현재 등록되어 있지 않습니다.' : '등록된 공연이 없습니다.'; ?></p>
                <a href="home.php" class="btn">메인 페이지로 돌아가기</a>
            </div>
        <?php endif; ?>

        <?php
        mysqli_stmt_close($stmt);
        mysqli_close($connect);
        ?>
    </div>

    <!-- 푸터 -->
    <footer class="footer">
        <p>&copy; 2025 ShowTicket. 모든 권리 보유.</p>
    </footer>
</body>
</html>