<?php
class Calendar
{
    public static function groupMatches($matches, $mode)
    {
        $grouped = [];

        foreach ($matches as $match) {

            $round = $match['round'];
            $phase = $match['phase'];
            $group = $match['group_id'];

            switch ($mode) {

                // 🟢 Campionato (giornate)
                case 1:
                    $grouped[$round][] = $match;
                    break;

                // 🔴 Eliminazione diretta (fase + giornata)
                case 2:
                    $grouped[$phase][$round][] = $match;
                    break;

                // 🔵 Gironi (girone + giornata)
                case 3:
                    $grouped[$group][$round][] = $match;
                    break;
            }
        }

        return $grouped;
    }
    public static function renderCalendar($seasonId, $level, $mode)
    {
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->orderBy('phase', 'ASC')
            ->orderBy('round')
            ->get();

        $grouped = self::groupMatches($matches, $mode);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $parsed = self::parseCalendarPost($_POST, $grouped, $mode);

            if ($_POST['action'] == 'simulate_all')
                self::simulateAllMatchesBySeason($seasonId, $level);
            elseif ($_POST['action'] == 'delete_all')
                self::DeleteAllMatchesBySeason($seasonId, $level);
            else {
                match ($parsed['action']) {
                    'save' => self::saveMatches($parsed['ids'], $parsed['post']),
                    'simulate' => self::simulateMatches($parsed['ids']),
                    'delete' => self::deleteMatches($parsed['ids']),
                    default => null,
                };
                Events::generatePlayersStatsForMatches($parsed['ids']);
            }
            if ($parsed['round'] !== null) {
                $anchor = $parsed['phase'] !== null
                    ? '#phase-' . $parsed['phase'] . '-round-' . $parsed['round']
                    : '#round-' . $parsed['round'];
            } else {
                $anchor = '';
            }
            header("Location: index.php?page=season&id=" . $seasonId . "&level=" . $level . "&action=calendar" . $anchor);
            exit;
        }
        // PRIMA (rotto per mode 2/3):
        $allIds = array_column(array_merge(...array_values($grouped)), 'id');

        // DOPO:
        $flatByRound = self::flattenGrouped($grouped, $mode);
        $allMatches = array_merge(...array_values($flatByRound));
        $allIds = array_column($allMatches, 'id');
        $allIdsStr = implode(',', $allIds);

        $isEnded = Seasons::checkSeasonEnd($seasonId);
        ?>
        <!-- FORM UNICO CHE WRAPPA TUTTO -->
        <form method="POST">
            <?php if (!$isEnded && $mode != 2): ?>
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
            <?php if (!$isEnded && $mode != 2): ?>
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                    <button type="submit" name="action" value="save_level" class="btn btn-success fw-bold p-3 w-100">💾 Salva
                        Livello</button>
                    <button type="submit" name="action" value="simulate_level" class="btn btn-warning fw-bold p-3 w-100">⚡ Simula
                        Livello</button>
                    <button type="submit" name="action" value="delete_level" class="btn btn-danger fw-bold p-3 w-100">✕ Elimina
                        Livello</button>
                </div>
            <?php endif; ?>
            <div class="row g-4 mb-5 pb-5">
                <?php if ($mode == 1): ?>

                    <?php foreach ($grouped as $round => $roundMatches): ?>
                        <?php self::renderDays($roundMatches, $round, $isEnded); ?>
                    <?php endforeach; ?>

                <?php elseif ($mode == 2): ?>

                    <?php foreach ($grouped as $phase => $rounds): ?>
                        <h3 class="text-center"><?= Competitions::$round_names[$phase - 1] ?></h3>
                        <?php
                        $isMinPhase = false;
                        $minPhase = DB::table('matches')
                            ->select('MIN(phase) as min_phase')
                            ->where('season_id', '=', $seasonId)
                            ->first()['min_phase'];
                        if ($minPhase == $phase)
                            $isMinPhase = true;
                        ?>
                        <?php foreach ($rounds as $round => $roundMatches): ?>
                            <?php self::renderDays($roundMatches, $round, $isEnded, $phase, $isMinPhase); ?>
                        <?php endforeach; ?>

