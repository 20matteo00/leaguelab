<?php

use Dom\Text;

class Matches
{
    public static $status = [
        0 => [
            'label' => 'Da Giocare',
            'badge' => 'secondary'
        ],
        1 => [
            'label' => 'Finito',
            'badge' => 'dark'
        ],
    ];
    public static function generateMatches($compId, $seasonId)
    {
        $competition = DB::table('competitions')->where('id', '=', $compId)->first();
        $modality = $competition['modality'];
        $round_trip = $competition['round_trip'];

        $teams = Seasons::getTeamsLevelsBySeason($seasonId);

        switch ($modality) {
            case '1':
                $matches = self::generateLeague($teams, $round_trip);
                self::insertMatches($matches, $seasonId, 'league');
                break;

            case '2':
                $matches = self::generateKnockout($teams, $round_trip);
                self::insertMatches($matches, $seasonId, 'knockout');
            default:
                break;
        }
    }


    private static function generateLeague($teamsByLevel, $ar = 0)
    {
        $schedule = [];

        foreach ($teamsByLevel as $level => $teams) {
            $teams = array_values($teams);
            shuffle($teams);
            $numTeams = count($teams);

            // Squadre dispari → aggiungo BYE (null)
            if ($numTeams % 2 !== 0) {
                $teams[] = null;
                $numTeams++;
            }

            $rounds = $numTeams - 1;
            $half = $numTeams / 2;

            // home[0] è il perno fisso del circle method
            $circle = $teams; // tutti i team nell'array circolare

            $levelSchedule = [];

            for ($round = 0; $round < $rounds; $round++) {
                $matches = [];

                for ($i = 0; $i < $half; $i++) {
                    $t1 = $circle[$i];
                    $t2 = $circle[$numTeams - 1 - $i];

                    if ($t1 === null || $t2 === null)
                        continue;

                    // Giornate con indice dispari (1,3,5...) → inverti per bilanciare casa/trasferta
                    if ($round % 2 === 1) {
                        $matches[] = ['home' => $t2, 'away' => $t1];
                    } else {
                        $matches[] = ['home' => $t1, 'away' => $t2];
                    }
                }

                $levelSchedule[] = $matches;

                // Circle method: tieni fisso circle[0], ruota gli altri
                $fixed = array_shift($circle);           // estrai il perno
                $last = array_pop($circle);             // prendi l'ultimo
                array_unshift($circle, $last);            // mettilo in seconda posizione
                array_unshift($circle, $fixed);           // rimetti il perno in testa
            }

            // Ritorno: duplica le giornate di andata invertendo home/away
            if ($ar == 1) {
                $returnRounds = [];
                foreach ($levelSchedule as $matches) {
                    $rev = [];
                    foreach ($matches as $m) {
                        $rev[] = ['home' => $m['away'], 'away' => $m['home']];
                    }
                    $returnRounds[] = $rev;
                }
                $levelSchedule = array_merge($levelSchedule, $returnRounds);
            }

            $schedule[$level] = $levelSchedule;
        }

        return $schedule;
    }

    private static function generateKnockout($teamsByLevel, $ar = 0)
    {
        $numTeamsAccepted = [1, 2, 4, 8, 16, 32, 64, 128];
        $schedule = [];

        foreach ($teamsByLevel as $level => $teams) {
            $numTeams = count($teams);

            if (!in_array($numTeams, $numTeamsAccepted)) {
                return [];
            }

            shuffle($teams);

            $half = $numTeams / 2;

            $roundMatches = [];

            for ($i = 0; $i < $half; $i++) {
                $home = $teams[$i];
                $away = $teams[$numTeams - 1 - $i];

                $roundMatches[] = [
                    'phase' => log($numTeams, 2),
                    'home' => $home,
                    'away' => $away
                ];
            }

            // 🔥 IDENTICO STRUTTURA LEAGUE: level → round → matches
            $levelSchedule = [];

            // ANDATA = round 1
            $levelSchedule[] = $roundMatches;

            // RITORNO = round 2
            if ($ar == 1 && $numTeams > 2) {
                $returnMatches = [];

                foreach ($roundMatches as $m) {
                    $returnMatches[] = [
                        'phase' => log($numTeams, 2),
                        'home' => $m['away'],
                        'away' => $m['home']
                    ];
                }

                $levelSchedule[] = $returnMatches;
            }

            $schedule[$level] = $levelSchedule;
        }

        return $schedule;
    }

