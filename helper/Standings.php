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
                            <th title="Edizioni partecipate">Ed</th>
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
                            if ($pos <= $comp_level['pro']) $class = 'bg-success text-white';
                            elseif ($pos > $rel) $class = 'bg-danger text-white';
                        }
                        ?>
                        <tr>
                            <td class="<?= $class ?>"><?= $pos++ ?></td>
                            <td class="text-start">
                                <?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                            </td>
                            <?php if ($countEdition): ?>
                                <td><?= $s['editions'] ?? 0 ?></td>
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


    private static function buildAllTimeStandings($compId): array
    {
        $seasons = DB::table('seasons')
            ->select('id')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '2')
            ->get();
        $seasonIds = array_column($seasons, 'id');

        if (empty($seasonIds)) return [];

        $matches = DB::table('matches')
            ->whereIn('season_id', $seasonIds)
            ->get();

        $seasonTeams = DB::table('season_teams')
            ->whereIn('season_id', $seasonIds)
            ->get();

        $teamsByLevel = [];
        $editionsByLevel = []; // [levelNum][teamId] = contatore stagioni

        foreach ($seasonTeams as $row) {
            $level    = (int)$row['level'];
            $teamId   = (int)$row['team_id'];
            $seasonId = (int)$row['season_id'];

            $teamsByLevel[$level][$teamId] = $teamId;
            $editionsByLevel[$level][$teamId] = ($editionsByLevel[$level][$teamId] ?? 0) + 1;
        }

        ksort($teamsByLevel);

        $result = [];
        foreach ($teamsByLevel as $levelNum => $teamsAssoc) {
            $levelMatches = array_values(array_filter(
                $matches,
                fn($m) => (int)$m['level'] === $levelNum
            ));

            $standings = Standings::buildStandings($levelMatches, $teamsAssoc, 'total');

            // Inietta le edizioni in ogni riga della classifica
            foreach ($standings as &$row) {
                $row['editions'] = $editionsByLevel[$levelNum][$row['team_id']] ?? 0;
            }
            unset($row);

            $result[$levelNum] = [
                'teams'     => $teamsAssoc,
                'standings' => $standings,
            ];
        }

        return $result;
    }

    public static function renderAllTimeStandings($compId): void
    {
        $byLevel = self::buildAllTimeStandings($compId);

        if (empty($byLevel)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">🏆 Classifica All-Time</h5>

            <?php if (count($byLevel) > 1): ?>
                <!-- Tab nav -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php foreach ($byLevel as $levelNum => $_): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $levelNum === array_key_first($byLevel) ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#alltime-level-<?= $levelNum ?>"
                                type="button">
                                Livello <?= $levelNum ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content">
                    <?php foreach ($byLevel as $levelNum => $data): ?>
                        <div class="tab-pane fade <?= $levelNum === array_key_first($byLevel) ? 'show active' : '' ?>"
                            id="alltime-level-<?= $levelNum ?>">
                            <?php self::renderStandingsTable(
                                $data['standings'],
                                $data['teams'],
                                '',
                                [],
                                true
                            ) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Livello singolo, niente tab -->
                <?php foreach ($byLevel as $levelNum => $data): ?>
                    <?php self::renderStandingsTable(
                        $data['standings'],
                        $data['teams'],
                        'Livello ' . $levelNum,
                        [],
                        true
                    ) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php
    }

    public static function renderHallOfFame($compId): void
    {
        $seasons = DB::table('seasons')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '2')
            ->orderBy('season_year')
            ->get();

        if (empty($seasons)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        $seasonIds = array_column($seasons, 'id');
        $seasonsById = array_column($seasons, null, 'id');

        // Recupera tutti i livelli configurati
        $compLevels = DB::table('competition_levels')
            ->where('competition_id', '=', $compId)
            ->orderBy('level')
            ->get();
        $levelNums = array_column($compLevels, 'level');

        // Se nessun livello configurato, usa il livello 1 di default
        if (empty($levelNums)) $levelNums = [1];

        // Per ogni livello, per ogni stagione, calcola il podio
        $podioByLevel = [];
        foreach ($levelNums as $levelNum) {
            foreach ($seasons as $season) {
                $sid = $season['id'];

                $seasonTeamsRaw = DB::table('season_teams')
                    ->where('season_id', '=', $sid)
                    ->where('level', '=', $levelNum)
                    ->get();

                if (empty($seasonTeamsRaw)) continue;

                $teamsAssoc = array_column($seasonTeamsRaw, 'team_id', 'team_id');

                $matches = DB::table('matches')
                    ->where('season_id', '=', $sid)
                    ->where('level', '=', $levelNum)
                    ->get();

                $standings = self::buildStandings($matches, $teamsAssoc, 'total');
                $top3 = array_slice(array_values($standings), 0, 3);

                if (!empty($top3)) {
                    $podioByLevel[$levelNum][$sid] = $top3;
                }
            }
        }

        if (empty($podioByLevel)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        $medalColors = [
            1 => ['bg' => '#FFD700', 'text' => '#000', 'label' => '🥇'],
            2 => ['bg' => '#C0C0C0', 'text' => '#000', 'label' => '🥈'],
            3 => ['bg' => '#CD7F32', 'text' => '#fff', 'label' => '🥉'],
        ];
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">🏆 Albo d'Oro</h5>

            <?php if (count($podioByLevel) > 1): ?>
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <?php foreach ($podioByLevel as $levelNum => $_): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $levelNum === array_key_first($podioByLevel) ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#hof-level-<?= $levelNum ?>"
                                type="button">
                                Livello <?= $levelNum ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tab-content">
                <?php foreach ($podioByLevel as $levelNum => $stagioni): ?>
                    <div class="tab-pane fade <?= $levelNum === array_key_first($podioByLevel) ? 'show active' : '' ?>"
                        id="hof-level-<?= $levelNum ?>">

                        <div class="row g-4">
                            <?php foreach ($stagioni as $sid => $top3): ?>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-dark text-white text-center fw-bold">
                                            📅 <?= htmlspecialchars($seasonsById[$sid]['season_year']) ?>
                                        </div>
                                        <div class="card-body d-flex flex-column justify-content-end">

                                            <!-- Podio -->
                                            <div class="d-flex align-items-end justify-content-center gap-2" style="height: 180px;">

                                                <!-- 2° posto - sinistra -->
                                                <div class="d-flex flex-column align-items-center" style="flex:1">
                                                    <?php if (isset($top3[1])): ?>
                                                        <div class="mb-1">
                                                            <?php Teams::renderTeams($top3[1]['team_id'], 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                                        </div>
                                                        <div class="w-100 rounded-top d-flex flex-column align-items-center justify-content-center py-2"
                                                            style="background:<?= $medalColors[2]['bg'] ?>;color:<?= $medalColors[2]['text'] ?>;height:100px">
                                                            <div style="font-size:1.5rem">🥈</div>
                                                            <div class="fw-bold"><?= $top3[1]['pts'] ?> pts</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 1° posto - centro -->
                                                <div class="d-flex flex-column align-items-center" style="flex:1">
                                                    <?php if (isset($top3[0])): ?>
                                                        <div class="mb-1">
                                                            <?php Teams::renderTeams($top3[0]['team_id'], 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                                        </div>
                                                        <div class="w-100 rounded-top d-flex flex-column align-items-center justify-content-center py-2"
                                                            style="background:<?= $medalColors[1]['bg'] ?>;color:<?= $medalColors[1]['text'] ?>;height:140px">
                                                            <div style="font-size:1.5rem">🥇</div>
                                                            <div class="fw-bold"><?= $top3[0]['pts'] ?> pts</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 3° posto - destra -->
                                                <div class="d-flex flex-column align-items-center" style="flex:1">
                                                    <?php if (isset($top3[2])): ?>
                                                        <div class="mb-1">
                                                            <?php Teams::renderTeams($top3[2]['team_id'], 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                                        </div>
                                                        <div class="w-100 rounded-top d-flex flex-column align-items-center justify-content-center py-2"
                                                            style="background:<?= $medalColors[3]['bg'] ?>;color:<?= $medalColors[3]['text'] ?>;height:70px">
                                                            <div style="font-size:1.5rem">🥉</div>
                                                            <div class="fw-bold"><?= $top3[2]['pts'] ?> pts</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php
                        // Dopo il loop delle stagioni, prima della chiusura del tab-pane, aggiungi:

                        // Calcola riepilogo medaglie per livello
                        $medaglie = [];
                        foreach ($stagioni as $sid => $top3) {
                            foreach ($top3 as $pos => $entry) {
                                $tid = $entry['team_id'];
                                if (!isset($medaglie[$tid])) {
                                    $medaglie[$tid] = [1 => 0, 2 => 0, 3 => 0];
                                }
                                $medaglie[$tid][$pos + 1]++;
                            }
                        }

                        // Ordina: prima per #1, poi #2, poi #3
                        uasort($medaglie, function ($a, $b) {
                            if ($b[1] !== $a[1]) return $b[1] <=> $a[1];
                            if ($b[2] !== $a[2]) return $b[2] <=> $a[2];
                            return $b[3] <=> $a[3];
                        });
                        ?>
                        <!-- Tabella riepilogo medaglie -->
                        <div class="table-responsive mt-4">
                            <table class="table table-hover align-middle shadow-sm text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-start">Squadra</th>
                                        <th title="Vittorie">🥇</th>
                                        <th title="Secondi posti">🥈</th>
                                        <th title="Terzi posti">🥉</th>
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
                <?php endforeach; ?>
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
            $s     = Calendar::getTeamStrength($teamId);
            $forze = Calendar::getForzaEffettiva($s, $avgStrength);

            $rows[$teamId] = [
                'team_id'     => $teamId,
                'attack'      => round($s['attack']),
                'defense'     => round($s['defense']),
                'home_factor' => round($s['home_factor']),
                'forza_home'  => round($forze['forza_home']),
                'forza_away'  => round($forze['forza_away']),
                'forza_avg'   => round(($forze['forza_home'] + $forze['forza_away']) / 2),
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
                        <th title="Forza Casa">F.Casa</th>
                        <th title="Forza Trasferta">F.Trasf</th>
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
                                <span class="badge bg-success"><?= $r['forza_home'] ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= $r['forza_away'] ?></span>
                            </td>
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
            $finalteams[$team] =  DB::table('teams')->select('name')->where('id', '=', $team)->first()['name'];
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
