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

    private static function buildAllTimeMarkers($compId, $level): array
    {
        $seasonIds = array_column(
            DB::table('seasons')
                ->select('id')
                ->where('competition_id', '=', $compId)
                ->get(),
            'id'
        );

        if (empty($seasonIds)) return [];

        // match SOLO di quel livello
        $matchIds = array_column(
            DB::table('matches')
                ->select('id')
                ->whereIn('season_id', $seasonIds)
                ->where('level', '=', $level)
                ->get(),
            'id'
        );

        if (empty($matchIds)) return [];

        return self::computeMarkers($matchIds);
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

    public static function renderMarkerStandings($seasonId, $level, $minGoal = null): void
    {
        $scorers = self::getMarkersStandings($seasonId, $level);

        if ($minGoal) {
            $scorers = array_filter($scorers, function ($item) use ($minGoal) {
                return $item['goal'] >= $minGoal;
            });
        }

        self::renderMarkersTable($scorers);
    }

    public static function renderAllTimeMarkers($compId, $level, $minGoal = null): void
    {
        $scorers = self::buildAllTimeMarkers($compId, $level);

        if (empty($scorers)) {
            echo '<p class="text-muted">Nessun dato disponibile.</p>';
            return;
        }

        // filtro goal
        if ($minGoal !== null) {
            $scorers = array_filter($scorers, function ($item) use ($minGoal) {
                return $item['goal'] >= $minGoal;
            });
        }
    ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-3">
                ⚽ Marcatori All-Time - Livello <?= $level ?> - Con almeno <?= $minGoal ?> Goal
            </h5>

            <?php self::renderMarkersTable($scorers); ?>
        </div>
<?php
    }
}
