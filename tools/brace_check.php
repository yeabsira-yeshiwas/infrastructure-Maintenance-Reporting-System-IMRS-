<?php
$path = __DIR__ . '/../management/assign.php';
$lines = file($path);
$d = 0;
foreach ($lines as $i => $l) {
    $open = substr_count($l, '{');
    $close = substr_count($l, '}');
    $d += $open - $close;
    if ($open || $close) {
        echo ($i+1) . ": open={$open} close={$close} depth={$d}\n";
    }
}
echo "FINAL={$d}\n";
?>