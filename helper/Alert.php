<?php

class Alert
{

    public static function generateAlert(
        string $text,
        string $type = 'info', // success, danger, warning, info
        string $title = '',
        bool $dismissible = true
    ) {
        $id = 'alert_' . uniqid();

        $classes = "alert alert-$type";
        if ($dismissible) {
            $classes .= " alert-dismissible fade show";
        }

?>

        <div id="<?= $id ?>" class="<?= $classes ?>" role="alert">

            <?php if ($title): ?>
                <strong><?= htmlspecialchars($title) ?></strong><br>
            <?php endif; ?>

            <?= htmlspecialchars($text) ?>

            <?php if ($dismissible): ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php endif; ?>

        </div>

<?php
    }
}
