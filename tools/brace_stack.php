<?php
$path = __DIR__ . '/../management/assign.php';
$lines = file($path);
$stack = [];
foreach ($lines as $i => $l) {
    $pos = 0;
    while (($pos = strpos($l, '{', $pos)) !== false) {
        $stack[] = ['line' => $i+1, 'char' => $pos];
        $pos++;
    }
    $pos = 0;
    while (($pos = strpos($l, '}', $pos)) !== false) {
        if (!empty($stack)) array_pop($stack);
        $pos++;
    }
}
if (empty($stack)) {
    echo "All braces matched\n";
} else {
    echo "Unmatched opens:\n";
    foreach ($stack as $s) echo "line {$s['line']}\n";
}
?>