                    <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </form>

        <?php
    }

    private static function renderDays($roundMatches, $round, $isEnded, $phase = null, $isMinPhase = true)
    {
        $anchor = ($phase) ? 'phase-' . $phase . '-round-' . $round : 'round-' . $round;
        // Costruisci il prefisso del round contestuale alla fase
        $roundKey = ($phase !== null) ? $phase . '_' . $round : $round;

        $nameDay = 'Giornata ' . $round ;
        if ($phase){
            $nameDay = ($round == 1) ? 'Andata' : 'Ritorno';
        }

        ?>
        <?php $roundIdsStr = implode(',', array_column($roundMatches, 'id')); ?>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100" id="<?= $anchor ?>">
                <div class="card-header bg-primary text-white text-center fw-bold">
                    <?= $nameDay ?>
                </div>
                <div class="card-body">
                    <?php foreach ($roundMatches as $match): ?>
                        <div class="d-flex align-items-center py-2 border-bottom">
                            <!-- Squadre -->
                            <div class="flex-grow-1">
                                <div class="row align-items-center text-center">
                                    <!-- Home -->
                                    <div class="col-5 text-end">
                                        <?php Teams::renderTeams($match['team_home_id'], 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                    </div>
                                    <!-- VS -->
                                    <div class="col-2 text-muted small">
                                        vs
                                    </div>
                                    <!-- Away -->
                                    <div class="col-5 text-start">
                                        <?php Teams::renderTeams($match['team_away_id'], 'fw-semibold px-2 rounded-pill d-inline-block') ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Score inputs -->
                            <div class="d-flex align-items-center gap-1 mx-3">
                                <input type="number" name="score_home_<?= $match['id'] ?>" value="<?= $match['score_home'] ?? '' ?>"
                                    min="0" class="form-control form-control-sm text-center p-0" style="width:40px">
                                <span class="text-muted small">:</span>
                                <input type="number" name="score_away_<?= $match['id'] ?>" value="<?= $match['score_away'] ?? '' ?>"
                                    min="0" class="form-control form-control-sm text-center p-0" style="width:40px">
                            </div>
                            <!-- Bottoni -->
                            <div class="d-flex gap-1">
                                <a href="index.php?page=match&id=<?= $match['id'] ?>" class="btn btn-info btn-sm px-2"
                                    title="Visualizza Incontro">👁️</a>
                                <?php if (!$isEnded && $isMinPhase): ?>
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
                    <?php if (!$isEnded && $isMinPhase): ?>
                        <button type="submit" name="action" value="save_round_<?= $roundKey ?>" class="btn btn-success btn-sm"
                            title="Salva Giornata">💾 Salva</button>
                        <button type="submit" name="action" value="simulate_round_<?= $roundKey ?>" class="btn btn-warning btn-sm"
                            title="Simula Giornata">⚡ Simula</button>
                        <button type="submit" name="action" value="delete_round_<?= $roundKey ?>" class="btn btn-danger btn-sm"
                            title="Resetta Giornata">✕ Resetta</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
    }
    private static function parseCalendarPost(array $post, array $grouped, int $mode): array
    {
        $action = $post['action'] ?? '';
        $allIds = array_filter(explode(',', $post['match_ids'] ?? ''));

        // Normalizza grouped → sempre [round => [matches]]
        // indipendentemente dal mode
        $flatByRound = self::flattenGrouped($grouped, $mode);

        // Per save_one_X: cerca anche la phase
        if (preg_match('/^(save|simulate|delete)_one_(\d+)$/', $action, $m)) {
            $round = null;
            $phase = null;
            foreach ($flatByRound as $key => $roundMatches) {
                foreach ($roundMatches as $match) {
                    if ((int) $match['id'] === (int) $m[2]) {
                        if (str_contains((string) $key, '_')) {
                            [$phase, $round] = explode('_', $key);
                            $phase = (int) $phase;
                            $round = (int) $round;
                        } else {
                            $round = (int) $key;
                        }
                        break 2;
                    }
                }
            }
            return ['action' => $m[1], 'ids' => [(int) $m[2]], 'post' => $post, 'round' => $round, 'phase' => $phase];
        }

        // Per save_round_X o save_round_2_1
        if (preg_match('/^(save|simulate|delete)_round_([\d_]+)$/', $action, $m)) {
            $key = $m[2];
            $phase = null;
            if (str_contains($key, '_')) {
                [$phaseStr, $roundStr] = explode('_', $key);
                $phase = (int) $phaseStr;
                $roundNum = (int) $roundStr;
            } else {
                $roundNum = (int) $key;
            }
            $roundMatches = $flatByRound[$key] ?? [];
            return ['action' => $m[1], 'ids' => array_column($roundMatches, 'id'), 'post' => $post, 'round' => $roundNum, 'phase' => $phase];
        }

        // Per save_level
        if (preg_match('/^(save|simulate|delete)_level$/', $action, $m)) {
            return ['action' => $m[1], 'ids' => array_map('intval', $allIds), 'post' => $post, 'round' => null, 'phase' => null];
        }

        return ['action' => null, 'ids' => [], 'post' => $post, 'round' => null, 'phase' => null];
    }

    // Appiattisce qualsiasi struttura grouped in [round => [matches]]
    private static function flattenGrouped(array $grouped, int $mode): array
    {
        if ($mode === 1) {
            return $grouped; // già [round => [matches]]
        }

        // mode 2 e 3: primo livello è phase/group, secondo è round
        $flat = [];
        foreach ($grouped as $outer => $rounds) {
            foreach ($rounds as $round => $matches) {
                // Se lo stesso round appare in più fasi (raro ma possibile),
                // usiamo una chiave composta per non sovrascrivere
                $key = ($mode === 2) ? $outer . '_' . $round : $round;
                $flat[$key] = array_merge($flat[$key] ?? [], $matches);
            }
        }
        return $flat;
    }

    private static function saveMatches(array $ids, array $post): void
    {
        foreach ($ids as $id) {
            $scoreHome = $post["score_home_{$id}"] ?? null;
            $scoreAway = $post["score_away_{$id}"] ?? null;

            DB::table('matches')
                ->where('id', '=', $id)
                ->update([
                    'score_home' => $scoreHome !== '' ? (int) $scoreHome : null,
                    'score_away' => $scoreAway !== '' ? (int) $scoreAway : null,
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

            if (!$match)
                continue;

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
        $random = rand(0, $sommaPesi - 1);
        $soglia = 0;

        foreach ($pesiFiltrati as $numero => $peso) {
            $soglia += $peso;
            if ($random < $soglia)
                return $numero;
        }

        return $min;
    }

    public static function getTeamStrength(int $teamId): array
    {
        $teamStats = Teams::getTeamStats($teamId);
        $playersStats = Players::getPlayersStatsByTeam($teamId);

        $attack = $teamStats['attack'] * 0.75 + $playersStats['attack'] * 0.25;
        $defense = $teamStats['defense'] * 0.75 + $playersStats['defense'] * 0.25;
        $homeFactor = $teamStats['home_factor'];

        $homeBoost = 1.0 + ($homeFactor / 999) * 0.15;

        return [
            'attack' => $attack,
            'defense' => $defense,
            'home_factor' => $homeFactor,
            'home_boost' => $homeBoost,
        ];
    }

    public static function getForzaEffettiva(array $strengthHome, array $strengthAway): array
    {
        // Forza offensiva: attacco di chi attacca vs difesa di chi difende
        $offHome = $strengthHome['attack'] * $strengthHome['home_boost'];
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
        $forze = self::getForzaEffettiva($strengthHome, $strengthAway);

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
            'status' => 1,
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
            $match = (array) $match;
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
                                                $sh = (int) $sh;
                                                $sa = (int) $sa;

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

    public static function renderKnockoutBracket($seasonId, $level, $round_trip)
    {
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('level', '=', $level)
            ->orderBy('phase', 'DESC')
            ->orderBy('round')
            ->get();

        if (empty($matches))
            return;

        // Raggruppa per phase, mantenendo teamA/teamB come home/away originale
        $byPhase = [];
        foreach ($matches as $match) {
            $match = (array) $match;
            $phase = (int) $match['phase'];
            $home = $match['team_home_id'];
            $away = $match['team_away_id'];
            $key = min($home, $away) . '-' . max($home, $away);
            if (!isset($byPhase[$phase][$key])) {
                $byPhase[$phase][$key] = [
                    'teamA' => $home,
                    'teamB' => $away,
                    'scoreA' => 0,
                    'scoreB' => 0,
                    'hasScore' => false,
                    'matches' => [],
                ];
            }
            $byPhase[$phase][$key]['matches'][] = $match;
        }

        // Calcola aggregati per ogni pair
        foreach ($byPhase as $phase => &$phasePairs) {
            foreach ($phasePairs as $key => &$pair) {
                foreach ($pair['matches'] as $m) {
                    if ($m['score_home'] === null || $m['score_away'] === null)
                        continue;
                    $pair['hasScore'] = true;
                    if ($m['team_home_id'] == $pair['teamA']) {
                        $pair['scoreA'] += (int) $m['score_home'];
                        $pair['scoreB'] += (int) $m['score_away'];
                    } else {
                        $pair['scoreA'] += (int) $m['score_away'];
                        $pair['scoreB'] += (int) $m['score_home'];
                    }
                }
            }
        }
        unset($phasePairs, $pair);

        // Ordina fasi: dalla più bassa (fase già giocata) alla più alta
        ksort($byPhase);
        $phaseKeys = array_keys($byPhase); // es. [1,2,4] o [2,4] o [4]

        // Slot della fase più bassa disponibile: ordine libero, è il punto di partenza
        $lowestPhase = $phaseKeys[0];
        $slotMap = [];
        $slotMap[$lowestPhase] = array_keys($byPhase[$lowestPhase]);

        // Risali verso le fasi più alte
        foreach ($phaseKeys as $idx => $phase) {
            if ($idx === 0)
                continue;

            $prevPhase = $phaseKeys[$idx - 1];
            $prevSlots = $slotMap[$prevPhase]; // pair ordinati della fase precedente
            $currentPairs = $byPhase[$phase];
            $usedKeys = [];
            $newSlots = [];

            foreach ($prevSlots as $prevKey) {
                $prevPair = $byPhase[$prevPhase][$prevKey];

                // I due team di questo pair sono i vincitori (o partecipanti)
                // di due match della fase corrente
                foreach ([$prevPair['teamA'], $prevPair['teamB']] as $team) {
                    $foundKey = null;
                    foreach ($currentPairs as $cKey => $cPair) {
                        if (in_array($cKey, $usedKeys))
                            continue;
                        if ($cPair['teamA'] == $team || $cPair['teamB'] == $team) {
                            $foundKey = $cKey;
                            break;
                        }
                    }
                    if ($foundKey !== null) {
                        $newSlots[] = $foundKey;
                        $usedKeys[] = $foundKey;
                    }
                }
            }

            $slotMap[$phase] = $newSlots;
        }

        // Rendering — dalla fase più alta alla più bassa (sinistra → destra)
        krsort($byPhase);
        $phaseKeys = array_keys($byPhase);
        ?>
        <div class="bracket-wrapper overflow-auto pb-4">
            <div class="d-flex gap-0 align-items-stretch" style="min-width: max-content;">

                <?php foreach ($phaseKeys as $phaseIdx => $phase):
                    $slots = $slotMap[$phase] ?? [];
                    ?>
                    <div class="bracket-round d-flex flex-column" style="min-width:210px; padding: 0 8px;">
                        <div class="text-center fw-bold text-uppercase small text-muted mb-2 border-bottom pb-1">
                            <?= Competitions::$round_names[$phase - 1] ?? 'Fase ' . $phase ?>
                        </div>
                        <div class="d-flex flex-column justify-content-around h-100 gap-3">
                            <?php foreach ($slots as $key):
                                $pair = $byPhase[$phase][$key] ?? null;
                                if (!$pair)
                                    continue;
                                $winnerA = $pair['hasScore'] && $pair['scoreA'] > $pair['scoreB'];
                                $winnerB = $pair['hasScore'] && $pair['scoreB'] > $pair['scoreA'];
                                ?>
                                <div class="bracket-match card border shadow-sm" style="border-radius:10px; overflow:hidden;">
                                    <?php
                                    // Ordina i match per round (andata prima, ritorno dopo)
                                    usort($pair['matches'], fn($a, $b) => $a['round'] <=> $b['round']);
                                    $isAR = count($pair['matches']) > 1 && $phase !== 1;

                                    // Calcola gol per match separati (per G1/G2)
                                    $gA = [];
                                    $gB = [];
                                    foreach ($pair['matches'] as $i => $m) {
                                        if ($m['score_home'] === null) {
                                            $gA[$i] = null;
                                            $gB[$i] = null;
                                        } elseif ($m['team_home_id'] == $pair['teamA']) {
                                            $gA[$i] = (int) $m['score_home'];
                                            $gB[$i] = (int) $m['score_away'];
                                        } else {
                                            $gA[$i] = (int) $m['score_away'];
                                            $gB[$i] = (int) $m['score_home'];
                                        }
                                    }
                                    ?>

                                    <!-- Header colonne -->
                                    <?php if ($isAR): ?>
                                        <div class="d-flex px-2 py-1 bg-light border-bottom" style="font-size:0.7rem; color:#999;">
                                            <span style="flex:1"></span>
                                            <span style="width:28px; text-align:center;">G1</span>
                                            <span style="width:28px; text-align:center;">G2</span>
                                            <span style="width:36px; text-align:center; font-weight:bold;">Tot</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Team A -->
                                    <div class="d-flex align-items-center justify-content-between px-2 py-1 <?= $winnerA ? 'bg-success text-white' : ($winnerB ? 'bg-light text-muted' : '') ?>"
                                        style="border-bottom:1px solid #eee;">
                                        <span class="small fw-semibold text-truncate" style="flex:1; max-width:110px;">
                                            <?php Teams::renderTeams($pair['teamA'], 'fw-semibold px-2 rounded-pill d-inline-block', false, false, ['abbr_name' => 3]) ?>
                                        </span>
                                        <?php if ($isAR): ?>
                                            <span style="width:28px; text-align:center; font-size:0.8rem;">
                                                <?= $gA[0] ?? '-' ?>
                                            </span>
                                            <span style="width:28px; text-align:center; font-size:0.8rem;">
                                                <?= $gA[1] ?? '-' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="width:36px; text-align:center;" class="fw-bold">
                                            <?= $pair['hasScore'] ? $pair['scoreA'] : '-' ?>
                                        </span>
                                    </div>

                                    <!-- Team B -->
                                    <div
                                        class="d-flex align-items-center justify-content-between px-2 py-1 <?= $winnerB ? 'bg-success text-white' : ($winnerA ? 'bg-light text-muted' : '') ?>">
                                        <span class="small fw-semibold text-truncate" style="flex:1; max-width:110px;">
                                            <?php Teams::renderTeams($pair['teamB'], 'fw-semibold px-2 rounded-pill d-inline-block', false, false, ['abbr_name' => 3]) ?>
                                        </span>
                                        <?php if ($isAR): ?>
                                            <span style="width:28px; text-align:center; font-size:0.8rem;">
                                                <?= $gB[0] ?? '-' ?>
                                            </span>
                                            <span style="width:28px; text-align:center; font-size:0.8rem;">
                                                <?= $gB[1] ?? '-' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="width:36px; text-align:center;" class="fw-bold">
                                            <?= $pair['hasScore'] ? $pair['scoreB'] : '-' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($phaseIdx < count($phaseKeys) - 1): ?>
                        <div class="d-flex align-items-start px-1" style="color:#ddd; font-size:1.5rem;">›</div>
                    <?php endif; ?>

                <?php endforeach; ?>
            </div>
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
