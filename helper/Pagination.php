<?php

class Pagination
{
    public static $pagination = [5, 10, 20, 50, 100];

    // helper per costruire URL mantenendo tutti i parametri
    private static function pageUrl($page, array $override = []): string
    {
        $params = array_merge([
            'page'     => $page,
            'action'   => 'view',
            'sorts'    => $GLOBALS['sortsParam'] ?? json_encode([]),
            'limit'    => $GLOBALS['limit'],
            'page_num' => $GLOBALS['page_num'],
        ], $override);
        return 'index.php?' . http_build_query($params);
    }

    // In Layout class (o helpers.php)

    public static function buildSortUrl(string $col, array $currentSorts, array $extra = []): string
    {
        $existing = null;
        foreach ($currentSorts as $pair) {
            if ($pair[0] === $col) {
                $existing = $pair[1];
                break;
            }
        }

        if ($existing === null) {
            $nextDir = 'ASC';
        } elseif ($existing === 'ASC') {
            $nextDir = 'DESC';
        } else {
            $nextDir = null;
        }

        $newSorts = array_values(array_filter($currentSorts, fn($p) => $p[0] !== $col));
        if ($nextDir !== null) {
            $newSorts[] = [$col, $nextDir]; // ← era array_unshift, ora append
        }

        $params = array_merge($extra, ['sorts' => json_encode($newSorts)]);
        return 'index.php?' . http_build_query($params);
    }

    public static function sortHeader(string $col, string $label, array $currentSorts, array $extra = []): string
    {
        $url      = self::buildSortUrl($col, $currentSorts, $extra);
        $existing = null;
        $priority = null;
        foreach ($currentSorts as $i => $pair) {
            if ($pair[0] === $col) {
                $existing = $pair[1];
                $priority = $i + 1;
                break;
            }
        }

        $icon = match ($existing) {
            'ASC'  => ' <span class="sort-icon asc">↑</span>',
            'DESC' => ' <span class="sort-icon desc">↓</span>',
            default => ' <span class="sort-icon idle">⇅</span>',
        };
        $badge = ($priority !== null && count($currentSorts) > 1)
            ? ' <sup class="sort-badge">' . $priority . '</sup>'
            : '';

        return '<a class="text-white text-decoration-none sort-link" href="' . $url . '">'
            . htmlspecialchars($label) . $icon . $badge . '</a>';
    }

    public static function parseSorts(array $allowed): array
    {
        $sorts = [];
        if (!empty($_GET['sorts'])) {
            $decoded = json_decode($_GET['sorts'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $pair) {
                    if (
                        is_array($pair) && count($pair) === 2
                        && in_array($pair[0], $allowed)
                        && in_array($pair[1], ['ASC', 'DESC'])
                    ) {
                        $sorts[] = $pair;
                    }
                }
            }
        }
        // fallback legacy ?sort=name&dir=ASC

        return $sorts;
    }

    public static function renderPagination($pages, $page_num, $type_page, array $extra = [])
    {
        // Inietta eventuali parametri extra (es. sorts, limit) nei globals temporanei
        // così pageUrl li pesca automaticamente
        foreach ($extra as $k => $v) {
            $GLOBALS[$k === 'sorts' ? 'sortsParam' : $k] = $v;
        }

        if ($pages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page_num <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= self::pageUrl($type_page, ['page_num' => $page_num - 1]) ?>">‹</a>
                    </li>
                    <?php
                    $start = max(1, $page_num - 2);
                    $end   = min($pages, $page_num + 2);
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= self::pageUrl($type_page, ['page_num' => 1]) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                            <a class="page-link" href="<?= self::pageUrl($type_page, ['page_num' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($end < $pages): ?>
                        <?php if ($end < $pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= self::pageUrl($type_page, ['page_num' => $pages]) ?>"><?= $pages ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="page-item <?= $page_num >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= self::pageUrl($type_page, ['page_num' => $page_num + 1]) ?>">›</a>
                    </li>
                </ul>
            </nav>
<?php endif;
    }
}