    public static function generateNextPhase($id, $ar = 0)
    {
        $minPhase = DB::table('matches')
            ->select('MIN(phase) as min_phase')
            ->where('season_id', '=', $id)
            ->whereNotNull('score_home')
            ->whereNotNull('score_away')
            ->first()['min_phase'];

        $matches = DB::table('matches')->where('season_id', '=', $id)->where('phase', '=', $minPhase)->get();

        $winner = self::getWinners($matches);
        $nextPhase[1] = $winner;

        $matches = self::generateknockout($nextPhase, $ar);
        self::insertMatches($matches, $id, 'knockout');

        header("Location: index.php?page=season&id=" . $id);
        exit;
    }

    private static function insertMatches($matches, $seasonId, $type = 'league')
    {
        if (empty($matches))
            return;
        foreach ($matches as $levelOrGroup => $rounds) {
            foreach ($rounds as $roundIndex => $roundMatches) {
                foreach ($roundMatches as $match) {
                    if ($type == 'league') {
                        DB::table('matches')->insert([
                            'season_id' => $seasonId,
                            'level' => $levelOrGroup,
                            'group_id' => null,
                            'phase' => null,
                            'team_home_id' => $match['home'],
                            'team_away_id' => $match['away'],
                            'score_home' => null,
                            'score_away' => null,
                            'match_date' => null,
                            'round' => $roundIndex + 1,
                            'status' => 0,
                        ]);
                    } elseif ($type == 'knockout') {
                        DB::table('matches')->insert([
                            'season_id' => $seasonId,
                            'level' => $levelOrGroup,
                            'group_id' => null,
                            'phase' => $match['phase'],
                            'team_home_id' => $match['home'],
                            'team_away_id' => $match['away'],
                            'score_home' => null,
                            'score_away' => null,
                            'match_date' => null,
                            'round' => $roundIndex + 1,
                            'status' => 0,
                        ]);
                    }
                }
            }
        }

        DB::table('seasons')->where('id', '=', $seasonId)->update([
            'status' => 1,
        ]);
    }

    public static function checkNullMatches($seasonId)
    {
        $matches = DB::table('matches')->where('season_id', '=', $seasonId)->whereNull('score_home')->whereNull('score_away')->count();
        return $matches;
    }

    public static function checkFinalPhase($seasonId): bool
    {
        $hasPhaseOne = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->where('phase', '=', 1)
            ->whereNotNull('score_home')   // aggiunto
            ->whereNotNull('score_away')   // aggiunto
            ->exists();

        if ($hasPhaseOne) {
            return true;
        }

        $hasAnyNonNullPhase = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->whereNotNull('phase')
            ->exists();

        if ($hasAnyNonNullPhase) {
            return false;
        }

        return true;
    }

    public static function buildPairs($matches)
    {
        $pairs = [];

        foreach ($matches as $match) {

            // supporta sia array che oggetto
            $home = is_array($match) ? $match['team_home_id'] : $match->team_home_id;
            $away = is_array($match) ? $match['team_away_id'] : $match->team_away_id;
            $scoreHome = is_array($match) ? $match['score_home'] : $match->score_home;
            $scoreAway = is_array($match) ? $match['score_away'] : $match->score_away;

            $teamA = min($home, $away);
            $teamB = max($home, $away);

            $key = $teamA . '-' . $teamB;

            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'teamA' => $teamA,
                    'teamB' => $teamB,
                    'scoreA' => 0,
                    'scoreB' => 0,
                ];
            }

