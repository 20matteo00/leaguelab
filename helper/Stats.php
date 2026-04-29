<?php
class Stats
{

    public static $globalmenu = [
        [
            'subaction' => 'overview',
            'icon'   => 'calendar3',
            'label'  => 'Panoramica'
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

    public static function renderGlobalMenu($baseUrl)
    {
        $menu = self::$globalmenu;
?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&action=stats&subaction=<?= $m['subaction'] ?>#content" class="btn btn-info w-100">
                        <i class="bi bi-<?= $m['icon'] ?> "></i> <?= $m['label'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderMenu($baseUrl, $level, $mode)
    {
        $menu = self::$menu[$mode];
    ?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&level=<?= $level ?>&action=stats&subaction=<?= $m['subaction'] ?>#content" class="btn btn-info w-100">
                        <i class="bi bi-<?= $m['icon'] ?> "></i> <?= $m['label'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    public static function renderGlobalStats($compId, $subaction)
    {
        switch ($subaction) {
            case 'overview':
                break;
            case 'team_history':
                $team = self::renderGlobalTeamStats($compId, $subaction);
                self::renderTeamHistoryByCompetition($compId, $team);
                break;
            default:
                break;
        }
    }

    public static function renderStats($seasonId, $level, $subaction)
    {
        switch ($subaction) {
            case 'overview':
                break;
            case 'team_matches':
                $team = self::renderTeamStats($seasonId, $level, $subaction);
                self::renderMatchesBySeasonAndTeam($seasonId, $level, $team);
                break;
            default:
                break;
        }
    }

    private static function renderGlobalTeamStats($compId, $subaction)
    {
        $seasons =  DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->first()['id'];

        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasons)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $teamSelected = $_POST['team'] ?? '';
    ?>
        <form action="index.php?page=competition&id=<?= $compId ?>&action=stats&subaction=<?= $subaction ?>" method="post" class="my-2">
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
        <form action="index.php?page=season&id=<?= $seasonId ?>&level=<?= $level ?>&action=stats&subaction=<?= $subaction ?>" method="post" class="my-2">
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
        if (empty($team)) return;
        $stats = ['ciao'];
        $seasons =  DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->get();
        $seasons = array_column($seasons, 'id');
    ?>
        <?php if (!empty($stats)): ?>
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

                            $numTeams = DB::table('season_teams')->where('season_id', '=', $season)->where('level', '=', $level)->count();
                            $comp_level = DB::table('competition_levels')->select('relegation_spots, promotion_spots')->where('competition_id', '=', $compId)->where('level', '=', $level)->first();
                            $ico = '';

                            $isPromoted = (bool) ($position <= ($comp_level['promotion_spots']));
                            if ($isPromoted) $ico = '<i class="bi bi-arrow-up text-success"></i>';

                            $isRelegated = (bool) ($position > ($numTeams - $comp_level['relegation_spots']));
                            if ($isRelegated) $ico = '<i class="bi bi-arrow-down text-danger"></i>';

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
        <?php else: ?>
            <?php Alert::generateAlert('Nessuna statistica trovata per questa squadra in questa competizione', 'warning', 'Nessuna statistica trovata') ?>
        <?php endif; ?>
    <?php
    }

    private static function renderMatchesBySeasonAndTeam($seasonId, $level, $team)
    {
        if (empty($team)) return;
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->whereRaw("(team_home_id = :team OR team_away_id = :team)", [
                'team' => $team
            ])->orderBy('round', 'ASC')->get();
    ?>

        <?php if (!empty($matches)): ?>
            <div class="table-responsive my-5">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Giornata</th>
                            <th>Incontro</th>
                            <th>Risultato</th>
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
                            ?>
                            <tr>
                                <td><?= $match['round'] ?></td>
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
