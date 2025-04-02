<?php
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .info { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>File Upload Test</h1>
    
    <div class="info">
        <h2>Current PHP Upload Settings</h2>
        <table>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>upload_max_filesize</td>
                <td><?php echo ini_get('upload_max_filesize'); ?></td>
            </tr>
            <tr>
                <td>post_max_size</td>
                <td><?php echo ini_get('post_max_size'); ?></td>
            </tr>
            <tr>
                <td>memory_limit</td>
                <td><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <td>max_execution_time</td>
                <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
            </tr>
            <tr>
                <td>max_input_time</td>
                <td><?php echo ini_get('max_input_time'); ?> seconds</td>
            </tr>
        </table>
    </div>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] == 0) {
            $file = $_FILES['test_file'];
            echo '<div class="success">';
            echo '<h2>File Upload Successful!</h2>';
            echo '<p>File Name: ' . htmlspecialchars($file['name']) . '</p>';
            echo '<p>File Size: ' . number_format($file['size'] / 1024 / 1024, 2) . ' MB</p>';
            echo '<p>File Type: ' . htmlspecialchars($file['type']) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h2>File Upload Failed</h2>';
            
            if (!isset($_FILES['test_file'])) {
                echo '<p>No file was submitted.</p>';
            } else {
                $error = $_FILES['test_file']['error'];
                echo '<p>Error Code: ' . $error . '</p>';
                
                switch ($error) {
                    case UPLOAD_ERR_INI_SIZE:
                        echo '<p>The uploaded file exceeds the upload_max_filesize directive in php.ini.</p>';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        echo '<p>The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.</p>';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        echo '<p>The uploaded file was only partially uploaded.</p>';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        echo '<p>No file was uploaded.</p>';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        echo '<p>Missing a temporary folder.</p>';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        echo '<p>Failed to write file to disk.</p>';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        echo '<p>A PHP extension stopped the file upload.</p>';
                        break;
                    default:
                        echo '<p>Unknown upload error.</p>';
                }
            }
            echo '</div>';
        }
    }
    ?>
    
    <h2>Test File Upload</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <p>
            <label for="test_file">Select a file to upload:</label><br>
            <input type="file" name="test_file" id="test_file">
        </p>
        <p>
            <button type="submit">Upload File</button>
        </p>
    </form>
    
    <div class="info">
        <h2>Troubleshooting Tips</h2>
        <ol>
            <li>If uploads fail, check your php.ini settings.</li>
            <li>Make sure post_max_size is larger than upload_max_filesize.</li>
            <li>Check your web server configuration (Apache/Nginx) for file size limits.</li>
            <li>Ensure the upload directory has proper write permissions.</li>
            <li>Check PHP error logs for more detailed error messages.</li>
        </ol>
    </div>
</body>
</html>

