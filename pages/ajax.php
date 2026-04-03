<?php
$products = ['Bánh quy mix hạt', 'Bánh kem bento', 'Bánh tart trái cây', 'Bánh kem phô mai matcha'];

$keywords = strtolower($_POST['keywords']);
$suggestions = [];

foreach ($products as $product) {
  if (strpos(strtolower($product), $keywords) !== false) {
    $suggestions[] = "<div class='suggest-item'>$product</div>";
  }
}

echo implode('', $suggestions);
?>
