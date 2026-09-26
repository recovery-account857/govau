<?php
$logFile = 'access.log';

if (isset($_POST['clear_log'])) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, ""); 
        echo json_encode(['status' => 'success']);
    }
    exit;
}

if (isset($_GET['ajax'])) {
    renderContent($logFile);
    exit;
}

function renderContent($logFile) {
    if (!file_exists($logFile)) {
        echo json_encode(['total' => 0, 'bot' => 0, 'human' => 0, 'rows' => ""]);
        return;
    };

    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logs = array_reverse($logs);

    $totalVisitor = count($logs);
    $totalHuman = 0; $totalBot = 0; $totalCard = 0;
    $tableRows = "";

    foreach ($logs as $line) {
        $parts = explode(' | ', $line);
        if (count($parts) >= 5) {
            $date    = trim($parts[0]);
            $info    = trim($parts[1]);
            $ip      = trim($parts[2]);
            $country = trim($parts[3]);
            $ua      = trim($parts[4]);

            $infoLower = strtolower($info);
            
            if ($infoLower == 'human') {
                $totalHuman++;
                $statusClass = 'badge-human';
            } else {
                $totalBot++;
                $statusClass = 'badge-bot';
            }

            $tableRows .= "<tr>
                <td class='time-cell'>$date</td>
                <td><span class='badge $statusClass'>".strtoupper($info)."</span></td>
                <td class='ip-cell'>$ip</td>
                <td><span class='country-tag'>$country</span></td>
                <td class='ua-cell' title='$ua'>$ua</td>
            </tr>";
        }
    }

    echo json_encode([
        'total' => $totalVisitor, 'bot' => $totalBot,
        'human' => $totalHuman, 'card' => $totalCard,
        'rows' => $tableRows
    ]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KucingHitam Dashboard | Live Feed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --glass-bg: rgba(15, 17, 26, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent: #a29bfe;
            --success: #55efc4;
            --danger: #ff7675;
            --warning: #ffeaa7;
            --text-main: #ffffff;
            --text-dim: #dfe6e9;
        }

        body { 
            background: url('https://cdn.pixabay.com/photo/2018/05/21/17/19/cat-3418832_1280.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; padding: 40px;
            min-height: 100vh;
        }

        /* Overlay gelap agar background tidak terlalu terang */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .header-section {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
            background: var(--glass-bg);
            padding: 20px 30px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .status-pill {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px; border-radius: 50px;
            font-size: 12px; display: flex; align-items: center; gap: 10px;
        }

        /* Grid Stats dengan Glassmorphism */
        .grid-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        
        .stat-card {
            background: var(--glass-bg); 
            padding: 25px; 
            border-radius: 24px;
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-5px); background: rgba(15, 17, 26, 0.85); }

        .stat-value {
            font-size: 36px; font-weight: 800; margin-top: 5px;
            font-family: 'JetBrains Mono', monospace;
            text-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        /* Table Area */
        .table-container { 
            background: var(--glass-bg); 
            border-radius: 24px; 
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        table { width: 100%; border-collapse: collapse; }
        th { 
            background: rgba(255, 255, 255, 0.05); text-align: left; padding: 18px 24px;
            font-size: 11px; text-transform: uppercase; color: var(--warning); letter-spacing: 1px;
        }
        td { padding: 15px 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); font-size: 13px; }

        /* Badges */
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; text-shadow: none; }
        .badge-human { background: var(--success); color: #000; }
        .badge-bot { background: var(--danger); color: #fff; }

        .country-tag { background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 6px; font-family: 'JetBrains Mono'; font-size: 11px; }
        .ip-cell { font-family: 'JetBrains Mono'; color: var(--accent); font-weight: 600; }
        .ua-cell { opacity: 0.6; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .btn-clear {
            background: rgba(255, 118, 117, 0.2); color: var(--danger); border: 1px solid var(--danger);
            padding: 10px 20px; border-radius: 12px; cursor: pointer; font-weight: 700;
            transition: 0.3s;
        }
        .btn-clear:hover { background: var(--danger); color: white; }
    </style>
</head>
<body>

    <div class="header-section">
        <h2 style="margin:0; letter-spacing: -1px;">KUCING<span style="color:var(--accent)">HITAM</span> SECURITY</h2>
        <div style="display: flex; gap: 15px;">
            <div class="status-pill">
                <span id="status-update">Syncing logs...</span>
            </div>
            <button class="btn-clear" onclick="clearLogs()">Flush Logs</button>
        </div>
    </div>

    <div class="grid-stats">
        <div class="stat-card">
            <div style="font-size: 11px; font-weight: 700; opacity: 0.7;">TOTAL VISITOR</div>
            <div class="stat-value" id="count-total">0</div>
        </div>
        <div class="stat-card">
            <div style="font-size: 11px; font-weight: 700; opacity: 0.7; color: var(--danger);">BLOCKLIST</div>
            <div class="stat-value" id="count-bot" style="color: var(--danger);">0</div>
        </div>
        <div class="stat-card">
            <div style="font-size: 11px; font-weight: 700; opacity: 0.7; color: var(--success);">VERIFIED</div>
            <div class="stat-value" id="count-human" style="color: var(--success);">0</div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th><th>Status</th><th>IP Address</th><th>Region</th><th>User Agent</th>
                </tr>
            </thead>
            <tbody id="log-table-body"></tbody>
        </table>
    </div>

    <script>
        function updateDashboard() {
            fetch('?ajax=1')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('count-total').innerText = data.total;
                    document.getElementById('count-bot').innerText = data.bot;
                    document.getElementById('count-human').innerText = data.human;
                    document.getElementById('log-table-body').innerHTML = data.rows;
                    document.getElementById('status-update').innerText = 'Last update: ' + new Date().toLocaleTimeString();
                });
        }

        function clearLogs() {
            if (confirm('Clear all logs?')) {
                fetch('', { method: 'POST', body: new URLSearchParams({'clear_log': '1'}) })
                .then(() => updateDashboard());
            }
        }

        updateDashboard();
        setInterval(updateDashboard, 3000);
    </script>
</body>
</html>