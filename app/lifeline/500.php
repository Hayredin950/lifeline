<?php
http_response_code(500);
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - <?php echo Config::get('APP_NAME', 'LifeLine Blood Network'); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .error-container {
            text-align: center;
            max-width: 500px;
            background: white;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #b91c1c;
            line-height: 1;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 28px;
            color: #1f2937;
            margin-bottom: 15px;
        }
        .error-message {
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #b91c1c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #991b1b;
        }
        .logo {
            font-size: 1.2rem;
            font-weight: 700;
            color: #b91c1c;
            margin-bottom: 30px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <a href="<?php echo baseUrl(); ?>/" class="logo">LifeLine Blood Network</a>
        <div class="error-code">500</div>
        <h1 class="error-title">Server Error</h1>
        <p class="error-message">
            Something went wrong on our end. We're working to fix it.<br>
            Please try again later or contact support if the problem persists.
        </p>
        <a href="<?php echo baseUrl(); ?>/" class="btn">Go to Homepage</a>
    </div>
</body>
</html>
