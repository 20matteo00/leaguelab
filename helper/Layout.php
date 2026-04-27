<?php

class Layout
{
    private static $menu = [
        'Squadre' => 'index.php?page=teams',
        'Giocatori' => 'index.php?page=players',
        'Competizioni' => 'index.php?page=competitions'
    ];

    public static function renderMenu($title)
    {
?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
            <?php if ($title): ?>
                <a class="navbar-brand fw-bold" href="index.php">⚽ <?= $title ?></a>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainMenu">
                <ul class="navbar-nav ms-auto">

                    <?php foreach (self::$menu as $label => $link): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $link ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                </ul>
            </div>
        </nav>
    <?php
    }

    public static function renderSubMenu($menu)
    {
    ?>
        <div class="container py-4">
            <div class="row g-3 justify-content-center">
                <?php foreach ($menu as $label => $link): ?>
                    <div class="col-12 col-md-4 col-lg-3">
                        <a href="<?= $link ?>" class="text-decoration-none">
                            <div class="card shadow-sm h-100 border-0 hover-shadow">
                                <div class="card-body text-center py-4">
                                    <div class="fw-bold fs-5">
                                        <?= htmlspecialchars($label) ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
