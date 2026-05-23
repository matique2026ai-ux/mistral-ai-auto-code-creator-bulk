<?php
$html = file_get_contents('C:/Users/PCIB/Desktop/mistral-ai-auto-code-creator-bulk/V3/index.php');
preg_match_all('/<script>(.*?)<\/script>/s', $html, $matches);
foreach($matches[1] as $i => $js) {
    file_put_contents('temp_' . $i . '.js', $js);
}
echo "Extracted.\n";
