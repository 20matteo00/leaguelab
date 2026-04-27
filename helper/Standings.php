<?php
class Standings
{

    public static $menu = [
        [
            'subaction' => 'total',
            'icon' => 'trophy',
            'label' => 'Totale'
        ],
        [
            'subaction' => 'home',
            'icon' => 'house-door',
            'label' => 'Casa'
        ],
        [
            'subaction' => 'away',
            'icon' => 'airplane',
            'label' => 'Trasferta'
        ],
        [
            'subaction' => 'first-leg',
            'icon' => 'arrow-right-circle',
            'label' => 'Andata'
        ],
        [
            'subaction' => 'second-leg',
            'icon' => 'arrow-left-circle',
            'label' => 'Ritorno'
        ]

    ];

    public static function renderStandingsMenu($baseUrl, $level, $round_trip)
    {
        $menu = self::$menu;
?>

        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <?php if ($round_trip == 0 && ($m['subaction'] == 'first-leg' || $m['subaction'] == 'second-leg')) continue; ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&level=<?= $level ?>&action=standings&subaction=<?= $m['subaction'] ?>#content" class="btn btn-info w-100">
                        <i class="bi bi-<?= $m['icon'] ?>"></i> <?= $m['label'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderProgressMenu($baseUrl, $level, $rounds)
    {
    ?>

        <div class="row g-2 mb-4">
            <?php for ($i = 1; $i <= $rounds; $i++): ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&level=<?= $level ?>&action=trend&subaction=<?= $i ?>#content" class="btn btn-info w-100 p-2">
                        <?= $i ?>
                    </a>
                </div>
            <?php endfor; ?>
        </div>
    <?php
    }

    private static function emptyStanding(): array
    {
        return [
            'pts'  => 0,
            'played' => 0,
            'won'  => 0,
            'drawn' => 0,
            'lost' => 0,
            'gf'   => 0, // goal fatti
            'ga'   => 0, // goal subiti
            'gd'   => 0, // differenza reti
        ];
    }

    private static function applyMatch(array &$standing, int $gf, int $ga): void
    {
        $standing['played']++;
        $standing['gf'] += $gf;
        $standing['ga'] += $ga;
        $standing['gd'] += ($gf - $ga);

        if ($gf > $ga) {
            $standing['won']++;
            $standing['pts'] += 3;
        } elseif ($gf === $ga) {
            $standing['drawn']++;
            $standing['pts'] += 1;
        } else {
            $standing['lost']++;
        }
    }

    private static function sortStandingsWithNames(array $standings, array $teams): array
    {
        uasort($standings, function ($a, $b) use ($teams) {
            if ($b['pts'] !== $a['pts']) return $b['pts'] <=> $a['pts'];
            if ($b['gd']  !== $a['gd'])  return $b['gd']  <=> $a['gd'];
            if ($b['gf']  !== $a['gf'])  return $b['gf']  <=> $a['gf'];
            $nameA = $teams[$a['team_id']] ?? '';
            $nameB = $teams[$b['team_id']] ?? '';
            return strcmp($nameA, $nameB);
        });
        return $standings;
    }

    public static function buildStandings(array $matches, array $teams, string $filter, ?int $half = null): array
    {
        $standings = [];
        foreach ($teams as $teamId => $teamName) {
            $standings[$teamId] = self::emptyStanding();
            $standings[$teamId]['team_id'] = $teamId;
        }

        foreach ($matches as $m) {
            $sh = $m['score_home'];
            $sa = $m['score_away'];
            if ($sh === null || $sa === null) continue;

            $sh    = (int)$sh;
            $sa    = (int)$sa;
            $round = (int)$m['round'];
            $home  = $m['team_home_id'];
            $away  = $m['team_away_id'];

            // Filtra per andata/ritorno
            if ($filter === 'first-leg'  && ($half === null || $round > $half))  continue;
            if ($filter === 'second-leg' && ($half === null || $round <= $half)) continue;

            // Applica alla squadra di casa (sempre, tranne se filtro = solo trasferta)
            if ($filter !== 'away' && isset($standings[$home])) {
                self::applyMatch($standings[$home], $sh, $sa);
            }

            // Applica alla squadra ospite (sempre, tranne se filtro = solo casa)
            if ($filter !== 'home' && isset($standings[$away])) {
                self::applyMatch($standings[$away], $sa, $sh);
            }
        }

        return self::sortStandingsWithNames($standings, $teams);
    }

    private static function renderStandingsTable(array $standings, array $teams, string $title, $comp_level = []): void
    {
    ?>
        <?php if ($title): ?>
            <h6 class="fw-bold mt-4 mb-2"><?= htmlspecialchars($title) ?></h6>
        <?php endif; ?>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th class="text-start">Squadra</th>
                        <th title="Punti">Pts</th>
                        <th title="Partite giocate">PG</th>
                        <th title="Vittorie">V</th>
                        <th title="Pareggi">P</th>
                        <th title="Sconfitte">S</th>
                        <th title="Goal fatti">GF</th>
                        <th title="Goal subiti">GS</th>
                        <th title="Differenza reti">DR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pos = 1;
                    $count_teams = count($teams);
                    foreach ($standings as $teamId => $s): ?>
                        <?php
                        $class = 'text-muted';
                        if (!empty($comp_level)) {
                            $rel = $count_teams - $comp_level['rel'];
                            if ($pos <= $comp_level['pro']) $class = 'bg-success text-white';
                            elseif ($pos > $rel) $class = 'bg-danger text-white';
                        }
                        ?>
                        <tr>
                            <td class="<?= $class ?>"><?= $pos++ ?></td>
                            <td class="text-start">
                                <?= Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                            </td>
                            <td><strong><?= $s['pts'] ?></strong></td>
                            <td><?= $s['played'] ?></td>
                            <td class="text-success"><?= $s['won'] ?></td>
                            <td class="text-warning"><?= $s['drawn'] ?></td>
                            <td class="text-danger"><?= $s['lost'] ?></td>
                            <td><?= $s['gf'] ?></td>
                            <td><?= $s['ga'] ?></td>
                            <td><?= $s['gd'] > 0 ? '+' . $s['gd'] : $s['gd'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($comp_level)): ?>
                <div class="d-flex gap-3 flex-wrap mt-2 mb-4 small">
                    <span class="badge bg-success text-white px-3 py-2">Promozione</span>
                    <span class="badge bg-danger text-white px-3 py-2">Retrocessione</span>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    public static function renderStandings($seasonId, $level, $subaction, $round_trip): void
    {
        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teams = Teams::orderTeamsByName($teams);
        $comp_params = [];

        $matchesRaw = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        // Converti in array
        $matches = array_map(fn($m) => (array)$m, $matchesRaw);

        // Calcola half round per andata/ritorno
        $half = null;
        if ($round_trip && in_array($subaction, ['first-leg', 'second-leg'])) {
            $maxRound = max(array_column($matches, 'round') ?: [0]);
            $half = (int)ceil($maxRound / 2);
        }

        // Andata/ritorno richiede round_trip attivo
        if (in_array($subaction, ['first-leg', 'second-leg']) && !$round_trip) {
            echo '<div class="alert alert-warning">Andata/Ritorno non disponibile per questa competizione.</div>';
            return;
        }

        if ($subaction == 'total') {
            $compId = DB::table('seasons')->select('competition_id')->where('id', '=', $seasonId)->first()['competition_id'];
            $comp_level = DB::table('competition_levels')->select('level, relegation_spots, promotion_spots')->where('competition_id', '=', $compId)->get();

            $result = array_values(array_filter($comp_level, function ($item) use ($level) {
                return $item['level'] == $level;
            }));

            $found = $result[0] ?? null;

            $comp_params[$level] = [
                'rel' => $found['relegation_spots'],
                'pro' => $found['promotion_spots'],
            ];
        }

        if ($subaction === 'first-leg') {
            $s1 = self::buildStandings($matches, $teams, 'first-leg', $half);
            self::renderStandingsTable($s1, $teams, 'Classifica Andata');
        } elseif ($subaction === 'second-leg') {
            $s2 = self::buildStandings($matches, $teams, 'second-leg', $half);
            self::renderStandingsTable($s2, $teams, 'Classifica Ritorno');
        } else {
            // total / home / away: una sola tabella
            $label = match ($subaction) {
                'total' => '',
                'home'  => 'Classifica Casa',
                'away'  => 'Classifica Trasferta',
                default => '',
            };
            $standings = self::buildStandings($matches, $teams, $subaction);
            self::renderStandingsTable($standings, $teams, $label, $comp_params[$level] ?? []);
        }
    }

    private static function getLeaderPerRound(array $allMatches, array $teams, array $rounds): array
    {
        $leaders = [];

        foreach ($rounds as $round) {
            $matchesUpTo = array_filter($allMatches, fn($m) => (int)$m['round'] <= $round);
            $standings   = self::buildStandings(array_values($matchesUpTo), $teams, 'total');

            $top       = array_slice($standings, 0, 2, true);
            $topValues = array_values($top);

            if (count($topValues) < 1) {
                $leaders[$round] = null;
                continue;
            }

            if (count($topValues) >= 2 && $topValues[0]['pts'] === $topValues[1]['pts']) {
                $leaders[$round] = null;
            } else {
                $leaders[$round] = array_key_first($top);
            }
        }

        return $leaders;
    }

    public static function renderLeaderTimeline($matches, $teams, $rounds): void
    {

        $leaders = self::getLeaderPerRound($matches, $teams, $rounds);

        // Costruisce i gruppi con rowspan
        // Es: [teamId, startRound, span]
        $groups = [];
        $i = 0;
        while ($i < count($rounds)) {
            $round    = $rounds[$i];
            $leaderId = $leaders[$round];
            $span     = 1;

            // Conta quante giornate consecutive ha lo stesso capolista
            while (
                $i + $span < count($rounds) &&
                $leaders[$rounds[$i + $span]] === $leaderId
            ) {
                $span++;
            }

            $groups[] = [
                'team_id'    => $leaderId,
                'start'      => $i,       // indice in $rounds
                'span'       => $span,
            ];

            $i += $span;
        }

    ?>
        <div class="table-responsive mt-4">
            <table class="table table-bordered align-middle text-center shadow-sm mb-0">
                <tbody>
                    <!-- Riga giornate -->
                    <tr class="table-dark">
                        <th class="text-nowrap px-3">Giornata</th>
                        <?php foreach ($rounds as $round): ?>
                            <th><?= $round ?></th>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Riga capolista con rowspan -->
                    <tr>
                        <th class="table-dark text-nowrap px-3">Capolista</th>
                        <?php
                        foreach ($groups as $group):
                            $teamId = $group['team_id'];
                            $span   = $group['span'];
                        ?>
                            <td colspan="<?= $span ?>">
                                <?php if ($teamId === null): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <?= Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block', false, false, ['abbr_name' => 3]) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
<?php
    }

    public static function renderProgress($seasonId, $level, $subaction): void
    {
        $upToRound = (int)$subaction;

        if ($upToRound <= 0) {
            echo '<div class="alert alert-info">Seleziona una giornata dal menu.</div>';
            return;
        }

        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teams = Teams::orderTeamsByName($teams);

        $allMatchesRaw = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        $allMatches = array_map(fn($m) => (array)$m, $allMatchesRaw);

        // Genera l'array dei round giocati direttamente qui
        $rounds = array_values(array_unique(array_filter(
            array_column($allMatches, 'round'),
            fn($r) => $r !== null
        )));
        sort($rounds);

        $matchesUpTo = array_filter($allMatches, fn($m) => (int)$m['round'] <= $upToRound);

        $standings = self::buildStandings(array_values($matchesUpTo), $teams, 'total');

        self::renderLeaderTimeline($allMatches, $teams, $rounds);

        self::renderStandingsTable($standings, $teams, 'Classifica dopo la Giornata ' . $upToRound);
    }
}
