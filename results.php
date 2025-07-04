<?php
// error_reporting(1);        // Turn off all error reporting
// ini_set('display_errors', 1);
require 'config.php';

// Fetch dropdown distinct values
$usernames = $pdo->query("SELECT DISTINCT username FROM app_usage")->fetchAll(PDO::FETCH_COLUMN);
$hostnames = $pdo->query("SELECT DISTINCT hostname FROM app_usage")->fetchAll(PDO::FETCH_COLUMN);
$appnames  = $pdo->query("SELECT DISTINCT application FROM app_usage")->fetchAll(PDO::FETCH_COLUMN);
$departments = $pdo->query("SELECT DISTINCT department FROM user_to_dept")->fetchAll(PDO::FETCH_COLUMN);
$app_filter = isset($_GET['daily']) ?$_GET['daily']: '';
$limitParam = isset($_GET['limit']) ?$_GET['limit']: 10;
if ($limitParam === 'all') {
    $limit = null; // no limit
} else {
    $limit = max(1, (int)$limitParam); // sanitize limit to be at least 1
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$offset = ($limit === null) ? 0 : max(0, ($page - 1) * $limit);

$app_filter = isset($_GET['daily']) ? $_GET['daily'] : '';
$limitParam = isset($_GET['limit']) ? $_GET['limit'] : 10;
$username   = isset($_GET['username']) ? $_GET['username'] : '';
$hostname   = isset($_GET['hostname']) ? $_GET['hostname'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$app_filter = isset($_GET['app']) ? $_GET['app'] : '';
$date_from  = isset($_GET['from']) ? $_GET['from'] : '';
$date_to    = isset($_GET['to']) ? $_GET['to'] : '';

$aggregate  = isset($_GET['aggregate']);
$hasFilters = $username !== '' || $hostname !== '' || $app_filter !== '' || $date_from !== '' || $date_to !== '' || $aggregate;

// Build COUNT query to get total results for pagination (only needed if not aggregating)
$countQuery = "SELECT COUNT(*) FROM app_usage LEFT JOIN user_to_dept ON app_usage.username = user_to_dept.username
WHERE 1=1";
$countParams = [];

if ($app_filter !== '') {
    $countQuery .= " AND application LIKE ?";
    $countParams[] = "%$app_filter%";
}
if ($username !== '') {
    $countQuery .= " AND app_usage.username = ?";
    $countParams[] = $username;
}

if ($hostname !== '') {
    $countQuery .= " AND hostname = ?";
    $countParams[] = $hostname;
}
if ($department !== '') {
    if ($department === 'Unknown') {
        $countQuery .= " AND user_to_dept.department IS NULL";
    } else {
        $countQuery .= " AND user_to_dept.department = ?";
        $countParams[] = $department;
    }
}

if ($date_from !== '') {
    $countQuery .= " AND DATE(timestamp) >= ?";
    $countParams[] = $date_from;
}
if ($date_to !== '') {
    $countQuery .= " AND DATE(timestamp) <= ?";
    $countParams[] = $date_to;
}

$total_results = 0;
$total_pages = 1;

if (!$aggregate) {
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $total_results = $stmt->fetchColumn();
    $total_pages = ($limit === null) ? 1 : ceil($total_results / $limit);
}

// Aggregation Query - Calculate totals per application
    $aggregated = [];
    if ($aggregate) {
    $aggQuery = "SELECT app_usage.username, hostname, application, 
       SUM(TIME_TO_SEC(active_time)) AS total_active, 
       SUM(TIME_TO_SEC(idle_time)) AS total_idle,
       user_to_dept.department
FROM app_usage 
LEFT JOIN user_to_dept ON app_usage.username = user_to_dept.username 
WHERE 1=1";


        $aggParams = [];

        if ($app_filter !== '') {
            $aggQuery .= " AND application LIKE ?";
            $aggParams[] = "%$app_filter%";
        }
        if ($username !== '') {
            $aggQuery .= " AND app_usage.username = ?";
            $aggParams[] = $username;
        }
        if ($hostname !== '') {
            $aggQuery .= " AND hostname = ?";
            $aggParams[] = $hostname;
        }
        if ($date_from !== '') {
            $aggQuery .= " AND DATE(timestamp) >= ?";
            $aggParams[] = $date_from;
        }
        if ($department !== '') {
    if ($department === 'Unknown') {
        $aggQuery .= " AND user_to_dept.department IS NULL";
    } else {
        $aggQuery .= " AND user_to_dept.department = ?";
        $aggParams[] = $department;
    }
}

        if ($date_to !== '') {
            $aggQuery .= " AND DATE(timestamp) <= ?";
            $aggParams[] = $date_to;
        }

        $aggQuery .= " GROUP BY username, hostname, application ORDER BY username, hostname, application ASC";


        $stmt = $pdo->prepare($aggQuery);
        $stmt->execute($aggParams);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $aggregated[] = [
    'username' => $row['username'],
    'hostname' => $row['hostname'],
    'application' => $row['application'],
    'active' => (int)$row['total_active'],
    'idle' => (int)$row['total_idle'],
    'department' => $row['department'] ?$row['department'] : 'Unknown',
];


}

    }

// Build main data query (non-aggregate detailed rows)
$query = "SELECT app_usage.username, hostname, application, active_time, idle_time, timestamp, 
       user_to_dept.department
       FROM app_usage
LEFT JOIN user_to_dept ON app_usage.username = user_to_dept.username
WHERE 1=1";
$params = [];

if ($app_filter !== '') {
    $query .= " AND application LIKE ?";
    $params[] = "%$app_filter%";
}
if ($username !== '') {
    $query .= " AND app_usage.username = ?";
    $params[] = $username;
}
if ($department !== '') {
    if ($department === 'Unknown') {
        $query .= " AND user_to_dept.department IS NULL";
        $countQuery .= " AND user_to_dept.department IS NULL";
    } else {
        $query .= " AND user_to_dept.department = ?";    
        $countQuery .= " AND user_to_dept.department = ?";
        $params[] = $department;
        $countParams[] = $department;
    }
}



if ($hostname !== '') {
    $query .= " AND hostname = ?";
    $params[] = $hostname;
}
if ($date_from !== '') {
    $query .= " AND DATE(timestamp) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $query .= " AND DATE(timestamp) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY timestamp DESC";

// Add pagination if not aggregate and limit is set
if (!$aggregate && $limit !== null) {
    $query .= " LIMIT $limit OFFSET $offset";
}

$data = [];
if (!$aggregate) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_results_agg = count($aggregated);
$total_pages_agg = ($limit === null) ? 1 : ceil($total_results_agg / $limit);

// Slice the aggregated list based on pagination


// Prepare aggregatedList from $aggregated for display
$aggregatedList = [];

if ($aggregate) {
    
    $aggregatedIndexed = array_values($aggregated);
$sliced = ($limit === null) ? $aggregatedIndexed : array_slice($aggregatedIndexed, $offset, $limit);

$aggregatedList = [];
foreach ($sliced as $times) {
    $aggregatedList[] = [
    'username' => $times['username'],
    'hostname' => $times['hostname'],
    'application' => $times['application'],
    'active' => $times['active'],
    'idle' => $times['idle'],
    'department' => $times['department'] ?$times['department']: 'Unknown'
];

}

}


// Handle daily details if aggregate and daily app selected
$dailyDetails = [];
if ($aggregate && isset($_GET['daily'])) {
    $selectedApp = $_GET['daily'];

    $dailyQuery = "SELECT DATE(timestamp) as day,
                          SUM(TIME_TO_SEC(active_time)) as active_seconds,
                          SUM(TIME_TO_SEC(idle_time)) as idle_seconds
                   FROM app_usage
                   WHERE application = ?
                     AND DATE(timestamp) BETWEEN ? AND ?";

    $dailyParams = [
        $selectedApp,
        $date_from ?: '1970-01-01',
        $date_to ?: date('Y-m-d')
    ];

    if (!empty($username)) {
        $dailyQuery .= " AND username = ?";
        $dailyParams[] = $username;
    }
    if (!empty($hostname)) {
        $dailyQuery .= " AND hostname = ?";
        $dailyParams[] = $hostname;
    }

    $dailyQuery .= " GROUP BY day ORDER BY day ASC";

    $stmt = $pdo->prepare($dailyQuery);
    $stmt->execute($dailyParams);
    $dailyDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if ($aggregate && isset($_GET['export_agg'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="aggregated_usage.csv"');
    $output = fopen('php://output', 'w');

    // Print date range info row
    fputcsv($output, ["Date Range:", "From: " . ($date_from ?: 'N/A'), "To: " . ($date_to ?: 'N/A')]);

    fputcsv($output, []); // Empty line for spacing

    // Output column headers
    fputcsv($output, [
        'Username',
        'Department',
        'Hostname',
        'Application',
        'Total Active Time',
        'Total Idle Time'
    ]);

    // ...rest of the query & loop code...


    // Reuse the aggregation query
    $aggQuery = "SELECT app_usage.username, hostname, application, 
                    SUM(TIME_TO_SEC(active_time)) AS total_active, 
                    SUM(TIME_TO_SEC(idle_time)) AS total_idle,
                    user_to_dept.department
                FROM app_usage 
                LEFT JOIN user_to_dept ON app_usage.username = user_to_dept.username 
                WHERE 1=1";

    $aggParams = [];

    if ($app_filter !== '') {
        $aggQuery .= " AND application LIKE ?";
        $aggParams[] = "%$app_filter%";
    }
    if ($username !== '') {
        $aggQuery .= " AND app_usage.username = ?";
        $aggParams[] = $username;
    }
    if ($hostname !== '') {
        $aggQuery .= " AND hostname = ?";
        $aggParams[] = $hostname;
    }
    if ($date_from !== '') {
        $aggQuery .= " AND DATE(timestamp) >= ?";
        $aggParams[] = $date_from;
    }
    if ($date_to !== '') {
        $aggQuery .= " AND DATE(timestamp) <= ?";
        $aggParams[] = $date_to;
    }
    if ($department !== '') {
        if ($department === 'Unknown') {
            $aggQuery .= " AND user_to_dept.department IS NULL";
        } else {
            $aggQuery .= " AND user_to_dept.department = ?";
            $aggParams[] = $department;
        }
    }

    $aggQuery .= " GROUP BY username, hostname, application ORDER BY username, hostname, application ASC";

    $stmt = $pdo->prepare($aggQuery);
    $stmt->execute($aggParams);

    // Write each row of data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['username'],
            $row['department'] ?$row['department']: 'Unknown',
            $row['hostname'],
            $row['application'],
            secondsToHms((int)$row['total_active']),
            secondsToHms((int)$row['total_idle']),
        ]);
    }

    fclose($output);
    exit;
}

function secondsToHms($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}

if ($aggregate && isset($_GET['export_daily'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="daily_usage_all_apps.csv"');
    $output = fopen('php://output', 'w');

    // Print metadata
    fputcsv($output, ['From Date', $date_from ?: 'All']);
    fputcsv($output, ['To Date', $date_to ?: 'All']);
    fputcsv($output, ['Username', $username ?: 'All']);
    fputcsv($output, ['Hostname', $hostname ?: 'All']);
    fputcsv($output, []); // blank line

    // Fetch all applications within the filters
    $appQuery = "SELECT DISTINCT application 
                 FROM app_usage
                 WHERE 1=1";

    $appParams = [];

    if (!empty($date_from)) {
        $appQuery .= " AND DATE(timestamp) >= ?";
        $appParams[] = $date_from;
    }
    if (!empty($date_to)) {
        $appQuery .= " AND DATE(timestamp) <= ?";
        $appParams[] = $date_to;
    }
    if (!empty($username)) {
        $appQuery .= " AND username = ?";
        $appParams[] = $username;
    }
    if (!empty($hostname)) {
        $appQuery .= " AND hostname = ?";
        $appParams[] = $hostname;
    }

    if (!empty($_GET['daily'])) {
    // Export just the app selected via "View Daily"
    $apps = [$_GET['daily']];
} elseif (!empty($_GET['app'])) {
    // Export just the app selected in the filter
    $apps = [$_GET['app']];
} else {
    // No app filter - export all
    $stmtApps = $pdo->prepare($appQuery);
    $stmtApps->execute($appParams);
    $apps = $stmtApps->fetchAll(PDO::FETCH_COLUMN);
}


    foreach ($apps as $appName) {
        // Print application header
        fputcsv($output, ["Application:", $appName]);

        // Print table headers
        fputcsv($output, ['Date', 'Active Time (HH:MM:SS)', 'Idle Time (HH:MM:SS)']);

        // Query daily data for this application
        $dailyQuery = "SELECT DATE(timestamp) AS day,
                              SUM(TIME_TO_SEC(active_time)) AS active_seconds,
                              SUM(TIME_TO_SEC(idle_time)) AS idle_seconds
                       FROM app_usage
                       WHERE application = ?";

        $dailyParams = [$appName];

        if (!empty($date_from)) {
            $dailyQuery .= " AND DATE(timestamp) >= ?";
            $dailyParams[] = $date_from;
        }
        if (!empty($date_to)) {
            $dailyQuery .= " AND DATE(timestamp) <= ?";
            $dailyParams[] = $date_to;
        }
        if (!empty($username)) {
            $dailyQuery .= " AND username = ?";
            $dailyParams[] = $username;
        }
        if (!empty($hostname)) {
            $dailyQuery .= " AND hostname = ?";
            $dailyParams[] = $hostname;
        }

        $dailyQuery .= " GROUP BY day ORDER BY day ASC";

        $stmtDaily = $pdo->prepare($dailyQuery);
        $stmtDaily->execute($dailyParams);
        $dailyDetails = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dailyDetails as $row) {
            fputcsv($output, [
                $row['day'],
                gmdate("H:i:s", (int)$row['active_seconds']),
                gmdate("H:i:s", (int)$row['idle_seconds']),
            ]);
        }

        fputcsv($output, []); // blank line after each app block
    }

    fclose($output);
    exit;
}


?>

<?php if ($aggregate): ?>
    
<script>
const chartLabels = <?= json_encode(array_map(function($item) {
    return $item['username'] . ' - ' . $item['hostname'] . ' - ' . $item['application'];
}, $aggregatedList)) ?>;    const chartActiveData = <?= json_encode(array_column($aggregatedList, 'active')) ?>;
    const chartIdleData = <?= json_encode(array_column($aggregatedList, 'idle')) ?>;
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
    
<head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <meta charset="UTF-8">
    <title>Usage Results</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1 style="text-align: center;">Usage Results</h1>

    <!-- Filter Form -->
    <form method="GET">
    <div class="filter-row">
        <div class="filter-group">
            <label>Username:</label>
            <select name="username" class="select2">
                <option value="">All</option>
                <?php foreach ($usernames as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= ($u == $username) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Hostname:</label>
            <select name="hostname" class="select2">
                <option value="">All</option>
                <?php foreach ($hostnames as $h): ?>
                    <option value="<?= htmlspecialchars($h) ?>" <?= ($h == $hostname) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($h) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Department:</label>
            <select name="department" class="select2">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= ($d == $department) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Application:</label>
            <select name="app" class="select2">
                <option value="">All</option>
                <?php foreach ($appnames as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= ($a == $app_filter) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>From Date:</label>
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
        </div>

        <div class="filter-group">
            <label>To Date:</label>
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">
        </div>

        <div class="filter-group" style="margin-top: 24px;">
            <label><input type="checkbox" name="aggregate" <?= $aggregate ? 'checked' : '' ?>> Aggregate Totals</label>
        </div>
    </div>

    <div class="button-row">
        <button type="submit">Filter</button>
        <a href="?" class="clear-button">Clear</a>
    </div>
</form>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: "Select Option",
            allowClear: true,
            width: 'resolve'
        });
    });
</script>


<div style="margin-top: 10px; text-align: left;">
    <form method="GET" id="limitForm" style="display: inline-block;">
        <?php
            $preserved = $_GET;
            unset($preserved['limit'], $preserved['page']);
            foreach ($preserved as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        echo '<input type="hidden" name="'.htmlspecialchars($key).'[]" value="'.htmlspecialchars($v).'">';
                    }
                } else {
                    echo '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($val).'">';
                }
            }
            $selectedLimit = isset($_GET['limit'] )?$_GET['limit']:10;
        ?>
        <label for="limitSelect" style="font-weight: bold; margin-right: 10px;">Entries per page:</label>
        <select name="limit" id="limitSelect" onchange="document.getElementById('limitForm').submit()" style="padding: 6px; font-size: 14px; border-radius: 4px; border: 1px solid #ccc;">
            <?php
            $limits = [10, 25, 50, 100];
            foreach ($limits as $l): ?>
                <option value="<?= $l ?>" <?= ($selectedLimit == $l) ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
            <option value="all" <?= ($selectedLimit === 'all') ? 'selected' : '' ?>>All</option>
        </select>
    </form>
