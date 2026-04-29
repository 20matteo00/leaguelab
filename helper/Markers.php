<?php
class Markers
{
    private static function getMarkersStandings($seasonId, $level)
    {
        $matches = DB::table('matches')
            ->select('id')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        $scorers = [];

        foreach ($matches as $match) {
            $events = DB::table('match_events')
                ->select('player_id, type')
                ->where('match_id', '=', $match['id'])
                ->whereIn('type', [1, 2, 3])
                ->get();

            foreach ($events as $event) {
                $playerId = $event['player_id'];

                // inizializza se non esiste
                if (!isset($scorers[$playerId])) {
                    $scorers[$playerId] = [
                        'goal' => 0,
                        'assist' => 0,
                        'rigori' => 0,
                    ];
                }

                // incrementa in base al tipo
                switch ($event['type']) {
                    case 1:
                        $scorers[$playerId]['goal']++;
                        break;
                    case 2:
                        $scorers[$playerId]['assist']++;
                        break;
                    case 3:
                        $scorers[$playerId]['rigori']++;
                        break;
                }
            }
        }

        // opzionale: ordina per goal (discendente)
        uasort($scorers, function ($a, $b) {
            return $b['goal'] <=> $a['goal'];
        });

        return $scorers;
    }

    public static function renderMarkerStandings($seasonId, $level)
    {
        $markers = self::getMarkersStandings($seasonId, $level);
?>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th title="Giocatore" class="text-start">Giocatore</th>
                        <th title="Squadre" class="text-start">Squadra</th>
                        <th title="Posizione">Posizione</th>
                        <th title="Goal">Goal (Rigori)</th>
                        <th title="Assit">Assit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pos = 1;
                    $positions = Field::getPosition();

                    foreach ($markers as $playerId => $marker): ?>
                        <?php
                        $player = DB::table('players')->select('team_id, position')->where('id', '=', $playerId)->first();
                        $teamId = $player['team_id'];
                        $position = $player['position'];
                        $goal = $marker['goal'] + $marker['rigori'];
                        ?>
                        <tr>
                            <td class="text-muted"><?= $pos++ ?></td>
                            <td class="text-start">
                                <?php Players::renderPlayers($playerId, 'fw-semibold rounded-pill d-inline-block') ?>
                            </td>
                            <td class="text-start"><?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?></td>
                            <td><?= array_column($positions, 'name', 'code')[$position] ?? '' ?></td>
                            <td><?= $goal . " (" . $marker['rigori'] . ")" ?></td>
                            <td><?= $marker['assist'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private static function buildAllTimeMarkers($compId): array
    {
        $seasons = DB::table('seasons')
            ->select('id')
            ->where('competition_id', '=', $compId)
            ->get();
        $seasonIds = array_column($seasons, 'id');

        $compLevels = DB::table('competition_levels')
            ->where('competition_id', '=', $compId)
            ->orderBy('level')
            ->get();
        $levelNums = !empty($compLevels) ? array_column($compLevels, 'level') : [1];

        $result = [];

        foreach ($levelNums as $levelNum) {
            $matches = DB::table('matches')
                ->select('id')
                ->whereIn('season_id', $seasonIds)
                ->where('level', '=', $levelNum)
                ->get();

            $scorers = [];
            foreach ($matches as $match) {
                $events = DB::table('match_events')
                    ->select('player_id, type')
                    ->where('match_id', '=', $match['id'])
                    ->whereIn('type', [1, 2, 3])
                    ->get();

                foreach ($events as $event) {
                    $playerId = $event['player_id'];
                    if (!isset($scorers[$playerId])) {
                        $scorers[$playerId] = ['goal' => 0, 'assist' => 0, 'rigori' => 0];
                    }
                    switch ($event['type']) {
                        case 1:
                            $scorers[$playerId]['goal']++;
                            break;
                        case 2:
                            $scorers[$playerId]['assist']++;
                            break;
                        case 3:
                            $scorers[$playerId]['rigori']++;
                            break;
                    }
                }
            }

            uasort($scorers, function ($a, $b) {
                $totA = $a['goal'] + $a['rigori'];
                $totB = $b['goal'] + $b['rigori'];
                if ($totB !== $totA) return $totB <=> $totA;
                return $b['assist'] <=> $a['assist'];
            });

            if (!empty($scorers)) {
                $result[$levelNum] = $scorers;
            }
        }

        return $result;
    }

    public static function renderAllTimeMarkers($compId): void
    {
        $byLevel = self::buildAllTimeMarkers($compId);

        if (empty($byLevel)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        $positions = Field::getPosition();
        $posMap = array_column($positions, 'name', 'code');
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">⚽ Marcatori All-Time</h5>

            <?php if (count($byLevel) > 1): ?>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php foreach ($byLevel as $levelNum => $_): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $levelNum === array_key_first($byLevel) ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#markers-level-<?= $levelNum ?>"
                                type="button">
                                Livello <?= $levelNum ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tab-content">
                <?php foreach ($byLevel as $levelNum => $scorers): ?>
                    <div class="tab-pane fade <?= $levelNum === array_key_first($byLevel) ? 'show active' : '' ?>"
                        id="markers-level-<?= $levelNum ?>">

                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle shadow-sm text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-start">Giocatore</th>
                                        <th class="text-start">Squadra</th>
                                        <th>Posizione</th>
                                        <th title="Goal">Goal (Rigori)</th>
                                        <th title="Assist">Assist</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pos = 1;
                                    foreach ($scorers as $playerId => $marker):
                                        $player = DB::table('players')
                                            ->select('team_id, position')
                                            ->where('id', '=', $playerId)
                                            ->first();
                                        $teamId   = $player['team_id'];
                                        $position = $player['position'];
                                        $goal     = $marker['goal'] + $marker['rigori'];
                                    ?>
                                        <tr>
                                            <td class="text-muted"><?= $pos++ ?></td>
                                            <td class="text-start">
                                                <?php Players::renderPlayers($playerId, 'fw-semibold rounded-pill d-inline-block') ?>
                                            </td>
                                            <td class="text-start">
                                                <?php Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                            </td>
                                            <td><?= $posMap[$position] ?? '' ?></td>
                                            <td><?= $goal . ' (' . $marker['rigori'] . ')' ?></td>
                                            <td><?= $marker['assist'] ?></td>
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
}
