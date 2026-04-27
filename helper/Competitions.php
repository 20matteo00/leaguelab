<?php

class Competitions
{
    public static $round_names = [
        'Finale',
        'Semifinale',
        'Quarti di finale',
        'Ottavi di finale',
        'Sedicesimi',
        'Trentaduesimi',
        'Sessantaquattresimi'
    ];
    public static function renderCompetitions($compId, $class = '', $country = false, $logo = false)
    {
        $comp = DB::table('competitions')
            ->select('name, images, country')
            ->where('id', '=', $compId)
            ->first();

        if (!$comp) {
            return;
        }

        // LOGO SAFE
        $image = null;

        if (!empty($comp['images'])) {
            $imgData = json_decode($comp['images'], true);
            $image = $imgData['logo'] ?? null;
        }

        if (!$image) {
            $image = 'images/empty.png';
        }
?>
        <div>

            <?php if ($logo): ?>
                <img src="<?= $image ?>"
                    class="img-sm">
            <?php endif; ?>

            <span class="<?= $class ?>"><?= htmlspecialchars($comp['name']) ?></span>

            <?php if ($country && !empty($comp['country'])): ?>
                <img
                    src="https://flagcdn.com/16x12/<?= strtolower($comp['country']) ?>.png"
                    alt="<?= htmlspecialchars($comp['country']) ?>">
            <?php endif; ?>

        </div>
<?php
    }
}
