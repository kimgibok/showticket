<?php
include './dbconn.php';
session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || !$_SESSION['is_staff']) {
    header("Location: home.php");
    exit();
}

$error_message = "";
$success_message = "";

// 공연 삭제 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_performance'])) {
    $performance_id = $_POST['performance_id'];
    
    // 예매가 있는지 확인
    $check_bookings = "
        SELECT COUNT(*) as booking_count 
        FROM booking_groups bg
        JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id
        WHERE ps.performance_id = ? AND bg.status = 'confirmed'
    ";
    $check_stmt = mysqli_prepare($connect, $check_bookings);
    mysqli_stmt_bind_param($check_stmt, "i", $performance_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $booking_count = mysqli_fetch_assoc($check_result)['booking_count'];
    
    if ($booking_count > 0) {
        $error_message = "예매된 공연은 삭제할 수 없습니다.";
    } else {
        // 트랜잭션 시작
        mysqli_begin_transaction($connect);
        
        try {
            // 공연 회차 삭제
            $delete_schedules = "DELETE FROM performance_schedules WHERE performance_id = ?";
            $schedule_stmt = mysqli_prepare($connect, $delete_schedules);
            mysqli_stmt_bind_param($schedule_stmt, "i", $performance_id);
            mysqli_stmt_execute($schedule_stmt);
            
            // 공연 삭제
            $delete_performance = "DELETE FROM performances WHERE performance_id = ?";
            $perf_stmt = mysqli_prepare($connect, $delete_performance);
            mysqli_stmt_bind_param($perf_stmt, "i", $performance_id);
            mysqli_stmt_execute($perf_stmt);
            
            mysqli_commit($connect);
            $success_message = "공연이 성공적으로 삭제되었습니다.";
            
            mysqli_stmt_close($schedule_stmt);
            mysqli_stmt_close($perf_stmt);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            $error_message = "공연 삭제 중 오류가 발생했습니다.";
        }
    }
    mysqli_stmt_close($check_stmt);
}

// 공연 상태 변경 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $performance_id = $_POST['performance_id'];
    $new_status = $_POST['new_status'];
    
    $update_status = "UPDATE performances SET status = ? WHERE performance_id = ?";
    $status_stmt = mysqli_prepare($connect, $update_status);
    mysqli_stmt_bind_param($status_stmt, "si", $new_status, $performance_id);
    
    if (mysqli_stmt_execute($status_stmt)) {
        $success_message = "공연 상태가 변경되었습니다.";
    } else {
        $error_message = "상태 변경 중 오류가 발생했습니다.";
    }
    mysqli_stmt_close($status_stmt);
}

// 페이지네이션 설정
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 필터링
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$genre_filter = $_GET['genre'] ?? '';

