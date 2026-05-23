<?php
ob_start();
include 'index.php';
$output = ob_get_clean();
echo 'Length: ' . strlen($output) . "\n";
if (strlen($output) > 0) {
    echo 'First 200 chars: ' . substr($output, 0, 200) . "\n";
}
