<?php
// Minimal offline page — served by the SW when network is unavailable.
// No DB access; no includes (they may not be cached).
http_response_code(503);
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline — LifeLine Blood Network</title>
<style>
  body { font-family: system-ui, sans-serif; background: #fff7f7; margin: 0;
         display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .box { text-align: center; padding: 2rem; max-width: 360px; }
  h1   { color: #b91c1c; margin-bottom: .5rem; }
  p    { color: #555; line-height: 1.6; }
  a    { color: #b91c1c; }
</style>
</head>
<body>
<div class="box">
  <h1>You are offline</h1>
  <p>LifeLine needs an internet connection to match donors and hospitals in real time.</p>
  <p>Please check your connection and <a href="javascript:location.reload()">try again</a>.</p>
</div>
</body>
</html>
