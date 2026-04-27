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
                                <?= Players::renderPlayers($playerId, 'fw-semibold rounded-pill d-inline-block') ?>
                            </td>
                            <td class="text-start"><?= Teams::renderTeams($teamId, 'fw-semibold px-2 rounded-pill d-inline-block') ?></td>
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
}
