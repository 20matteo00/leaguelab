<?php
class Stats
{

    public static $menu = [
        1 => [
            [
                'subaction' => 'overview',
                'icon'   => 'calendar3',
                'label'  => 'Panoramica'
            ],
            [
                'subaction' => 'team_matches',
                'icon' => 'dribbble',
                'label' => 'Incontri per Squadra'
            ],
        ],
        2 => [],
        3 => [],
    ];

    public static function renderMenu($baseUrl, $level, $mode)
    {
        $menu = self::$menu[$mode];
?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&level=<?= $level ?>&action=stats&subaction<?= $m['subaction'] ?>#content" class="btn btn-info w-100">
                        <i class="bi bi-<?= $m['icon'] ?> "></i> <?= $m['label'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
<?php
    }

    public static function renderStats($seasonId, $level) {
        
    }
}
