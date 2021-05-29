<?php
$array = explode(',', $_GET['array']);

// 修正はここから
$length = count($array);

$cnt = 0;

for ($i = 0; $i < $length - 1; $i++) {
  for ($j = 1; $j < $length - $i; $j++) {
    $cnt++;
    if ($array[$j] < $array[$j - 1]) {
      $tmp = $array[$j];
      $array[$j] = $array[$j - 1];
      $array[$j - 1] = $tmp;
    }
  }
}
// 修正はここまで

echo "<pre>";
print_r($array);
print_r("比較回数: $cnt 回");
echo "</pre>";
