<?php
class Calendar
{
    public static function renderCalendar($seasonId, $level)
    {
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->orderBy('round')
            ->get();

        $grouped = [];
        foreach ($matches as $match) {
            $grouped[$match['round']][] = [
                'id'         => $match['id'],
                'home_team'  => $match['team_home_id'],
                'away_team'  => $match['team_away_id'],
                'score_home' => $match['score_home'],
                'score_away' => $match['score_away'],
            ];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $parsed = self::parseCalendarPost($_POST, $grouped);

            if ($_POST['action'] == 'simulate_all') self::simulateAllMatchesBySeason($seasonId, $level);
            elseif ($_POST['action'] == 'delete_all') self::DeleteAllMatchesBySeason($seasonId, $level);
            else {
                match ($parsed['action']) {
                    'save'     => self::saveMatches($parsed['ids'], $parsed['post']),
                    'simulate' => self::simulateMatches($parsed['ids']),
                    'delete'   => self::deleteMatches($parsed['ids']),
                    default    => null,
                };
                Events::generatePlayersStatsForMatches($parsed['ids']);
            }


            $anchor = $parsed['round'] !== null ? '#round-' . $parsed['round'] : '';
            header("Location: index.php?page=season&id=" . $seasonId . "&level=" . $level . "&action=calendar" . $anchor);
            exit;
        }
        $allIds    = array_column(array_merge(...array_values($grouped)), 'id');
        $allIdsStr = implode(',', $allIds);

        $isEnded = Seasons::checkSeasonEnd($seasonId);
?>



        <!-- FORM UNICO CHE WRAPPA TUTTO -->
        <form method="POST">
            <?php if (!$isEnded): ?>
                <div class="position-fixed bottom-0 start-0 w-100 p-3 bg-white z-1">
                    <div class="container d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold w-100" name="action" value="simulate_all">
                            ⚡ Simula tutto
                        </button>
                        <button type="submit" class="btn btn-danger fw-bold w-100" name="action" value="delete_all">
                            ✕ Elimina tutto
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <input type="hidden" name="match_ids" value="<?= $allIdsStr ?>">
            <!-- AZIONI LIVELLO -->
            <?php if (!$isEnded): ?>
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                    <button type="submit" name="action" value="save_level" class="btn btn-success fw-bold p-3 w-100">💾 Salva Livello</button>
                    <button type="submit" name="action" value="simulate_level" class="btn btn-warning fw-bold p-3 w-100">⚡ Simula Livello</button>
                    <button type="submit" name="action" value="delete_level" class="btn btn-danger fw-bold p-3 w-100">✕ Elimina Livello</button>
                </div>
            <?php endif; ?>
            <div class="row g-4">
                <?php foreach ($grouped as $round => $roundMatches): ?>
                    <?php $roundIdsStr = implode(',', array_column($roundMatches, 'id')); ?>
                    <div class="col-12 col-lg-6">
                        <div class="card shadow-sm border-0 h-100" id="round-<?= $round ?>">
                            <div class="card-header bg-primary text-white text-center fw-bold">
                                Giornata <?= $round ?>
                            </div>
                            <div class="card-body">
                                <?php foreach ($roundMatches as $match): ?>
                                    <div class="d-flex align-items-center py-2 border-bottom">
                                        <!-- Squadre -->
                                        <div class="flex-grow-1">
                                            <div class="row align-items-center text-center">
                                                <!-- Home -->
                                                <div class="col-5 text-end">
                                                    <?php Teams::renderTeams($match['home_team'], 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                                </div>
                                                <!-- VS -->
                                                <div class="col-2 text-muted small">
                                                    vs
                                                </div>
                                                <!-- Away -->
                                                <div class="col-5 text-start">
                                                    <?php Teams::renderTeams($match['away_team'], 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Score inputs -->
                                        <div class="d-flex align-items-center gap-1 mx-3">
                                            <input type="number" name="score_home_<?= $match['id'] ?>"
                                                value="<?= $match['score_home'] ?? '' ?>"
                                                min="0"
                                                class="form-control form-control-sm text-center p-0"
                                                style="width:40px">
                                            <span class="text-muted small">:</span>
                                            <input type="number" name="score_away_<?= $match['id'] ?>"
                                                value="<?= $match['score_away'] ?? '' ?>"
                                                min="0"
                                                class="form-control form-control-sm text-center p-0"
                                                style="width:40px">
                                        </div>
                                        <!-- Bottoni -->
                                        <div class="d-flex gap-1">
                                            <a href="index.php?page=match&id=<?= $match['id'] ?>"
                                                class="btn btn-info btn-sm px-2" title="Visualizza Incontro">👁️</a>
                                            <?php if (!$isEnded): ?>
                                                <button type="submit" name="action" value="save_one_<?= $match['id'] ?>"
                                                    class="btn btn-success btn-sm px-2" title="Salva Incontro">✓</button>
                                                <button type="submit" name="action" value="simulate_one_<?= $match['id'] ?>"
                                                    class="btn btn-warning btn-sm px-2" title="Simula Incontro">⚡</button>
                                                <button type="submit" name="action" value="delete_one_<?= $match['id'] ?>"
                                                    class="btn btn-danger btn-sm px-2" title="Resetta Incontro">✕</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- FOOTER GIORNATA -->
                            <div class="card-footer d-flex gap-2 justify-content-end">
                                <?php if (!$isEnded): ?>
                                    <button type="submit" name="action" value="save_round_<?= $round ?>"
                                        class="btn btn-success btn-sm" title="Salva Giornata">💾 Salva</button>
                                    <button type="submit" name="action" value="simulate_round_<?= $round ?>"
                                        class="btn btn-warning btn-sm" title="Simula Giornata">⚡ Simula</button>
                                    <button type="submit" name="action" value="delete_round_<?= $round ?>"
                                        class="btn btn-danger btn-sm" title="Resetta Giornata">✕ Resetta</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>

    <?php
    }
    private static function parseCalendarPost(array $post, array $grouped): array
    {
        $action   = $post['action'] ?? '';
        $allIds   = array_filter(explode(',', $post['match_ids'] ?? ''));

        // Singola partita → save_one_123 / simulate_one_123 / delete_one_123
        if (preg_match('/^(save|simulate|delete)_one_(\d+)$/', $action, $m)) {
            // Trova il round della partita cercando nei grouped
            $round = null;
            foreach ($grouped as $r => $roundMatches) {
                foreach ($roundMatches as $match) {
                    if ((int)$match['id'] === (int)$m[2]) {
                        $round = $r;
                        break 2;
                    }
                }
            }
            return [
                'action' => $m[1],
                'ids'    => [(int)$m[2]],
                'post'   => $post,
                'round'  => $round,
            ];
        }

        // Giornata → save_round_3 / simulate_round_3 / delete_round_3
        if (preg_match('/^(save|simulate|delete)_round_(\d+)$/', $action, $m)) {
            return [
                'action' => $m[1],
                'ids'    => array_column($grouped[(int)$m[2]], 'id'),
                'post'   => $post,
                'round'  => (int)$m[2],
            ];
        }

        // Livello intero → nessun round specifico
        if (preg_match('/^(save|simulate|delete)_level$/', $action, $m)) {
            return [
                'action' => $m[1],
                'ids'    => array_map('intval', $allIds),
                'post'   => $post,
                'round'  => null,
            ];
        }

        return ['action' => null, 'ids' => [], 'post' => $post, 'round' => null];
    }

    private static function saveMatches(array $ids, array $post): void
    {
        foreach ($ids as $id) {
            $scoreHome = $post["score_home_{$id}"] ?? null;
            $scoreAway = $post["score_away_{$id}"] ?? null;

            DB::table('matches')
                ->where('id', '=', $id)
                ->update([
                    'score_home' => $scoreHome !== '' ? (int)$scoreHome : null,
                    'score_away' => $scoreAway !== '' ? (int)$scoreAway : null,
                    'status' => 1,
                ]);
        }
    }

    private static function simulateMatches(array $ids): void
    {
        foreach ($ids as $id) {
            $match = DB::table('matches')
                ->where('id', '=', $id)
                ->whereNull('score_home')
                ->whereNull('score_away')
                ->first();

            if (!$match) continue;

            $result = self::simulateMatch($id);

            DB::table('matches')
                ->where('id', '=', $id)
                ->update($result);
        }
    }

    private static function deleteMatches(array $ids): void
    {
        DB::table('matches')
            ->whereIn('id', $ids)
            ->update([
                'score_home' => null,
                'score_away' => null,
                'status' => 0,
            ]);
    }

    private static function pesoRand(int $min, int $max): int
    {
        $pesi = [
            0 => 50,
            1 => 60,
            2 => 40,
            3 => 25,
            4 => 12,
            5 => 8,
            6 => 4,
            7 => 1
        ];

        $pesiFiltrati = array_filter(
            $pesi,
            fn($k) => $k >= $min && $k <= $max,
            ARRAY_FILTER_USE_KEY
        );

        $sommaPesi = array_sum($pesiFiltrati);
        $random    = rand(0, $sommaPesi - 1);
        $soglia    = 0;

        foreach ($pesiFiltrati as $numero => $peso) {
            $soglia += $peso;
            if ($random < $soglia) return $numero;
        }

        return $min;
    }

    public static function getTeamStrength(int $teamId): array
    {
        $teamStats    = Teams::getTeamStats($teamId);
        $playersStats = Players::getPlayersStatsByTeam($teamId);

        $attack     = $teamStats['attack']   * 0.75 + $playersStats['attack']   * 0.25;
        $defense    = $teamStats['defense']  * 0.75 + $playersStats['defense']  * 0.25;
        $homeFactor = $teamStats['home_factor'];

        $homeBoost = 1.0 + ($homeFactor / 999) * 0.15;

        return [
            'attack'      => $attack,
            'defense'     => $defense,
            'home_factor' => $homeFactor,
            'home_boost'  => $homeBoost,
        ];
    }

    public static function getForzaEffettiva(array $strengthHome, array $strengthAway): array
    {
        // Forza offensiva: attacco di chi attacca vs difesa di chi difende
        $offHome = $strengthHome['attack']  * $strengthHome['home_boost'];
        $offAway = $strengthAway['attack'];

        // Forza difensiva: quanto freni l'avversario (alta difesa = freni di più)
        $defHome = $strengthHome['defense'];
        $defAway = $strengthAway['defense'];

        // Score finale: attacco proprio * 0.6 + difesa propria * 0.4
        // (la difesa contribuisce positivamente alla propria forza, non negativamente all'avversario)
        $forzaHome = min(999, $offHome * 0.6 + $defHome * 0.4);
        $forzaAway = min(999, $offAway * 0.6 + $defAway * 0.4);

        return [
            'forza_home' => $forzaHome,
            'forza_away' => $forzaAway,
        ];
    }

    private static function simulateMatch($matchId): array
    {
        $matchTeams = DB::table('matches')
            ->select('team_home_id, team_away_id')
            ->where('id', '=', $matchId)
            ->first();

        $strengthHome = self::getTeamStrength($matchTeams['team_home_id']);
        $strengthAway = self::getTeamStrength($matchTeams['team_away_id']);
        $forze        = self::getForzaEffettiva($strengthHome, $strengthAway);

        $ratio = ($forze['forza_home'] - $forze['forza_away']) / 999;
        $noise = mt_rand(-300, 300) / 999;
        $score = $ratio + $noise;

        $gol1 = 0;
        $gol2 = 0;

        if ($score >= 0.7) {
            $gol1 = self::pesoRand(3, 7);
            $gol2 = self::pesoRand(0, max(0, $gol1 - 3));
        } elseif ($score >= 0.45) {
            $gol1 = self::pesoRand(2, 6);
            $gol2 = self::pesoRand(0, max(0, $gol1 - 2));
        } elseif ($score >= 0.25) {
            $gol1 = self::pesoRand(1, 5);
            $gol2 = self::pesoRand(0, max(0, $gol1 - 1));
        } elseif ($score >= 0.10) {
            $gol1 = self::pesoRand(0, 4);
            $gol2 = self::pesoRand(0, $gol1);
        } elseif ($score >= 0.03) {
            $gol1 = self::pesoRand(0, 3);
            $gol2 = self::pesoRand(0, $gol1 + 1);
        } elseif ($score >= -0.03) {
            $gol1 = self::pesoRand(0, 3);
            $gol2 = self::pesoRand(0, 3);
        } elseif ($score >= -0.10) {
            $gol2 = self::pesoRand(0, 3);
            $gol1 = self::pesoRand(0, $gol2 + 1);
        } elseif ($score >= -0.25) {
            $gol2 = self::pesoRand(0, 4);
            $gol1 = self::pesoRand(0, $gol2);
        } elseif ($score >= -0.45) {
            $gol2 = self::pesoRand(1, 5);
            $gol1 = self::pesoRand(0, max(0, $gol2 - 1));
        } elseif ($score >= -0.70) {
            $gol2 = self::pesoRand(2, 6);
            $gol1 = self::pesoRand(0, max(0, $gol2 - 2));
        } else {
            $gol2 = self::pesoRand(3, 7);
            $gol1 = self::pesoRand(0, max(0, $gol2 - 3));
        }

        return [
            'score_home' => $gol1,
            'score_away' => $gol2,
            'status'     => 1,
        ];
    }

    public static function renderBracket($seasonId, $level)
    {
        $teams = Seasons::getTeamsLevelsBySeason($seasonId)[$level];
        $teams = Teams::orderTeamsByName($teams);
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->get();

        // Indicizza per [home_id][away_id] => match
        $matchMap = [];
        foreach ($matches as $match) {
            $match = (array)$match;
            $matchMap[$match['team_home_id']][$match['team_away_id']] = $match;
        }
    ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">C ↓ / T →</th>
                        <?php foreach ($teams as $awayId => $awayName): ?>
                            <th>
                                <?php Teams::renderTeams(
                                    $awayId,
                                    'fw-semibold px-2 rounded-pill d-inline-block',
                                    false,
                                    false,
                                    ['abbr_name' => 3]
                                ) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $homeId => $homeName): ?>
                        <tr>
                            <td class="text-start">
                                <?php Teams::renderTeams($homeId, 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                            </td>
                            <?php foreach ($teams as $awayId => $awayName): ?>
                                <?php if ($homeId === $awayId): ?>
                                    <td style="background:#222;"></td>
                                <?php else: ?>
                                    <td>
                                        <?php
                                        if (isset($matchMap[$homeId][$awayId])) {
                                            $m = $matchMap[$homeId][$awayId];
                                            $sh = $m['score_home'];
                                            $sa = $m['score_away'];

                                            if ($sh === null || $sa === null) {
                                                echo '<span class="text-muted">-</span>';
                                            } else {
                                                $sh = (int)$sh;
                                                $sa = (int)$sa;

                                                if ($sh > $sa) {
                                                    $badge = 'bg-success text-white';
                                                } elseif ($sh < $sa) {
                                                    $badge = 'bg-danger text-white';
                                                } else {
                                                    $badge = 'bg-warning text-dark';
                                                }

                                                echo '<span class="badge ' . $badge . '">' . $sh . ' - ' . $sa . '</span>';
                                            }
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legenda -->
        <div class="d-flex gap-3 flex-wrap mt-2 mb-4 small">
            <span class="badge bg-success text-white px-3 py-2">Casa vince</span>
            <span class="badge bg-danger text-white px-3 py-2">Trasferta vince</span>
            <span class="badge bg-warning text-dark px-3 py-2">Pareggio</span>
            <span class="text-muted align-self-center">— &nbsp; Non giocata</span>
        </div>

<?php
    }

    private static function simulateAllMatchesBySeason($seasonId, $level)
    {
        $matches = DB::table('matches')->select('id')->where('season_id', '=', $seasonId)->get();
        $matches = array_column($matches, 'id');
        self::simulateMatches($matches);
        Events::generatePlayersStatsForMatches($matches);
    }

    private static function deleteAllMatchesBySeason($seasonId, $level)
    {
        $matches = DB::table('matches')->select('id')->where('season_id', '=', $seasonId)->get();
        $matches = array_column($matches, 'id');
        self::deleteMatches($matches);
        Events::generatePlayersStatsForMatches($matches);
    }
}
