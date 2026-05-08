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
    <span id="top"></span>
    <?php
    Layout::renderMenu($title);
    ob_start();
    if ($page) {
        include __DIR__ . '/pages/' . $page . '.php';
    }
    ob_end_flush();
    ?>
    <span id="bottom"></span>
    <div class="position-fixed bottom-0 end-0 p-3 d-flex flex-column gap-2" style="z-index: 1050;">

        <!-- Su -->
        <a href="#top" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
            style="width:24px;height:24px;">
            <i class="bi bi-arrow-up"></i>
        </a>

        <!-- Giù -->
        <a href="#bottom" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
            style="width:24px;height:24px;">
            <i class="bi bi-arrow-down"></i>
        </a>

    </div>
</body>

</html>


<?php
