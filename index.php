<?php
require_once __DIR__ . '/bootstrap.php'; // o dove hai messo l'autoloader

$title = "League Lab";
$page = $_GET['page'] ?? null;
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php
    Config::renderMeta();
    Config::renderTitle($title);
    Config::renderStyle();
    Config::renderScript();
    ?>
</head>

<body>
    <?php
    Layout::renderMenu($title);
    ob_start();
    if ($page) {
        include __DIR__ . '/pages/' . $page . '.php';
    }
    ob_end_flush();
    ?>
</body>

</html>


<?php