            if ($home == $teamA) {
                $pairs[$key]['scoreA'] += $scoreHome;
                $pairs[$key]['scoreB'] += $scoreAway;
            } else {
                $pairs[$key]['scoreA'] += $scoreAway;
                $pairs[$key]['scoreB'] += $scoreHome;
            }
        }

        return $pairs;
    }
    public static function getWinners($matches)
    {
        $pairs = self::buildPairs($matches);

        $winners = [];

        foreach ($pairs as $pair) {
            if ($pair['scoreA'] > $pair['scoreB']) {
                $winners[] = $pair['teamA'];
            } elseif ($pair['scoreB'] > $pair['scoreA']) {
                $winners[] = $pair['teamB'];
            }
        }

        return $winners;
    }

    public static function getDraws($seasonId)
    {
        $matches = DB::table('matches')
            ->where('season_id', '=', $seasonId)
            ->get();

        $pairs = self::buildPairs($matches);

        $draws = [];

        foreach ($pairs as $pair) {
            if ($pair['scoreA'] === $pair['scoreB']) {
                $draws[] = $pair;
            }
        }

        return $draws;
    }

    public static function getMatchesByTeamsComp($seasons, $teamHome, $teamAway, $location = 'all', $level = 'all', $order = 'oldest_first')
    {
        $query = DB::table('matches')
            ->whereIn('season_id', $seasons);

        // 📍 LOCATION
        if ($location === 'all') {
            $query->whereRaw(
                "(
        (team_home_id = :home1 AND team_away_id = :away1)
        OR
        (team_home_id = :home2 AND team_away_id = :away2)
    )",
                [
                    'home1' => $teamHome,
                    'away1' => $teamAway,
                    'home2' => $teamAway,
                    'away2' => $teamHome,
                ]
            );
        }

        if ($location === 'home') {
            // squadra 1 in casa
            $query->where('team_home_id', '=', $teamHome)
                ->where('team_away_id', '=', $teamAway);
        }

        if ($location === 'away') {
            // squadra 1 in trasferta
            $query->where('team_home_id', '=', $teamAway)
                ->where('team_away_id', '=', $teamHome);
        }

        // 🎚️ LEVEL
        if ($level !== 'all') {
            $query->where('level', '=', $level);
        }

        // 🔃 ORDER
        // occhio: il tuo builder vuole colonna + direzione separati
        if ($order === 'oldest_first') {
            $query->orderBy('season_id', 'ASC')->orderBy('round', 'ASC');
        } else {
            $query->orderBy('season_id', 'DESC')->orderBy('round', 'DESC');
        }

        return $query->get();
    }

    public static function getMatchesByTeamsCompAdvanced($seasons, $teams, $level = 'all', $order = 'oldest_first')
    {
        $query = DB::table('matches')
            ->whereIn('season_id', $seasons)
            ->whereIn("team_home_id", $teams)
            ->whereIn("team_away_id", $teams);

        // 🎚️ LEVEL
        if ($level !== 'all') {
            $query->where('level', '=', $level);
        }

        // 🔃 ORDER
        // occhio: il tuo builder vuole colonna + direzione separati
        if ($order === 'oldest_first') {
            $query->orderBy('season_id', 'ASC')->orderBy('phase', 'DESC')->orderBy('round', 'ASC');
        } else {
            $query->orderBy('season_id', 'DESC')->orderBy('phase', 'ASC')->orderBy('round', 'DESC');
        }

        return $query->get();
    }

    public static function renderMatchesByTeamsAndComp($compId, $mode)
    {
        $seasonId = Seasons::getLastSeason($compId)['id'];
        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasonId)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $seasons = DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->where('status', '=', '2')->get();
        $seasons = array_column($seasons, 'id');

        $maxLevel = DB::table('competition_levels')
            ->select('MAX(level) as max_level')
            ->where('competition_id', '=', $compId)
            ->first()['max_level'];

        $teamHome = $_POST['team1'] ?? '';
        $teamAway = $_POST['team2'] ?? '';
        $location = $_POST['location'] ?? 'all';
        $level = $_POST['level'] ?? 'all';
        $order = $_POST['order'] ?? 'oldest_first';

        $matches = [];
        $validTeams = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validTeams = !empty($teamHome) && !empty($teamAway) && ($teamHome != $teamAway);

            if ($validTeams && !empty($seasons)) {
                $matches = self::getMatchesByTeamsComp($seasons, $teamHome, $teamAway, $location, $level, $order);
            }
        }
        ?>
        <div>
            <form method="post" action="" class="head-to-head-form my-4">
                <div class="row">
                    <div class="col form-group">
                        <label for="team1">Squadra 1</label>
                        <select name="team1" id="team1" class="form-control">
                            <option value="">-- Scegli --</option>
                            <?php foreach ($teams as $key => $team): ?>
                                <option value="<?= $key ?>" <?= ($key == $teamHome) ? 'selected' : '' ?>>
                                    <?php Teams::renderTeams($key) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="team2">Squadra 2</label>
                        <select name="team2" id="team2" class="form-control">
                            <option value="">-- Scegli --</option>
                            <?php foreach ($teams as $key => $team): ?>
                                <option value="<?= $key ?>" <?= ($key == $teamAway) ? 'selected' : '' ?>>
                                    <?php Teams::renderTeams($key) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="location">Luogo</label>
                        <select name="location" id="location" class="form-control">
                            <option value="all" <?= ($location == 'all') ? 'selected' : '' ?>>Casa + Trasferta</option>
                            <option value="home" <?= ($location == 'home') ? 'selected' : '' ?>>Casa Squadra 1</option>
                            <option value="away" <?= ($location == 'away') ? 'selected' : '' ?>>Casa Squadra 2</option>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="level">Livello</label>
                        <select name="level" id="level" class="form-control">
                            <option value="all" <?= ($level == 'all') ? 'selected' : '' ?>>Tutti</option>
                            <?php for ($i = 1; $i <= $maxLevel; $i++): ?>
                                <option value="<?= $i ?>" <?= ($level == $i) ? 'selected' : '' ?>>
                                    Livello <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="order">Ordine</label>
                        <select name="order" id="order" class="form-control">
                            <option value="oldest_first" <?= ($order == 'oldest_first') ? 'selected' : '' ?>>
                                Prima il più vecchio
                            </option>
                            <option value="newest_first" <?= ($order == 'newest_first') ? 'selected' : '' ?>>
                                Prima il più recente
                            </option>
                        </select>
                    </div>

                    <div class="col form-group d-flex">
                        <button type="submit" class="btn btn-primary mt-auto w-100">Invia</button>
                    </div>
                </div>
            </form>
            <?php if (!empty($matches)): ?>
                <div class="table-responsive my-5">
                    <table class="table table-hover align-middle shadow-sm text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>Anno</th>
                                <th>Livello</th>
                                <?php if ($mode == 2): ?>
                                    <th>Fase</th>
                                <?php endif; ?>
                                <th>Giornata</th>
                                <th>Incontro</th>
                                <th>Risultato</th>
                                <th>Esito</th>
                                <th>Dettaglio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                                <?php
                                $season_year = DB::table('seasons')->select('season_year')->where('id', '=', $match['season_id'])->first()['season_year'];
                                $class = 'warning';
                                $esito = 'X';
                                if ($match['score_home'] > $match['score_away']) {
                                    $class = 'success';
                                    $esito = '1';
                                } elseif ($match['score_home'] < $match['score_away']) {
                                    $class = 'danger';
                                    $esito = '2';
                                }
                                $nameRound = $match['round'];
                                if ($mode == 2) {
                                    $nameRound = ($match['round'] == 1) ? 'Andata' : 'Ritorno';
                                }
                                ?>
                                <tr>
                                    <td><?= $season_year ?></td>
                                    <td><?= $match['level'] ?></td>
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
                                    <td class="text-<?= $class ?>"><?= $esito ?></td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="index.php?page=match&id=<?= $match['id'] ?>" class="btn btn-info btn-sm px-2"
                                                title="Visualizza Incontro">👁️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $teams = [
                    $teamHome => DB::table('teams')->select('name')->where('id', '=', $teamHome)->first()['name'],
                    $teamAway => DB::table('teams')->select('name')->where('id', '=', $teamAway)->first()['name'],
                ];
                $standings = Standings::buildStandings($matches, $teams, 'all');

                ?>
                <div class="row">
                    <?php foreach ($standings as $teamId => $stats): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card shadow-sm">
                                <!-- HEADER -->
                                <div class="card-header bg-dark text-white text-center">
                                    <strong>
                                        <?php Teams::renderTeams($stats['team_id'], 'px-2 rounded-pill d-inline-block small') ?>
                                    </strong>
                                </div>
                                <!-- BODY -->
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Giocate</span>
                                            <span class="badge bg-secondary"><?= $stats['played'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Vinte</span>
                                            <span class="badge bg-success"><?= $stats['won'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Pari</span>
                                            <span class="badge bg-warning text-dark"><?= $stats['drawn'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Perse</span>
                                            <span class="badge bg-danger"><?= $stats['lost'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Gol fatti</span>
                                            <span class="badge bg-success"><?= $stats['gf'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Gol subiti</span>
                                            <span class="badge bg-danger"><?= $stats['ga'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Differenza Reti</span>
                                            <span class="badge <?= $stats['gd'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $stats['gd'] ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($matches) && ($validTeams)): ?>
                <?php Alert::generateAlert('Nessun Incontro tra le 2 squadre in questa competizione', 'warning', 'Nessun Incontro') ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderMatchesByTeamsAndCompAdvanced($compId, $mode)
    {
        $seasonId = Seasons::getLastSeason($compId)['id'];
        $teams = DB::table('season_teams')->select('team_id')->where('season_id', '=', $seasonId)->get();
        $teams = array_column($teams, 'team_id');
        $teams = Teams::orderTeamsByName($teams);

        $seasons = DB::table('seasons')->select('id')->where('competition_id', '=', $compId)->where('status', '=', '2')->get();
        $seasons = array_column($seasons, 'id');

        $maxLevel = DB::table('competition_levels')
            ->select('MAX(level) as max_level')
            ->where('competition_id', '=', $compId)
            ->first()['max_level'];

        $teamsSelected = $_POST['teams'] ?? [];
        $level = $_POST['level'] ?? 'all';
        $order = $_POST['order'] ?? 'oldest_first';

        $matches = [];
        $validTeams = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validTeams = count($teamsSelected) > 1 && count($teamsSelected) < 11;

            if ($validTeams && !empty($seasons)) {
                $matches = self::getMatchesByTeamsCompAdvanced($seasons, $teamsSelected, $level, $order);
            }
        }
        ?>
        <div>
            <form method="post" action="" class="head-to-head-form my-4">
                <div class="row">
                    <div class="col form-group">
                        <label for="teams">Squadra 1</label>
                        <select name="teams[]" id="teams" class="form-control" multiple="true" size="10">
                            <option value="">-- Scegli --</option>
                            <?php foreach ($teams as $key => $team): ?>
                                <option value="<?= $key ?>" <?= (in_array($key, $teamsSelected)) ? 'selected' : '' ?>>
                                    <?php Teams::renderTeams($key) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="level">Livello</label>
                        <select name="level" id="level" class="form-control">
                            <option value="all" <?= ($level == 'all') ? 'selected' : '' ?>>Tutti</option>
                            <?php for ($i = 1; $i <= $maxLevel; $i++): ?>
                                <option value="<?= $i ?>" <?= ($level == $i) ? 'selected' : '' ?>>
                                    Livello <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col form-group">
                        <label for="order">Ordine</label>
                        <select name="order" id="order" class="form-control">
                            <option value="oldest_first" <?= ($order == 'oldest_first') ? 'selected' : '' ?>>
                                Prima il più vecchio
                            </option>
                            <option value="newest_first" <?= ($order == 'newest_first') ? 'selected' : '' ?>>
                                Prima il più recente
                            </option>
                        </select>
                    </div>

                    <div class="col form-group d-flex">
                        <button type="submit" class="btn btn-primary mb-auto w-100 mt-4">Invia</button>
                    </div>
                </div>
            </form>
            <?php if (!empty($matches)): ?>
                <div class="table-responsive my-5">
                    <table class="table table-hover align-middle shadow-sm text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>Anno</th>
                                <th>Livello</th>
                                <?php if ($mode == 2): ?>
                                    <th>Fase</th>
                                <?php endif; ?>
                                <th>Giornata</th>
                                <th>Incontro</th>
                                <th>Risultato</th>
                                <th>Esito</th>
                                <th>Dettaglio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                                <?php
                                $season_year = DB::table('seasons')->select('season_year')->where('id', '=', $match['season_id'])->first()['season_year'];
                                $class = 'warning';
                                $esito = 'X';
                                if ($match['score_home'] > $match['score_away']) {
                                    $class = 'success';
                                    $esito = '1';
                                } elseif ($match['score_home'] < $match['score_away']) {
                                    $class = 'danger';
                                    $esito = '2';
                                }
                                $nameRound = $match['round'];
                                if ($mode == 2) {
                                    $nameRound = ($match['round'] == 1) ? 'Andata' : 'Ritorno';
                                }
                                ?>
                                <tr>
                                    <td><?= $season_year ?></td>
                                    <td><?= $match['level'] ?></td>
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
                                    <td class="text-<?= $class ?>"><?= $esito ?></td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="index.php?page=match&id=<?= $match['id'] ?>" class="btn btn-info btn-sm px-2"
                                                title="Visualizza Incontro">👁️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $teams = [];
                foreach ($teamsSelected as $team) {
                    $teams[$team] = DB::table('teams')->select('name')->where('id', '=', $team)->first()['name'];
                }
                $standings = Standings::buildStandings($matches, $teams, 'all');
                ?>
                <div class="row">
                    <?php foreach ($standings as $teamId => $stats): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card shadow-sm">
                                <!-- HEADER -->
                                <div class="card-header bg-dark text-white text-center">
                                    <strong>
                                        <?php Teams::renderTeams($stats['team_id'], 'px-2 rounded-pill d-inline-block small') ?>
                                    </strong>
                                </div>
                                <!-- BODY -->
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Punti</span>
                                            <span class="badge bg-dark"><?= $stats['pts'] ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Giocate</span>
                                            <span class="badge bg-secondary"><?= $stats['played'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Vinte</span>
                                            <span class="badge bg-success"><?= $stats['won'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Pari</span>
                                            <span class="badge bg-warning text-dark"><?= $stats['drawn'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Perse</span>
                                            <span class="badge bg-danger"><?= $stats['lost'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Gol fatti</span>
                                            <span class="badge bg-success"><?= $stats['gf'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Gol subiti</span>
                                            <span class="badge bg-danger"><?= $stats['ga'] ?></span>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Differenza Reti</span>
                                            <span class="badge <?= $stats['gd'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $stats['gd'] ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($matches) && ($validTeams)): ?>
                <?php Alert::generateAlert('Nessun Incontro tra le squadre in questa competizione', 'warning', 'Nessun Incontro') ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
