<?php

class Teams
{

    public static function renderTeams($teamId, $class = '', $country = false, $logo = false, $params = [])
    {
        $team = DB::table('teams')
            ->select('name, colors, images, country')
            ->where('id', '=', $teamId)
            ->first();

        if (!$team) {
            return;
        }

        // COLORS SAFE
        $colors = json_decode($team['colors'] ?? '', true) ?? [];

        $bg = $colors['background'] ?? '#fff';
        $text = $colors['text'] ?? '#000';
        $border = $colors['border'] ?? '#ddd';

        $style = "
        background-color:$bg;
        color:$text;
        border:2px solid $border;";

        // LOGO SAFE
        $image = null;

        if (!empty($team['images'])) {
            $imgData = json_decode($team['images'], true);
            $image = $imgData['logo'] ?? null;
        }

        if (!$image) {
            $image = 'images/empty.png';
        }
        if (!empty($params)) {
            $abbr = $params['abbr_name'] ?? 0;
            if ($abbr > 0) {
                $team['name'] = self::abbreviateTeamName($abbr, $team['name']);
            }
        }
?>

        <?php if ($logo): ?>
            <img src="<?= $image ?>"
                class="img-sm">
        <?php endif; ?>

        <span class="<?= $class ?>" style="<?= $style ?>"><?= htmlspecialchars($team['name']) ?></span>

        <?php if ($country && !empty($team['country'])): ?>
            <img
                src="https://flagcdn.com/16x12/<?= strtolower($team['country']) ?>.png"
                alt="<?= htmlspecialchars($team['country']) ?>">
        <?php endif; ?>

<?php
    }

    private static function abbreviateTeamName(int $n, string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));

        // caso 1: una sola parola -> primi N caratteri
        if (count($parts) === 1) {
            return mb_substr($parts[0], 0, $n);
        }

        // caso 2: più parole
        $result = [];

        // prima parola: N-1 caratteri (minimo 1)
        $firstLen = max(1, $n - 1);
        $result[] = mb_substr($parts[0], 0, $firstLen);

        // seconda parola: 1 carattere (se esiste)
        if (isset($parts[1])) {
            $result[] = mb_substr($parts[1], 0, 1);
        }

        // eventuali altre parole (opzionale: tutte iniziali)
        for ($i = 2; $i < count($parts); $i++) {
            $result[] = mb_substr($parts[$i], 0, 1);
        }
        
        return implode('', $result);
    }

    public static function importTeams()
    {
        $teamsInDB = DB::table('teams')->select('name')->get();
        $teams = Field::getTeams();

        $existingNames = array_map(fn($t) => strtolower(trim($t['name'])), $teamsInDB);

        foreach ($teams as $team) {

            $name = strtolower(trim($team['name']));

            if (in_array($name, $existingNames)) {
                continue;
            }

            /* =========================
           🖼️ IMAGES FIX
        ========================= */

            $imagePath = null;

            // 👉 controlla se esiste 'image'
            if (!empty($team['image'])) {
                $imagePath = $team['image'];
            }
            // 👉 fallback se il JSON usa 'images'
            elseif (!empty($team['images']['logo'])) {
                $imagePath = $team['images']['logo'];
            }

            $images = $imagePath ? ['logo' => $imagePath] : null;

            /* =========================
           INSERT
        ========================= */

            DB::table('teams')->insert([
                'name' => $team['name'],
                'country' => $team['country'] ?? null,
                'city' => $team['city'] ?? null,
                'stadium' => $team['stadium'] ?? null,
                'attack' => $team['attack'] ?? 1,
                'defense' => $team['defense'] ?? 1,
                'home_factor' => $team['home_factor'] ?? 1,
                'colors' => !empty($team['colors']) ? json_encode($team['colors']) : null,
                'images' => $images ? json_encode($images) : null,
                'created' => date('Y-m-d H:i:s')
            ]);

            $existingNames[] = $name;
        }
    }

    public static function getTeamStats($teamId)
    {
        $stats = DB::table('teams')->select('attack, defense, home_factor')->where('id', '=', $teamId)->first();
        return [
            'attack' => (float) $stats['attack'] ?? null,
            'defense' => (float) $stats['defense'] ?? null,
            'home_factor' => (float) $stats['home_factor'] ?? null,
        ];
    }

    public static function getTeamNameById($teamId)
    {
        return DB::table('teams')->select('name')->where('id', '=', $teamId)->first()['name'];
    }

    public static function orderTeamsByName($teams)
    {
        $teamsName = [];

        foreach ($teams as $team) {
            $teamsName[$team] = self::getTeamNameById($team);
        }

        // ordina per nome squadra mantenendo gli ID come chiavi
        asort($teamsName, SORT_NATURAL | SORT_FLAG_CASE);

        return $teamsName;
    }
}
