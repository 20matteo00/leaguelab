<?php
class Markers
{
    private static function computeMarkers(array $matchIds): array
    {
        $scorers = [];
        foreach ($matchIds as $matchId) {
            $events = DB::table('match_events')
                ->select('player_id, type, params')
                ->where('match_id', '=', $matchId)
                ->whereIn('type', [1, 2])
                ->get();

            foreach ($events as $event) {
                $playerId = $event['player_id'];
                if (!isset($scorers[$playerId])) {
                    $scorers[$playerId] = ['goal' => 0, 'assist' => 0, 'rigori' => 0];
                }
                switch ($event['type']) {
                    case 1:
                        $scorers[$playerId]['goal']++;
                        $params = json_decode($event['params'] ?? 'null', true);
                        if (!empty($params['penalty'])) {
                            $scorers[$playerId]['rigori']++;
                        }
                        break;
                    case 2:
                        $scorers[$playerId]['assist']++;
                        break;
                }
            }
        }

        uasort($scorers, function ($a, $b) {
            if ($b['goal'] !== $a['goal']) return $b['goal'] <=> $a['goal'];
            return $b['assist'] <=> $a['assist'];
        });

        return $scorers;
    }

    private static function getMarkersStandings($seasonId, $level): array
    {
        $matchIds = array_column(
            DB::table('matches')
                ->select('id')
                ->where('season_id', '=', $seasonId)
                ->where('level', '=', $level)
                ->get(),
            'id'
        );

        return self::computeMarkers($matchIds);
    }

    private static function buildAllTimeMarkers($compId): array
    {
        $seasonIds = array_column(
            DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->get(),
            'id'
        );

        $compLevels = DB::table('competition_levels')
            ->where('competition_id', '=', $compId)
            ->orderBy('level')
            ->get();
        $levelNums = !empty($compLevels) ? array_column($compLevels, 'level') : [1];

        $result = [];
        foreach ($levelNums as $levelNum) {
            $matchIds = array_column(
                DB::table('matches')
                    ->select('id')
                    ->whereIn('season_id', $seasonIds)
                    ->where('level', '=', $levelNum)
                    ->get(),
                'id'
            );

            $scorers = self::computeMarkers($matchIds);
            if (!empty($scorers)) {
                $result[$levelNum] = $scorers;
            }
        }

        return $result;
    }

    private static function renderMarkersTable(array $scorers): void
    {
        $positions = Field::getPosition();
        $posMap    = array_column($positions, 'name', 'code');
?>
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
                        $player   = DB::table('players')->select('team_id, position')->where('id', '=', $playerId)->first();
                        $teamId   = $player['team_id'];
                        $position = $player['position'];
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
                            <td><?= $marker['goal'] . ' (' . $marker['rigori'] . ')' ?></td>
                            <td><?= $marker['assist'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    public static function renderMarkerStandings($seasonId, $level): void
    {
        $scorers = self::getMarkersStandings($seasonId, $level);
        self::renderMarkersTable($scorers);
    }

    public static function renderAllTimeMarkers($compId): void
    {
        $byLevel = self::buildAllTimeMarkers($compId);

        if (empty($byLevel)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">⚽ Marcatori All-Time</h5>

            <?php if (count($byLevel) > 1): ?>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php foreach ($byLevel as $levelNum => $_): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $levelNum === array_key_first($byLevel) ? 'active' : '' ?>"
                                data-bs-toggle="tab"
                                data-bs-target="#markers-alltime-<?= $levelNum ?>"
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
                        id="markers-alltime-<?= $levelNum ?>">
                        <?php self::renderMarkersTable($scorers) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php
    }
}