// 검색 조건 구성
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR v.venue_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($genre_filter)) {
    $where_conditions[] = "p.genre = ?";
    $params[] = $genre_filter;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// 전체 공연 수 조회
$count_query = "
    SELECT COUNT(*) as total 
    FROM performances p
    JOIN venues v ON p.venue_id = v.venue_id
    $where_clause
";
$count_stmt = mysqli_prepare($connect, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_performances = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_performances / $limit);

// 공연 목록 조회
$performances_query = "
    SELECT 
        p.*,
        v.venue_name,
        v.location,
        (SELECT COUNT(*) FROM performance_schedules ps WHERE ps.performance_id = p.performance_id) as schedule_count,
        (SELECT COUNT(DISTINCT bg.group_id) 
         FROM booking_groups bg 
         JOIN performance_schedules ps2 ON bg.schedule_id = ps2.schedule_id 
         WHERE ps2.performance_id = p.performance_id AND bg.status = 'confirmed') as booking_count,
        (SELECT SUM(bg.total_price) 
         FROM booking_groups bg 
         JOIN performance_schedules ps3 ON bg.schedule_id = ps3.schedule_id 
         WHERE ps3.performance_id = p.performance_id AND bg.status = 'confirmed') as total_revenue
    FROM performances p
    JOIN venues v ON p.venue_id = v.venue_id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$perf_stmt = mysqli_prepare($connect, $performances_query);
$final_params = $params;
$final_params[] = $limit;
$final_params[] = $offset;
$final_param_types = $param_types . "ii";

mysqli_stmt_bind_param($perf_stmt, $final_param_types, ...$final_params);
mysqli_stmt_execute($perf_stmt);
$performances_result = mysqli_stmt_get_result($perf_stmt);

mysqli_stmt_close($count_stmt);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공연 관리 - ShowTicket Admin</title>
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
            background-color: #f8f9fa;
        }
        
        .header {
            background-color: white;
            color: #333;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #eee;
        }
        
        .nav-container {
            max-width: 1400px;
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
        
        .admin-badge {
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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
        
        .nav-menu a:hover, .nav-menu a.active {
            color: #667eea;
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
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .btn-danger:hover {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            border-color: #28a745;
            color: #28a745;
        }
        
        .btn-success:hover {
            background: #28a745;
            color: white;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2.2rem;
            color: #333;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .form-control {
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .performances-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #e0e6ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .performance-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.3rem;
        }
        
        .performance-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-upcoming {
            background-color: #ffc107;
            color: #333;
        }
        
        .status-booking {
            background-color: #28a745;
            color: white;
        }
        
        .status-closed {
            background-color: #6c757d;
            color: white;
        }
        
        .status-finished {
            background-color: #dc3545;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .stats-mini {
            font-size: 0.85rem;
            color: #666;
        }
        
        .stats-mini strong {
            color: #333;
        }
        
        .no-performances {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background: #667eea;
            color: white;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-select {
            padding: 0.3rem 0.5rem;
            font-size: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: white;
        }
        
        @media (max-width: 1024px) {
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <header class="header">
        <div class="nav-container">
            <a href="home.php" class="logo">🎭 ShowTicket<span class="admin-badge" style="color: white;">ADMIN</span></a>
            
            <nav class="nav-menu">
                <a href="home.php">홈</a>
                <a href="performances.php">공연</a>
                <a href="my_bookings.php">내 예매</a>
                <a href="admin_performances.php" class="active">공연 관리</a>
            </nav>
            
            <div class="user-info">
                    <a href="mypage.php" style="color: #333; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 8px; transition: background-color 0.3s;">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?>님</span>
                    </a>
                    <a href="logout.php" class="btn">로그아웃</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- 페이지 헤더 -->
        <div class="page-header">
            <h1 class="page-title">🎪 공연 관리</h1>
            <a href="admin_add_performance.php" class="btn btn-primary">+ 새 공연 등록</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- 관리자 통계 -->
        <div class="admin-stats">
            <?php
            $admin_stats_query = "
                SELECT 
                    COUNT(*) as total_performances,
                    SUM(CASE WHEN status = 'booking' THEN 1 ELSE 0 END) as active_performances,
                    (SELECT COUNT(DISTINCT bg.group_id) FROM booking_groups bg 
                     JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id 
                     WHERE bg.status = 'confirmed') as total_bookings,
                    (SELECT SUM(bg.total_price) FROM booking_groups bg 
                     JOIN performance_schedules ps ON bg.schedule_id = ps.schedule_id 
                     WHERE bg.status = 'confirmed') as total_revenue
                FROM performances
            ";
            $admin_stats_result = mysqli_query($connect, $admin_stats_query);
            $admin_stats = mysqli_fetch_assoc($admin_stats_result);
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $admin_stats['total_performances']; ?></div>
                <div class="stat-label">총 공연 수</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $admin_stats['active_performances']; ?></div>
                <div class="stat-label">예매중인 공연</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $admin_stats['total_bookings']; ?></div>
                <div class="stat-label">총 예매 건수</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($admin_stats['total_revenue'] ?? 0); ?>원</div>
                <div class="stat-label">총 매출</div>
            </div>
        </div>

        <!-- 필터 섹션 -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">검색</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="공연명 또는 공연장 검색" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">상태</label>
                        <select name="status" class="form-control">
                            <option value="">전체</option>
                            <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>예매 예정</option>
                            <option value="booking" <?php echo $status_filter == 'booking' ? 'selected' : ''; ?>>예매중</option>
                            <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>예매 마감</option>
                            <option value="finished" <?php echo $status_filter == 'finished' ? 'selected' : ''; ?>>공연 종료</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">장르</label>
                        <select name="genre" class="form-control">
                            <option value="">전체</option>
                            <option value="뮤지컬" <?php echo $genre_filter == '뮤지컬' ? 'selected' : ''; ?>>뮤지컬</option>
                            <option value="연극" <?php echo $genre_filter == '연극' ? 'selected' : ''; ?>>연극</option>
                            <option value="콘서트" <?php echo $genre_filter == '콘서트' ? 'selected' : ''; ?>>콘서트</option>
                            <option value="오페라" <?php echo $genre_filter == '오페라' ? 'selected' : ''; ?>>오페라</option>
                            <option value="발레" <?php echo $genre_filter == '발레' ? 'selected' : ''; ?>>발레</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">검색</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 공연 목록 테이블 -->
        <div class="performances-table">
            <div class="table-header">
                <h2 class="table-title">공연 목록</h2>
                <span>총 <?php echo $total_performances; ?>개 공연</span>
            </div>

            <?php if (mysqli_num_rows($performances_result) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>공연 정보</th>
                            <th>기간</th>
                            <th>상태</th>
                            <th>통계</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($perf = mysqli_fetch_assoc($performances_result)): ?>
                            <tr>
                                <td>
                                    <div class="performance-title"><?php echo htmlspecialchars($perf['title']); ?></div>
                                    <div class="performance-meta">
                                        🎭 <?php echo $perf['genre']; ?> | 
                                        📍 <?php echo htmlspecialchars($perf['venue_name']); ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div><?php echo date('Y.m.d', strtotime($perf['performance_start_date'])); ?> ~</div>
                                    <div><?php echo date('Y.m.d', strtotime($perf['performance_end_date'])); ?></div>
                                    <div class="stats-mini"><?php echo $perf['schedule_count']; ?>회차</div>
                                </td>
                                
                                <td>
                                    <div class="status-badge status-<?php echo $perf['status']; ?>">
                                        <?php 
                                        echo match($perf['status']) {
                                            'upcoming' => '예매 예정',
                                            'booking' => '예매중',
                                            'closed' => '예매 마감',
                                            'finished' => '공연 종료'
                                        };
                                        ?>
                                    </div>
                                    
                                    <form method="POST" style="margin-top: 0.5rem;">
                                        <input type="hidden" name="performance_id" value="<?php echo $perf['performance_id']; ?>">
                                        <select name="new_status" class="status-select" onchange="this.form.submit()">
                                            <option value="upcoming" <?php echo $perf['status'] == 'upcoming' ? 'selected' : ''; ?>>예매 예정</option>
                                            <option value="booking" <?php echo $perf['status'] == 'booking' ? 'selected' : ''; ?>>예매중</option>
                                            <option value="closed" <?php echo $perf['status'] == 'closed' ? 'selected' : ''; ?>>예매 마감</option>
                                            <option value="finished" <?php echo $perf['status'] == 'finished' ? 'selected' : ''; ?>>공연 종료</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                
                                <td>
                                    <div class="stats-mini">
                                        <strong><?php echo $perf['booking_count']; ?></strong>건 예매<br>
                                        <strong><?php echo number_format($perf['total_revenue'] ?? 0); ?></strong>원 매출
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="action-buttons">
                                        <a href="performance_detail.php?id=<?php echo $perf['performance_id']; ?>" 
                                           class="btn btn-small" target="_blank">상세보기</a>
                                        <a href="admin_edit_performance.php?id=<?php echo $perf['performance_id']; ?>" 
                                           class="btn btn-small btn-success">수정</a>
                                        
                                        <?php if ($perf['booking_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('정말로 이 공연을 삭제하시겠습니까?');">
                                                <input type="hidden" name="performance_id" value="<?php echo $perf['performance_id']; ?>">
                                                <button type="submit" name="delete_performance" 
                                                        class="btn btn-small btn-danger">삭제</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; color: #999;">예매건 있음</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- 페이지네이션 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&genre=<?php echo urlencode($genre_filter); ?>" 
                               class="page-link">‹ 이전</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&genre=<?php echo urlencode($genre_filter); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&genre=<?php echo urlencode($genre_filter); ?>" 
                               class="page-link">다음 ›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-performances">
                    <h3>📋 등록된 공연이 없습니다</h3>
                    <p>새로운 공연을 등록해보세요!</p>
                    <a href="admin_add_performance.php" class="btn btn-primary">공연 등록하기</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 상태 변경 확인
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function(e) {
                const confirmed = confirm('공연 상태를 변경하시겠습니까?');
                if (!confirmed) {
                    e.preventDefault();
                    // 원래 값으로 되돌리기
                    this.value = this.getAttribute('data-original');
                    return false;
                }
            });
            
            // 원래 값 저장
            select.setAttribute('data-original', select.value);
        });

        // 검색 필터 자동 제출 (선택사항)
        document.querySelectorAll('select[name="status"], select[name="genre"]').forEach(select => {
            select.addEventListener('change', function() {
                // 즉시 검색하고 싶다면 주석 해제
                // this.form.submit();
            });
        });

        // 테이블 행 클릭 시 상세보기 (선택사항)
        document.querySelectorAll('table tr[onclick]').forEach(row => {
            row.style.cursor = 'pointer';
        });
    </script>

    <?php
    mysqli_stmt_close($perf_stmt);
    mysqli_close($connect);
    ?>
</body>
</html>