<?php
$target = isset($_GET['target']) ? $_GET['target'] : '/';
header('Location: '.$target);
die();
?>
