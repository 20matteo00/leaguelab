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
                    <a href="<?= $baseUrl ?>&level=<?= $level ?>&action=stats&subaction=<?= $m['subaction'] ?>#content" class="btn btn-info w-100">
                        <i class="bi bi-<?= $m['icon'] ?> "></i> <?= $m['label'] ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
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

    private static function renderTeamStats($seasonId, $level, $subaction)
    {

        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasonId)->where('level', '=', $level)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $teamSelected = $_POST['team'] ?? '';
    ?>
        <form action="index.php?page=season&id=<?= $seasonId ?>&level=<?= $level ?>&action=stats&subaction=<?= $subaction ?>" method="post" class="my-2">
            <div class="row">
                <div class="col form-group">
                    <label for="team">Squadra</label>
                    <select name="team" id="team" class="form-control">
                        <option value="">-- Scegli --</option>
                        <?php foreach ($teams as $key => $team): ?>
                            <option value="<?= $key ?>" <?= ($key == $teamSelected) ? 'selected' : '' ?>>
                                <?= Teams::renderTeams($key) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto form-group d-flex">
                    <button type="submit" class="btn btn-primary mt-auto w-100">Invia</button>
                </div>
            </div>
        </form>
    <?php
        return $teamSelected;
    }

    private static function renderMatchesBySeasonAndTeam($seasonId, $level, $team)
    {
        if(empty($team)) return;
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
                                        <?= Teams::renderTeams($match['team_home_id'], 'px-2 rounded-pill d-inline-block small') ?>
                                        Vs
                                        <?= Teams::renderTeams($match['team_away_id'], 'px-2 rounded-pill d-inline-block small') ?>
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
        <?php elseif (empty($matches)): ?>
            <?php Alert::generateAlert('Nessun Incontro trovato in questa stagione', 'warning', 'Nessun Incontro trovato') ?>
        <?php endif; ?>
<?php
    }
}