</div>

<br>


<?php if ($aggregate): ?>
<?php if ($aggregate): ?>
    <div style="margin: 10px 0;">
        <form method="GET" style="display:inline-block; margin-right: 20px;">
            <?php
            // Preserve all current GET parameters, but add export_agg=1
            $exportParams = $_GET;
            $exportParams['export_agg'] = 1;
            // Remove pagination to export all filtered aggregated data
            unset($exportParams['page'], $exportParams['limit']);
            ?>
            <?php foreach ($exportParams as $key => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
            <button type="submit">Export Aggregated CSV</button>
        </form>

        <form method="GET" style="display:inline-block;">
            <?php
            $exportDailyParams = $_GET;
            $exportDailyParams['export_daily'] = 1;
            // Remove pagination and limit for export
            unset($exportDailyParams['page'], $exportDailyParams['limit']);
            ?>
            <?php foreach ($exportDailyParams as $key => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
            <button type="submit">Export Daily CSV</button>
        </form>
    </div>
<?php endif; ?>


    <table border="1" cellspacing="0" cellpadding="5" style="width: 100%; margin-top: 20px;">
        <thead>
            <tr>
    <th>S.No</th>
    <th>Username</th>
    <th>Department</th>
    <th>Hostname</th>
    
    <th style="width: 500px;">Application</th>
    <th>Total Active Time (HH:MM:SS)</th>
    <th>Total Idle Time (HH:MM:SS)</th>
</tr>

        </thead>
        <tbody>
 <?php 
$serial = $offset + 1;
$expandedKey = isset($_GET['daily']) ? urldecode($_GET['daily']) : '';

foreach ($aggregatedList as $item): 
    $app = $item['application'];
    $times = ['active' => $item['active'], 'idle' => $item['idle']];
    $compositeKey = "{$item['username']}|{$item['hostname']}|{$item['application']}";
    $isExpanded = ($expandedKey === $compositeKey);
?>
<tr>
    <td><?= $serial++ ?></td>
    <td><?= htmlspecialchars($item['username']) ?></td>
<td><?= htmlspecialchars($item['department']) ?></td>
    <td><?= htmlspecialchars($item['hostname']) ?></td>
    <td>
        <?= htmlspecialchars($app) ?><br>
        <a href="javascript:void(0);" onclick="toggleDetails('<?= rawurlencode($compositeKey) ?>')">
            <?= $isExpanded ? 'Hide Daily' : 'View Daily' ?>
        </a>
    </td>
    <td><?= gmdate("H:i:s", $times['active']) ?></td>
    <td><?= gmdate("H:i:s", $times['idle']) ?></td>
</tr>

<?php if ($isExpanded): ?>
<tr id="details-<?= rawurlencode($compositeKey) ?>">
    <td colspan="6">
        <?php 
        // Query daily data here for this exact row:
        $dailyDetails = [];
        $dailyParams = [
            $item['application'],
            $date_from ?: '1970-01-01',
            $date_to ?: date('Y-m-d'),
            $item['username'],
            $item['hostname']
        ];

        $dailyQuery = "SELECT DATE(timestamp) as day,
                          SUM(TIME_TO_SEC(active_time)) as active_seconds,
                          SUM(TIME_TO_SEC(idle_time)) as idle_seconds
                       FROM app_usage
                       WHERE application = ?
                         AND DATE(timestamp) BETWEEN ? AND ?
                         AND username = ?
                         AND hostname = ?
                       GROUP BY day
                       ORDER BY day ASC";

        $stmtDaily = $pdo->prepare($dailyQuery);
        $stmtDaily->execute($dailyParams);
        $dailyDetails = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <table border="1" style="margin: 10px 0; width: 100%;" cellspacing="0" cellpadding="5">
            <thead> 
                <tr>
                    <th style="width: 830px;">Date</th>
                    <th style="width: 610px;">Active Time</th>
                    <th>Idle Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailyDetails as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['day']) ?></td>
                    <td><?= gmdate("H:i:s", $row['active_seconds']) ?></td>
                    <td><?= gmdate("H:i:s", $row['idle_seconds']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($dailyDetails)): ?>
                    <tr><td colspan="3" style="text-align:center;">No daily details found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </td>
</tr>
<?php endif; ?>
                    
<?php endforeach; ?>

        </tbody>
    </table>
<h2 style="margin-top: 40px;">Aggregated Usage Chart</h2>
<div style="max-width: 700px; margin: 40px auto 60px; height: 400px;">
    <canvas id="usagePieChart"></canvas>
</div>


<script>
if (typeof chartLabels !== 'undefined') {
    const ctx = document.getElementById('usagePieChart').getContext('2d');

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Total Active Time (in seconds)',
                data: chartActiveData,
                backgroundColor: chartLabels.map((_, i) => `hsl(${i * 30 % 360}, 70%, 60%)`),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                title: {
                    display: true,
                    text: 'Active Time per Application'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const seconds = context.parsed;
                            const hours = Math.floor(seconds / 3600);
                            const minutes = Math.floor((seconds % 3600) / 60);
                            return `${context.label}: ${hours}h ${minutes}m`;
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php if ($total_pages_agg > 1): ?>
    <div id="paginationBar">
        <div class="pagination-content">
            <?php for ($i = 1; $i <= $total_pages_agg; $i++): ?>
                <?php
                    $queryString = $_GET;
                    $queryString['page'] = $i;
                ?>
                <a href="?<?= http_build_query($queryString) ?>" style="<?= $i == $page ? 'font-weight: bold;' : '' ?>">
                    <?= $i ?>
                </a>&nbsp;
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<?php else: ?>
    <table border="1" cellspacing="0" cellpadding="5">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Username</th>
                <th>Department</th>
                <th>Hostname</th>
                <th>Application</th>
                <th>Active Time</th>
                <th>Idle Time</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($data)): ?>
            <tr><td colspan="7" style="text-align: center;">No records found.</td></tr>
        <?php else: 
            $sno = $offset + 1;
            foreach ($data as $row): ?>
            <tr>
                <td><?= $sno++ ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['department'] ?$row['department']: 'Unknown') ?></td>
                <td><?= htmlspecialchars($row['hostname']) ?></td>
                <td><?= htmlspecialchars($row['application']) ?></td>
                <td><?= htmlspecialchars($row['active_time']) ?></td>
                <td><?= htmlspecialchars($row['idle_time']) ?></td>
                <td><?= htmlspecialchars($row['timestamp']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
                
    <!-- Pagination Controls -->
    <?php if ($limit !== null && $total_pages > 1): ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <?php 
                $params = $_GET;
                $params['page'] = $p;
                $queryStr = http_build_query($params);
                ?>
                <?php if ($p == $page): ?>
                    <strong><?= $p ?></strong>
                <?php else: ?>
                    <a href="?<?= htmlspecialchars($queryStr) ?>"><?= $p ?></a>
                <?php endif; ?>
                &nbsp;
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function toggleDetails(appName) {
    const url = new URL(window.location.href);
    const currentDaily = url.searchParams.get('daily');

    if (currentDaily === appName) {
        url.searchParams.delete('daily');
    } else {
        url.searchParams.set('daily', appName);
    }
    window.location.href = url.toString();
}
</script>
</body>
</html>