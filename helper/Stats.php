<?php
class Stats
{

    public static $globalmenu = [
        [
            'subaction' => 'overview',
            'icon' => 'calendar3',
            'label' => 'Panoramica'
        ],
        [
            'subaction' => 'team_history',
            'icon' => 'arrows-vertical',
            'label' => 'Storia Squadra'
        ],
    ];

    public static $menu = [
        1 => [
            [
                'subaction' => 'overview',
                'icon' => 'calendar3',
                'label' => 'Panoramica'
            ],
            [
                'subaction' => 'team_matches',
                'icon' => 'dribbble',
                'label' => 'Incontri per Squadra'
            ],
        ],
        2 => [
            [
                'subaction' => 'overview',
                'icon' => 'calendar3',
                'label' => 'Panoramica'
            ],
            [
                'subaction' => 'team_matches',
                'icon' => 'dribbble',
                'label' => 'Incontri per Squadra'
            ],
        ],
        3 => [],
    ];

    public static function renderGlobalMenu($page, $urlParams)
    {
        $menu = self::$globalmenu;
?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <?php $urlParams['subaction'] = $m['subaction']; ?>
                <div class="col">
                    <?= Link::a(
                        $page,
                        '<i class="bi bi-' . $m['icon'] . '"></i> ' . $m['label'],
                        $urlParams,
                        [
                            'class' => 'btn btn-info w-100'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderMenu($page, $urlParams, $mode)
    {
        $menu = self::$menu[$mode];
    ?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <?php $urlParams['subaction'] = $m['subaction']; ?>
                <div class="col">
                    <?= Link::a(
                        $page,
                        '<i class="bi bi-' . $m['icon'] . '"></i> ' . $m['label'],
                        $urlParams,
                        [
                            'class' => 'btn btn-info w-100'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderGlobalStats($compId, $subaction, $mode)
    {
        switch ($subaction) {
            case 'overview':
                break;
            case 'team_history':
                $team = self::renderGlobalTeamStats($compId, $subaction);
                ($mode == 1) ? self::renderTeamHistoryByCompetition($compId, $team) : self::renderTeamHistoryByCompetitionKnockout($compId, $team);
                break;
            default:
                break;
        }
    }

    public static function renderStats($seasonId, $level, $subaction, $mode)
    {
        switch ($subaction) {
            case 'overview':
                break;
            case 'team_matches':
                $team = self::renderTeamStats($seasonId, $level, $subaction);
                self::renderMatchesBySeasonAndTeam($seasonId, $level, $team, $mode);
                break;
            default:
                break;
        }
    }

    private static function renderGlobalTeamStats($compId, $subaction)
    {
        $seasons = DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->first()['id'];

        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasons)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $teamSelected = $_POST['team'] ?? '';
    ?>
        <form action="index.php?page=competition&id=<?= $compId ?>&action=stats&subaction=<?= $subaction ?>" method="post"
            class="my-2">
            <?php self::renderFormStats($teams, $teamSelected) ?>
        </form>
    <?php
        return $teamSelected;
    }

    private static function renderTeamStats($seasonId, $level, $subaction)
    {

        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasonId)->where('level', '=', $level)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $teamSelected = $_POST['team'] ?? '';
    ?>
        <form action="index.php?page=season&id=<?= $seasonId ?>&level=<?= $level ?>&action=stats&subaction=<?= $subaction ?>"
            method="post" class="my-2">
            <?php self::renderFormStats($teams, $teamSelected) ?>
        </form>
    <?php
        return $teamSelected;
    }

    private static function renderFormStats($teams, $teamSelected)
    {
    ?>
        <div class="row">
            <div class="col form-group">
                <label for="team">Squadra</label>
                <select name="team" id="team" class="form-control">
                    <option value="">-- Scegli --</option>
                    <?php foreach ($teams as $key => $team): ?>
                        <option value="<?= $key ?>" <?= ($key == $teamSelected) ? 'selected' : '' ?>>
                            <?php Teams::renderTeams($key) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto form-group d-flex">
                <button type="submit" class="btn btn-primary mt-auto w-100">Invia</button>
            </div>
        </div>
    <?php
    }

    private static function renderTeamHistoryByCompetition($compId, $team)
    {
        if (empty($team))
            return;
        $seasons = DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->where('status', '=', '2')->get();
        $seasons = array_column($seasons, 'id');
        $bestYear = $badYear = [
            'year' => 0,
            'level' => 0,
            'position' => 0,
        ];
    ?>
        <div class="table-responsive my-5">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Stagione</th>
                        <th>Livello</th>
                        <th>Posizione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seasons as $season): ?>
                        <?php
                        $level = DB::table('season_teams')->select('level')->where('season_id', '=', $season)->where('team_id', '=', $team)->first()['level'];
                        $year = DB::table('seasons')->select('season_year')->where('id', '=', $season)->first()['season_year'];
                        $position = Standings::getPositionTeamBySeason($team, $season, $level);

                        $bestYear = self::calculateYear($bestYear, $year, $level, $position, 'best');
                        $badYear = self::calculateYear($badYear, $year, $level, $position, 'worst');

                        $numTeams = DB::table('season_teams')->where('season_id', '=', $season)->where('level', '=', $level)->count();
                        $comp_level = DB::table('competition_levels')->select('relegation_spots, promotion_spots')->where('competition_id', '=', $compId)->where('level', '=', $level)->first();
                        $ico = '';

                        $isPromoted = (bool) ($position <= ($comp_level['promotion_spots'] ?? null));
                        if ($isPromoted)
                            $ico = '<i class="bi bi-arrow-up text-success"></i>';

                        $isRelegated = (bool) ($position > ($numTeams - ($comp_level['relegation_spots'] ?? null)));
                        if ($isRelegated)
                            $ico = '<i class="bi bi-arrow-down text-danger"></i>';

                        $isChampion = (bool) ($position == 1 && $level == 1);
                        if ($isChampion)
                            $ico = '<i class="bi bi-trophy text-warning"></i>';
                        ?>
                        <tr>
                            <td><?= $year ?></td>
                            <td><?= $level ?></td>
                            <td><?= $position ?> <?= $ico ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-4">
            <?php
            self::renderYearCard($bestYear, 'best', $bestYear['position']);
            self::renderYearCard($badYear, 'worst', $badYear['position']);
            ?>
        </div>
    <?php
    }

    private static function renderTeamHistoryByCompetitionKnockout($compId, $team)
    {
        if (empty($team))
            return;

        $seasons = DB::table('seasons')
            ->select('id')
            ->where('competition_id', '=', $compId)
            ->where('status', '=', '2')
            ->get();
        $seasons = array_column($seasons, 'id');

        $bestYear = null; // phase più bassa = miglior risultato
        $worstYear = null; // phase più alta = peggior risultato
        $rows = [];
    ?>
        <div class="table-responsive my-5">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Stagione</th>
                        <th>Livello</th>
                        <th>Fase raggiunta</th>
                        <th>Risultato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seasons as $sid):
                        $seasonTeam = DB::table('season_teams')
                            ->select('level')
                            ->where('season_id', '=', $sid)
                            ->where('team_id', '=', $team)
                            ->first();
                        if (!$seasonTeam)
                            continue;

                        $level = $seasonTeam['level'];
                        $year = DB::table('seasons')->select('season_year')->where('id', '=', $sid)->first()['season_year'];

                        $minPhaseRow = DB::table('matches')
                            ->select('MIN(phase) as min_phase')
                            ->where('season_id', '=', $sid)
                            ->where('level', '=', $level)
                            ->whereRaw("(team_home_id = :team OR team_away_id = :team)", ['team' => $team])
                            ->whereNotNull('score_home')
                            ->first();

                        $minPhase = (int) ($minPhaseRow['min_phase'] ?? 0);
                        if (!$minPhase)
                            continue;

                        $faseName = Competitions::$round_names[$minPhase - 1] ?? 'Fase ' . $minPhase;

                        $matchesFase = DB::table('matches')
                            ->where('season_id', '=', $sid)
                            ->where('level', '=', $level)
                            ->where('phase', '=', $minPhase)
                            ->whereRaw("(team_home_id = :team OR team_away_id = :team)", ['team' => $team])
                            ->get();

                        $winners = Matches::getWinners($matchesFase);
                        $isWinner = in_array($team, $winners);

                        // Eliminato da chi?
                        $eliminatedBy = null;
                        if (!$isWinner) {
                            // Trova l'avversario aggregato
                            $pairs = Matches::buildPairs($matchesFase);
                            $pair = reset($pairs);
                            if ($pair) {
                                $eliminatedBy = ($pair['teamA'] == $team) ? $pair['teamB'] : $pair['teamA'];
                            }
                        }

                        if ($minPhase === 1) {
                            $ico = $isWinner ? '🏆' : '🥈';
                            $label = $isWinner ? 'Vincitore' : 'Finalista';
                        } else {
                            $ico = $isWinner
                                ? '<i class="bi bi-arrow-right text-success"></i>'
                                : '<i class="bi bi-x text-danger"></i>';
                            $label = $isWinner ? 'Passato' : 'Eliminato';
                        }

                        // Calcola best/worst (parità → più recente, quindi sovrascrive sempre se uguale)
                        if ($bestYear === null || $minPhase < $bestYear['phase'] || $minPhase === $bestYear['phase']) {
                            $bestYear = ['year' => $year, 'level' => $level, 'phase' => $minPhase, 'faseName' => $faseName, 'isWinner' => $isWinner];
                        }
                        if ($worstYear === null || $minPhase > $worstYear['phase'] || $minPhase === $worstYear['phase']) {
                            $worstYear = ['year' => $year, 'level' => $level, 'phase' => $minPhase, 'faseName' => $faseName, 'isWinner' => $isWinner];
                        }
                    ?>
                        <tr>
                            <td><?= $year ?></td>
                            <td><?= $level ?></td>
                            <td><?= $faseName ?></td>
                            <td>
                                <?= $ico ?> <?= $label ?>
                                <?php if ($eliminatedBy): ?>
                                    <span class="text-muted small ms-1">da
                                        <?php Teams::renderTeams($eliminatedBy, 'fw-semibold px-2 rounded-pill d-inline-block small') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Card miglior/peggior anno -->
        <div class="row g-4">
            <?php
            self::renderYearCard($bestYear, 'best', null, $bestYear['faseName']);
            self::renderYearCard($worstYear, 'worst', null, $worstYear['faseName']);
            ?>
        </div>
    <?php
    }

    private static function calculateYear($current, $year, $level, $position, $mode = 'best')
    {
        if ($current['year'] == 0) {
            return compact('year', 'level', 'position');
        }

        $isBetter = false;

        if ($mode === 'best') {
            $isBetter =
                $level < $current['level'] ||
                ($level == $current['level'] && $position <= $current['position']);
        } else { // worst
            $isBetter =
                $level > $current['level'] ||
                ($level == $current['level'] && $position >= $current['position']);
        }

        return $isBetter
            ? compact('year', 'level', 'position')
            : $current;
    }

    private static function renderYearCard($data, $type = 'best', $position = null, $faseName = null)
    {
        $isBest = $type === 'best';
        $title = $isBest ? 'Miglior Anno' : 'Peggior Anno';
        $icon = $isBest ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow';
        $color = $isBest ? 'success' : 'danger';
        $year = $data['year'] ?? '-';
        $level = $data['level'] ?? '-';
    ?>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div
                    class="card-header bg-<?= $color ?> bg-gradient text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi <?= $icon ?>"></i> <?= $title ?></span>
                    <span class="badge bg-light text-<?= $color ?> fw-bold"><?= $year ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Livello</span>
                        <span class="fw-bold fs-5 text-<?= $color ?>"><?= $level ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted"><?= $faseName ? 'Fase' : 'Posizione' ?></span>
                        <?php if ($faseName): ?>
                            <span class="badge bg-<?= $color ?> fs-6 px-3 py-2"><?= $faseName ?></span>
                        <?php else: ?>
                            <span class="badge bg-<?= $color ?> fs-6 px-3 py-2"><?= $position ?>°</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    private static function renderMatchesBySeasonAndTeam($seasonId, $level, $team, $mode)
    {
        if (empty($team))
            return;
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->whereRaw("(team_home_id = :team OR team_away_id = :team)", [
                'team' => $team
            ])->orderBy('phase', 'DESC')->orderBy('round', 'ASC')->get();
    ?>

        <?php if (!empty($matches)): ?>
            <div class="table-responsive my-5">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <?php if ($mode == 2): ?>
                                <th>Fase</th>
                            <?php endif; ?>
                            <th>Giornata</th>
                            <th>Incontro</th>
                            <th>Risultato</th>
                            <th>Dettaglio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matches as $match): ?>
                            <?php
                            $class = 'warning';
                            $isHome = (bool) ($team == $match['team_home_id']);
                            $isAway = (bool) ($team == $match['team_away_id']);
                            $homeWin = (bool) ($match['score_home'] > $match['score_away']);
                            $AwayWin = (bool) ($match['score_home'] < $match['score_away']);
                            if (($isHome && $homeWin) || ($isAway && $AwayWin)) {
                                $class = 'success';
                            } elseif (($isHome && $AwayWin) || ($isAway && $homeWin)) {
                                $class = 'danger';
                            }
                            $nameRound = $match['round'];
                            if ($mode == 2) {
                                $nameRound = ($match['round'] == 1) ? 'Andata' : 'Ritorno';
                            }
                            ?>
                            <tr>
                                <?php if ($mode == 2): ?>
                                    <td><?= Competitions::$round_names[$match['phase'] - 1] ?></td>
                                <?php endif; ?>
                                <td><?= $nameRound ?></td>
                                <td>
                                    <div>
                                        <?php Teams::renderTeams($match['team_home_id'], 'px-2 rounded-pill d-inline-block small') ?>
                                        Vs
                                        <?php Teams::renderTeams($match['team_away_id'], 'px-2 rounded-pill d-inline-block small') ?>
                                    </div>
                                </td>
                                <td class="text-<?= $class ?>">
                                    <?= $match['score_home'] ?> - <?= $match['score_away'] ?>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-1">
                                        <?= Link::a(
                                            'match',
                                            '👁️',
                                            ['id' => $match['id']],
                                            [
                                                'class' => 'btn btn-info btn-sm px-2',
                                                'title' => 'Visualizza Incontro'
                                            ]
                                        ) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php Alert::generateAlert('Nessun Incontro trovato in questa stagione', 'warning', 'Nessun Incontro trovato') ?>
        <?php endif; ?>
<?php
    }
}
