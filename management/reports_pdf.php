<?php
require_once __DIR__ . '/../includes/auth.php';
requireAnyRole(['Maintenance_Manager', 'Admin']);

require_once __DIR__ . '/../includes/functions.php';

$conn = getDBConnection();
$selectedMonth = $_GET['month'] ?? 'all';
$selectedDepartment = $_GET['department'] ?? 'all';

$where = [];
$params = [];
$types = '';

if ($selectedMonth !== 'all') {
    $where[] = "DATE_FORMAT(mr.submitted_at, '%Y-%m') = ?";
    $params[] = $selectedMonth;
    $types .= 's';
}

if ($selectedDepartment !== 'all') {
    $where[] = "it.type_name = ?";
    $params[] = $selectedDepartment;
    $types .= 's';
}

$whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT mr.request_number, mr.title, it.type_name, l.location_name, l.building, mr.priority, mr.status, mr.submitted_at, mr.completed_at, u.full_name as submitter
    FROM maintenance_requests mr
    LEFT JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    LEFT JOIN locations l ON mr.location_id = l.location_id
    LEFT JOIN users u ON mr.user_id = u.user_id" . $whereSql . "
    ORDER BY mr.submitted_at DESC");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

$html = '<!doctype html><html><head><meta charset="utf-8"><title>Maintenance Report</title>';
$html .= '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#222}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f4f4f4}</style>';
$html .= '</head><body>';
$html .= '<h2>Maintenance Requests Report</h2>';
$html .= '<p>Generated: ' . date('Y-m-d H:i') . '</p>';
if ($selectedMonth !== 'all' || $selectedDepartment !== 'all') {
    $html .= '<p>Filters: ';
    if ($selectedMonth !== 'all') {
        $html .= 'Month ' . htmlspecialchars($selectedMonth) . '; ';
    }
    if ($selectedDepartment !== 'all') {
        $html .= 'Department ' . htmlspecialchars($selectedDepartment) . ';';
    }
    $html .= '</p>';
}
$html .= '<table><thead><tr><th>#</th><th>Request #</th><th>Title</th><th>Type</th><th>Location</th><th>Priority</th><th>Status</th><th>Submitted At</th><th>Completed At</th><th>Submitted By</th></tr></thead><tbody>';

$i = 1;
foreach ($requests as $r) {
    $loc = trim(($r['location_name'] ?? '') . ' - ' . ($r['building'] ?? ''));
    $html .= '<tr>';
    $html .= '<td>' . $i++ . '</td>';
    $html .= '<td>' . htmlspecialchars($r['request_number'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($r['title'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($r['type_name'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($loc ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($r['priority'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($r['status'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars(formatDate($r['submitted_at'] ?? '', 'Y-m-d H:i')) . '</td>';
    $html .= '<td>' . (!empty($r['completed_at']) ? htmlspecialchars(formatDate($r['completed_at'], 'Y-m-d H:i')) : '') . '</td>';
    $html .= '<td>' . htmlspecialchars($r['submitter'] ?? '') . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';
$html .= '</body></html>';

// Use Dompdf if available
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    try {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('maintenance_report_' . date('Ymd_Hi') . '.pdf', ['Attachment' => 1]);
        exit();
    } catch (Exception $e) {
        echo '<h3>PDF generation failed:</h3><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<p>If you need PDF support, install <code>dompdf/dompdf</code> via Composer:</p>';
        echo '<pre>composer require dompdf/dompdf</pre>';
        exit();
    }
} else {
    // Developer guidance when library missing
    echo '<h3>PDF generator not installed</h3>';
    echo '<p>To enable PDF export, install Dompdf using Composer in the project root:</p>';
    echo '<pre>composer require dompdf/dompdf</pre>';
    echo '<p>After installation, re-run this export link.</p>';
    // Also offer HTML fallback
    echo '<hr>' . $html;
    exit();
}

?>
