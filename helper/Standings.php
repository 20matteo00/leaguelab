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
        ],
        [
            'subaction' => 'expected',
            'icon' => 'x-circle',
            'label' => 'Prevista'
        ]

    ];

    public static function renderStandingsMenu($page, $urlParams, $round_trip)
    {
        $menu = self::$menu;
?>

        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <?php if ($round_trip == 0 && ($m['subaction'] == 'first-leg' || $m['subaction'] == 'second-leg'))
                    continue; ?>
                <div class="col">
                    <?php $urlParams['subaction'] = $m['subaction']; ?>
                    <?= Link::a(
                        $page,
                        '<i class="bi bi-' . $m['icon'] . ' me-2"></i> ' . $m['label'],
                        $urlParams,
                        [
                            'class' => 'btn btn-outline-info w-100 p-2'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderProgressMenu($page, $urlParams, $rounds)
    {
    ?>

        <div class="row g-2 mb-4">
            <?php for ($i = 1; $i <= $rounds; $i++): ?>
                <?php $urlParams['subaction'] = $i; ?>
                <div class="col">
                    <?= Link::a(
                        $page,
                        $i,
                        $urlParams,
                        [
                            'class' => 'btn btn-outline-info w-100 p-2'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php
    }

    private static function emptyStanding(): array
    {
        return [
            'pts' => 0,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'gf' => 0, // goal fatti
            'ga' => 0, // goal subiti
            'gd' => 0, // differenza reti
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
            if ($b['pts'] !== $a['pts'])
                return $b['pts'] <=> $a['pts'];
            if ($b['gd'] !== $a['gd'])
                return $b['gd'] <=> $a['gd'];
            if ($b['gf'] !== $a['gf'])
                return $b['gf'] <=> $a['gf'];
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
            if ($sh === null || $sa === null)
                continue;

            $sh = (int) $sh;
            $sa = (int) $sa;
            $round = (int) $m['round'];
            $home = $m['team_home_id'];
            $away = $m['team_away_id'];

            // Filtra per andata/ritorno
            if ($filter === 'first-leg' && ($half === null || $round > $half))
                continue;
            if ($filter === 'second-leg' && ($half === null || $round <= $half))
                continue;

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

    public static function renderStandingsTable(array $standings, array $teams, string $title, $comp_level = [], $countEdition = false): void
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
                        <?php if ($countEdition): ?>
                            <th title="Prima Edizione">#F.E.</th>
                            <th title="Ultima Edizione">#L.E.</th>
                            <th title="Edizioni partecipate">Ed</th>
                            <th title="Media Punti per Edizione">μ</th>
                        <?php endif; ?>
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
                            if ($pos <= $comp_level['pro'])
                                $class = 'bg-success text-white';
                            elseif ($pos > $rel)
                                $class = 'bg-danger text-white';
                        }
                        ?>
                        <tr>
                            <td class="<?= $class ?>"><?= $pos++ ?></td>
                            <td class="text-start">
                                <?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                            </td>
                            <?php if ($countEdition): ?>
                                <td><?= $s['first_edition'] ?? 0 ?></td>
                                <td><?= $s['last_edition'] ?? 0 ?></td>
                                <td><?= $s['editions'] ?? 0 ?></td>
                                <td><?= round($s['pts'] / $s['editions'], 2) ?></td>
                            <?php endif; ?>
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
        if ($subaction == 'expected') {
            Standings::renderExpectedStandings($seasonId, $level);
            return;
        }
        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teams = Teams::orderTeamsByName($teams);
        $comp_params = [];

        $matchesRaw = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        // Converti in array
        $matches = array_map(fn($m) => (array) $m, $matchesRaw);

        // Calcola half round per andata/ritorno
        $half = null;
        if ($round_trip && in_array($subaction, ['first-leg', 'second-leg'])) {
            $maxRound = max(array_column($matches, 'round') ?: [0]);
            $half = (int) ceil($maxRound / 2);
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
                'rel' => $found['relegation_spots'] ?? null,
                'pro' => $found['promotion_spots'] ?? null,
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
                'home' => 'Classifica Casa',
                'away' => 'Classifica Trasferta',
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
            $matchesUpTo = array_filter($allMatches, fn($m) => (int) $m['round'] <= $round);
            $standings = self::buildStandings(array_values($matchesUpTo), $teams, 'total');

            $top = array_slice($standings, 0, 2, true);
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
            $round = $rounds[$i];
            $leaderId = $leaders[$round];
            $span = 1;

            // Conta quante giornate consecutive ha lo stesso capolista
            while (
                $i + $span < count($rounds) &&
                $leaders[$rounds[$i + $span]] === $leaderId
            ) {
                $span++;
            }

            $groups[] = [
                'team_id' => $leaderId,
                'start' => $i,       // indice in $rounds
                'span' => $span,
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
                            $span = $group['span'];
                        ?>
                            <td colspan="<?= $span ?>">
                                <?php if ($teamId === null): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block', false, false, ['abbr_name' => 3]) ?>
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
        $upToRound = (int) $subaction;

        if ($upToRound <= 0) {
            echo '<div class="alert alert-info">Seleziona una giornata dal menu.</div>';
            return;
        }

        $compId = DB::table('seasons')->select('competition_id')->where('id', '=', $seasonId)->first()['competition_id'];
        $comp_level = DB::table('competition_levels')->select('level, relegation_spots, promotion_spots')->where('competition_id', '=', $compId)->get();

        $result = array_values(array_filter($comp_level, function ($item) use ($level) {
            return $item['level'] == $level;
        }));

        $found = $result[0] ?? null;

        $comp_params[$level] = [
            'rel' => $found['relegation_spots'] ?? null,
            'pro' => $found['promotion_spots'] ?? null,
        ];

        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teams = Teams::orderTeamsByName($teams);

        $allMatchesRaw = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        $allMatches = array_map(fn($m) => (array) $m, $allMatchesRaw);

        // Genera l'array dei round giocati direttamente qui
        $rounds = array_values(array_unique(array_filter(
            array_column($allMatches, 'round'),
            fn($r) => $r !== null
        )));
        sort($rounds);

        $matchesUpTo = array_filter($allMatches, fn($m) => (int) $m['round'] <= $upToRound);

        $standings = self::buildStandings(array_values($matchesUpTo), $teams, 'total');

        self::renderLeaderTimeline($allMatches, $teams, $rounds);

        self::renderStandingsTable($standings, $teams, 'Classifica dopo la Giornata ' . $upToRound, $comp_params[$level]);
    }


    private static function buildAllTimeStandings($compId, $level): array
    {
        $seasons = DB::table('seasons')
            ->select('id')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '99')
            ->get();

        $seasonIds = array_column($seasons, 'id');
        if (empty($seasonIds))
            return [];

        // ✅ MATCH SOLO DI QUEL LIVELLO
        $matches = DB::table('matches')
            ->whereIn('season_id', $seasonIds)
            ->where('level', '=', $level)
            ->get();

        // ✅ TEAM SOLO DI QUEL LIVELLO
        $seasonTeams = DB::table('season_teams')
            ->whereIn('season_id', $seasonIds)
            ->where('level', '=', $level)
            ->get();

        $teams = [];
        $editions = [];
        $firstEdition = [];
        $lastEdition = [];
        foreach ($seasonTeams as $row) {
            $teamId = (int) $row['team_id'];
            $teams[$teamId] = $teamId;
            $editions[$teamId] = ($editions[$teamId] ?? 0) + 1;
            $firstEdition[$teamId] = Seasons::getSeasonByTeamLevel($seasonIds, $teamId, $level, 'first');
            $lastEdition[$teamId] = Seasons::getSeasonByTeamLevel($seasonIds, $teamId, $level, 'last');
        }

        // classifica
        $standings = Standings::buildStandings($matches, $teams, 'total');

        // aggiungo edizioni
        foreach ($standings as &$row) {
            $row['editions'] = $editions[$row['team_id']] ?? 0;
            $row['first_edition'] = $firstEdition[$row['team_id']] ?? 0;
            $row['last_edition'] = $lastEdition[$row['team_id']] ?? 0;
        }
        unset($row);

        return [
            'teams' => $teams,
            'standings' => $standings,
        ];
    }

    public static function renderAllTimeStandings($compId, $level): void
    {
        $data = self::buildAllTimeStandings($compId, $level);

        if (empty($data)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">🏆 Classifica All-Time - Livello <?= $level ?></h5>

            <?php self::renderStandingsTable(
                $data['standings'],
                $data['teams'],
                '',
                [],
                true
            ); ?>
        </div>
    <?php
    }

    private static function buildHallOfFame($compId, $level): array
    {
        $seasons = DB::table('seasons')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '99')
            ->orderBy('season_year')
            ->get();

        if (empty($seasons))
            return [];

        $podio = [];
        $medaglie = [];

        foreach ($seasons as $season) {
            $sid = $season['id'];

            // team del livello
            $seasonTeamsRaw = DB::table('season_teams')
                ->where('season_id', '=', $sid)
                ->where('level', '=', $level)
                ->get();

            if (empty($seasonTeamsRaw))
                continue;

            $teamsAssoc = array_column($seasonTeamsRaw, 'team_id', 'team_id');

            // match del livello
            $matches = DB::table('matches')
                ->where('season_id', '=', $sid)
                ->where('level', '=', $level)
                ->get();

            $standings = self::buildStandings($matches, $teamsAssoc, 'total');
            $top3 = array_slice(array_values($standings), 0, 3);

            if (!empty($top3)) {
                $podio[$sid] = [
                    'season_year' => $season['season_year'],
                    'top3' => $top3
                ];

                // conteggio medaglie
                foreach ($top3 as $pos => $entry) {
                    $tid = $entry['team_id'];

                    if (!isset($medaglie[$tid])) {
                        $medaglie[$tid] = [1 => 0, 2 => 0, 3 => 0];
                    }

                    $medaglie[$tid][$pos + 1]++;
                }
            }
        }

        // ordinamento medaglie
        uasort($medaglie, function ($a, $b) {
            if ($b[1] !== $a[1])
                return $b[1] <=> $a[1];
            if ($b[2] !== $a[2])
                return $b[2] <=> $a[2];
            return $b[3] <=> $a[3];
        });

        return [
            'podio' => $podio,
            'medaglie' => $medaglie
        ];
    }

    public static function renderHallOfFame($compId, $level): void
    {
        $data = self::buildHallOfFame($compId, $level);

        if (empty($data)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        $podio = $data['podio'];
        $medaglie = $data['medaglie'];

        $medalColors = [
            1 => ['bg' => '#FFD700', 'text' => '#000'],
            2 => ['bg' => '#C0C0C0', 'text' => '#000'],
            3 => ['bg' => '#CD7F32', 'text' => '#fff'],
        ];
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">🏆 Albo d'Oro - Livello <?= $level ?></h5>

            <div class="row g-4">
                <?php foreach ($podio as $sid => $row): ?>
                    <?php $top3 = $row['top3']; ?>

                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-dark text-white text-center fw-bold">
                                <?= Link::a(
                                    'season',
                                    '📅 ' . htmlspecialchars($row['season_year']),
                                    ['id' => $sid]
                                ) ?>
                            </div>

                            <div class="card-body d-flex flex-column justify-content-end">
                                <div class="d-flex align-items-end justify-content-center gap-2" style="height: 180px;">

                                    <?php foreach ([0, 1, 2] as $visualPos): ?>
                                        <div class="d-flex flex-column align-items-center" style="flex:1">
                                            <?php if (isset($top3[$visualPos])): ?>
                                                <div class="mb-1">
                                                    <?php Teams::renderTeams($top3[$visualPos]['team_id'], 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                                </div>

                                                <div class="w-100 rounded-top d-flex flex-column align-items-center justify-content-center py-2"
                                                    style="background:<?= $medalColors[$visualPos + 1]['bg'] ?>;
                                                       color:<?= $medalColors[$visualPos + 1]['text'] ?>;
                                                       height:<?= [125, 100, 75][$visualPos] ?>px">

                                                    <div style="font-size:1.5rem">
                                                        <?= ['🥇', '🥈', '🥉'][$visualPos] ?>
                                                    </div>
                                                    <div class="fw-bold"><?= $top3[$visualPos]['pts'] ?> pts</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabella medaglie -->
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th class="text-start">Squadra</th>
                            <th>🥇</th>
                            <th>🥈</th>
                            <th>🥉</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pos = 1;
                        foreach ($medaglie as $tid => $m): ?>
                            <tr>
                                <td class="text-muted"><?= $pos++ ?></td>
                                <td class="text-start">
                                    <?php Teams::renderTeams($tid, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                </td>
                                <td><strong><?= $m[1] ?: '-' ?></strong></td>
                                <td><?= $m[2] ?: '-' ?></td>
                                <td><?= $m[3] ?: '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    <?php
    }

    private static function buildHallOfFameKnockout($compId, $level): array
    {
        $seasons = DB::table('seasons')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '99')
            ->orderBy('season_year')
            ->get();

        if (empty($seasons))
            return [];

        $podio = [];
        $medaglie = [];

        foreach ($seasons as $season) {
            $sid = $season['id'];

            $matches = DB::table('matches')
                ->where('season_id', '=', $sid)
                ->where('level', '=', $level)
                ->get();

            if (empty($matches))
                continue;

            // Finale (phase=1)
            $finale = array_filter($matches, fn($m) => (int) $m['phase'] === 1);
            if (empty($finale))
                continue;

            $pairsFinale = Matches::buildPairs($finale);
            $pair = reset($pairsFinale);

            if (!$pair['scoreA'] && !$pair['scoreB'])
                continue; // finale non giocata

            if ($pair['scoreA'] > $pair['scoreB']) {
                $winner = $pair['teamA'];
                $loser = $pair['teamB'];
            } else {
                $winner = $pair['teamB'];
                $loser = $pair['teamA'];
            }

            // Semifinali (phase=2) — prendi i perdenti
            $semi = array_filter($matches, fn($m) => (int) $m['phase'] === 2);
            $pairsSemi = Matches::buildPairs($semi);
            $semiFinalisti = [];
            foreach ($pairsSemi as $p) {
                if ($p['scoreA'] > $p['scoreB'])
                    $semiFinalisti[] = $p['teamB'];
                elseif ($p['scoreB'] > $p['scoreA'])
                    $semiFinalisti[] = $p['teamA'];
            }

            $top = [
                0 => ['team_id' => $winner],
                1 => ['team_id' => $loser],
            ];
            // Semifinaliste a pari merito (pos 3)
            foreach ($semiFinalisti as $t) {
                $top[] = ['team_id' => $t];
            }

            $podio[$sid] = [
                'season_year' => $season['season_year'],
                'top' => $top,
                'semiFinalisti' => $semiFinalisti,
            ];

            // Medaglie: 1=vincitore, 2=finalista, 3=semifinalisti
            foreach ([0 => 1, 1 => 2] as $idx => $medal) {
                $tid = $top[$idx]['team_id'];
                if (!isset($medaglie[$tid]))
                    $medaglie[$tid] = [1 => 0, 2 => 0, 3 => 0];
                $medaglie[$tid][$medal]++;
            }
            foreach ($semiFinalisti as $tid) {
                if (!isset($medaglie[$tid]))
                    $medaglie[$tid] = [1 => 0, 2 => 0, 3 => 0];
                $medaglie[$tid][3]++;
            }
        }

        uasort($medaglie, function ($a, $b) {
            if ($b[1] !== $a[1])
                return $b[1] <=> $a[1];
            if ($b[2] !== $a[2])
                return $b[2] <=> $a[2];
            return $b[3] <=> $a[3];
        });

        return ['podio' => $podio, 'medaglie' => $medaglie];
    }

    public static function renderHallOfFameKnockout($compId, $level): void
    {
        $data = self::buildHallOfFameKnockout($compId, $level);

        if (empty($data)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        $podio = $data['podio'];
        $medaglie = $data['medaglie'];

        $medalColors = [
            1 => ['bg' => '#FFD700', 'text' => '#000'],
            2 => ['bg' => '#C0C0C0', 'text' => '#000'],
            3 => ['bg' => '#CD7F32', 'text' => '#fff'],
        ];
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">🏆 Albo d'Oro - Livello <?= $level ?></h5>

            <div class="row g-4">
                <?php foreach ($podio as $sid => $row): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-dark text-white text-center fw-bold">
                                <?= Link::a(
                                    'season',
                                    '📅 ' . htmlspecialchars($row['season_year']),
                                    ['id' => $sid]
                                ) ?>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-end">

                                <!-- Podio 1°/2° -->
                                <div class="d-flex align-items-end justify-content-center gap-2 mb-3" style="height:180px;">
                                    <?php foreach ([1, 0, 1] as $visualPos => $dataIdx):
                                    // Classico podio: 2°(sx) 1°(centro) non serve il terzo qui
                                    // Usiamo layout [2°, 1°]
                                    endforeach; ?>

                                    <?php
                                    $layout = [
                                        ['idx' => 0, 'medal' => 1, 'height' => 125, 'emoji' => '🥇'],
                                        ['idx' => 1, 'medal' => 2, 'height' => 100, 'emoji' => '🥈'],
                                    ];
                                    foreach ($layout as $col):
                                        $entry = $row['top'][$col['idx']] ?? null;
                                    ?>
                                        <div class="d-flex flex-column align-items-center" style="flex:1">
                                            <?php if ($entry): ?>
                                                <div class="mb-1">
                                                    <?php Teams::renderTeams($entry['team_id'], 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                                </div>
                                                <div class="w-100 rounded-top d-flex flex-column align-items-center justify-content-center py-2"
                                                    style="background:<?= $medalColors[$col['medal']]['bg'] ?>;
                                                       color:<?= $medalColors[$col['medal']]['text'] ?>;
                                                       height:<?= $col['height'] ?>px">
                                                    <div style="font-size:1.5rem"><?= $col['emoji'] ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Semifinaliste a pari merito -->
                                <?php if (!empty($row['semiFinalisti'])): ?>
                                    <div class="border-top pt-2">
                                        <div class="text-muted small text-center mb-1">🥉 Semifinaliste</div>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <?php foreach ($row['semiFinalisti'] as $tid): ?>
                                                <?php Teams::renderTeams($tid, 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabella medaglie -->
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th class="text-start">Squadra</th>
                            <th>🥇</th>
                            <th>🥈</th>
                            <th>🥉</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $pos = 1;
                        foreach ($medaglie as $tid => $m): ?>
                            <tr>
                                <td class="text-muted"><?= $pos++ ?></td>
                                <td class="text-start">
                                    <?php Teams::renderTeams($tid, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                </td>
                                <td><strong><?= $m[1] ?: '-' ?></strong></td>
                                <td><?= $m[2] ?: '-' ?></td>
                                <td><?= $m[3] ?: '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php
    }

    public static function renderExpectedStandings($seasonId, $level): void
    {
        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teamsAssoc = array_combine($teams, $teams);

        $avgDefense = 500; // difesa avversaria media di riferimento
        $avgStrength = ['defense' => $avgDefense, 'home_boost' => 1.0, 'attack' => $avgDefense];

        $rows = [];
        foreach ($teamsAssoc as $teamId) {
            $s = Calendar::getTeamStrength($teamId);
            $forze = Calendar::getForzaEffettiva($s, $avgStrength);

            $rows[$teamId] = [
                'team_id' => $teamId,
                'attack' => round($s['attack']),
                'defense' => round($s['defense']),
                'home_factor' => round($s['home_factor']),
                'forza_home' => round($forze['forza_home']),
                'forza_away' => round($forze['forza_away']),
                'forza_avg' => round(($forze['forza_home'] + $forze['forza_away']) / 2),
            ];
        }

        uasort($rows, fn($a, $b) => $b['forza_avg'] <=> $a['forza_avg']);
    ?>
        <h6 class="fw-bold mt-4 mb-2">Classifica Prevista</h6>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th class="text-start">Squadra</th>
                        <th title="Attacco">ATK</th>
                        <th title="Difesa">DEF</th>
                        <th title="Fattore Casa">🏠</th>
                        <th title="Forza Media">F.Media</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $pos = 1;
                    foreach ($rows as $teamId => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $pos++ ?></td>
                            <td class="text-start">
                                <?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                            </td>
                            <td><?= $r['attack'] ?></td>
                            <td><?= $r['defense'] ?></td>
                            <td><?= $r['home_factor'] ?></td>
                            <td>
                                <strong><?= $r['forza_avg'] ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php
    }

    public static function getPositionTeamBySeason($teamId, $seasonId, $level)
    {
        $matches = DB::table('matches')->where('season_id', '=', $seasonId)->where('level', '=', $level)->get();
        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasonId)->where('level', '=', $level)->get();
        $teams = array_column($teams, 'team_id');
        $finalteams = [];
        foreach ($teams as $team) {
            $finalteams[$team] = DB::table('teams')->select('name')->where('id', '=', $team)->first()['name'];
        }
        $results = self::buildStandings($matches, $finalteams, 'total');

        $position = 1;
        foreach ($results as $id => $team) {
            if ($id == $teamId) {
                break;
            }
            $position++;
        }
        return $position;
    }
}
