<?php

declare(strict_types=1);

/** @var string $token */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Profile not found</title>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f8fafc;
            color: #0f172a;
        }

        .box {
            text-align: center;
        }

        .box h1 {
            font-size: 1.05rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .box p {
            color: #64748b;
            font-size: 0.88rem;
            margin: 0;
        }

        .box code {
            background: #f1f5f9;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
<div class="box">
    <h1>Profile not found</h1>
    <p>No profile exists for token <code><?= htmlspecialchars($token) ?></code>, or it has expired.</p>
</div>
</body>
</html>