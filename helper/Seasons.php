<?php

class Seasons
{
    public static $status = [
        0 => [
            'label' => 'Pianificata',
            'badge' => 'secondary'
        ],
        1 => [
            'label' => 'In corso',
            'badge' => 'success'
        ],
        2 => [
            'label' => 'Fase Finale',
            'badge' => 'success'
        ],
        99 => [
            'label' => 'Conclusa',
            'badge' => 'dark'
        ],
    ];
    public static $menu = [
        1 => [
            [
                'action' => 'calendar',
                'icon' => 'calendar-event',
                'label' => 'Calendario'
            ],
            [
                'action' => 'standings',
                'icon' => 'trophy',
                'label' => 'Classifica'
            ],
            [
                'action' => 'bracket',
                'icon' => 'diagram-3',
                'label' => 'Tabellone'
            ],
            [
                'action' => 'trend',
                'icon' => 'graph-up-arrow',
                'label' => 'Andamento'
            ],
            [
                'action' => 'markers',
                'icon' => 'person-standing',
                'label' => 'Marcatori'
            ],
            [
                'action' => 'stats',
                'icon' => 'bar-chart',
                'label' => 'Statistiche'
            ]
        ],
        2 => [
            [
                'action' => 'calendar',
                'icon' => 'calendar-event',
                'label' => 'Calendario'
            ],
            [
                'action' => 'bracket',
                'icon' => 'diagram-3',
                'label' => 'Tabellone'
            ],
            [
                'action' => 'markers',
                'icon' => 'person-standing',
                'label' => 'Marcatori'
            ],
            [
                'action' => 'stats',
                'icon' => 'bar-chart',
                'label' => 'Statistiche'
            ]
        ],
        3 => [
            [
                'action' => 'calendar',
                'icon' => 'calendar-event',
                'label' => 'Calendario'
            ],
            [
                'action' => 'standings',
                'icon' => 'trophy',
                'label' => 'Classifica'
            ],
            [
                'action' => 'bracket',
                'icon' => 'diagram-3',
                'label' => 'Tabellone'
            ],
            [
                'action' => 'trend',
                'icon' => 'graph-up-arrow',
                'label' => 'Andamento'
            ],
            [
                'action' => 'markers',
                'icon' => 'person-standing',
                'label' => 'Marcatori'
            ],
            [
                'action' => 'stats',
                'icon' => 'bar-chart',
                'label' => 'Statistiche'
            ]
        ],
    ];

    public static function getTeamsLevelsBySeason($seasonId)
    {
        $teamsLevels = DB::table('season_teams')->where('season_id', '=', $seasonId)->get();
        $result = [
            'groups' => [],
            'levels' => []
        ];

        $hasGroups = false;

        foreach ($teamsLevels as $row) {
            if (!is_null($row['group_id'])) {
                $hasGroups = true;
                $group = (int) $row['group_id'];

                if (!isset($result['groups'][$group])) {
                    $result['groups'][$group] = [];
                }

                $result['groups'][$group][] = (int) $row['team_id'];
            } else {
                $level = (int) $row['level'];

                if (!isset($result['levels'][$level])) {
                    $result['levels'][$level] = [];
                }

                $result['levels'][$level][] = (int) $row['team_id'];
            }
        }

        if ($hasGroups) {
            $final = $result['groups'];
        } else {
            $final = $result['levels'];
        }
        return $final;
    }

    public static function getMaxLevelBySeason($seasonId)
    {
        $result = DB::table('season_teams')
            ->select('MAX(group_id) as max_level')
            ->where('season_id', '=', $seasonId)
            ->first()['max_level'];

        if ($result) return (int) $result;

        $result = DB::table('season_teams')
            ->select('MAX(level) as max_level')
            ->where('season_id', '=', $seasonId)
            ->first()['max_level'];

        return (int) ($result ?? 0);
    }

    public static function getSeasonStatus($seasonId)
    {
        return DB::table('seasons')->select('status')->where('id', '=', $seasonId)->first()['status'];
    }

    public static function checkSeasonEnd($seasonId)
    {
        $status = self::getSeasonStatus($seasonId);
        if ($status == 99)
            return true;
        return false;
    }

    public static function setSeasonStatusEnd($seasonId)
    {
        $compId = DB::table('seasons')->select('competition_id')->where('id', '=', $seasonId)->first()['competition_id'];
        DB::table('seasons')
            ->where('id', '=', $seasonId)
            ->update([
                'status' => 99,
            ]);
        header("Location: index.php?page=competition&id=" . $compId);
        exit;
    }

    public static function getMaxSeasonEndByCompetition($compId)
    {
        $lastSeasonEnd = self::getLastSeason($compId)['status'];

        if ($lastSeasonEnd == 99)
            return true;
        return false;
    }

    public static function getLastSeason($compId)
    {
        return DB::table('seasons')
            ->where('competition_id', '=', $compId)
            ->orderBy('season_year', 'desc')
            ->first();
    }

    public static function getSeasonByTeamLevel($seasons, $team, $level, $type)
    {
        $id = null;
        if ($type == 'first') {
            $id = DB::table('season_teams')
                ->select('MIN(season_id) as season_id')
                ->whereIn('season_id', $seasons)
                ->where('level', '=', $level)
                ->where('team_id', '=', $team)
                ->first()['season_id'];
        } elseif ($type == 'last') {
            $id = DB::table('season_teams')
                ->select('MAX(season_id) as season_id')
                ->whereIn('season_id', $seasons)
                ->where('level', '=', $level)
                ->where('team_id', '=', $team)
                ->first()['season_id'];
        }
        $year = 0;
        if ($id) $year = DB::table('seasons')->select('season_year')->where('id', '=', $id)->first()['season_year'];
        return $year;
    }

    public static function seasonContinue($compId)
    {
        $lastSeason = self::getLastSeason($compId);
        $year = $lastSeason['season_year'];
        $idSeason = $lastSeason['id'];

        // Livelli configurati per questa competizione, ordinati
        $compLevels = DB::table('competition_levels')
            ->where('competition_id', '=', $compId)
            ->orderBy('level')
            ->get();

        // Nuova stagione
        $newSeasonId = DB::table('seasons')->insert([
            'competition_id' => $compId,
            'season_year' => $year + 1,
            'status' => 0,
            'created' => date('Y-m-d H:i:s'),
        ]);

        // Caso semplice: nessun livello configurato → copia i team così come sono
        if (empty($compLevels)) {
            $oldTeams = DB::table('season_teams')
                ->where('season_id', '=', $idSeason)
                ->get();

            foreach ($oldTeams as $row) {
                DB::table('season_teams')->insert([
                    'season_id' => $newSeasonId,
                    'team_id' => $row['team_id'],
                    'level' => $row['level'],
                ]);
            }
            return $newSeasonId;
        }

        // Caso con livelli: calcola promozioni/retrocessioni
        $teamsLevel = self::getTeamsLevelsBySeason($idSeason);

        // Per ogni livello calcola la classifica e individua chi sale/scende
        // $movements[teamId] = livello destinazione nella nuova stagione
        $movements = [];

        // Indicizza i comp_levels per accesso rapido
        $levelConfig = [];
        foreach ($compLevels as $cl) {
            $levelConfig[(int) $cl['level']] = $cl;
        }

        foreach ($levelConfig as $levelNum => $config) {
            $teams = $teamsLevel[$levelNum] ?? [];
            if (empty($teams))
                continue;

            $teamsAssoc = array_combine($teams, $teams); // [8=>8, 9=>9, ...]

            // Recupera le partite di questo livello nella stagione corrente
            $matches = DB::table('matches')
                ->where('season_id', '=', $idSeason)
                ->where('level', '=', $levelNum)
                ->get();

            // Costruisce la classifica (tutti i match, nessun filtro)
            $standings = Standings::buildStandings($matches, $teamsAssoc, 'total');

            $teamIds = array_column($standings, 'team_id'); // ← prende il campo team_id da ogni riga

            $totalTeams = count($teamIds);
            $promotionSpots = (int) $config['promotion_spots'];
            $relegationSpots = (int) $config['relegation_spots'];

            foreach ($teamIds as $pos => $teamId) {
                $posizione = $pos + 1; // 1-based

                if ($levelNum > 1 && $posizione <= $promotionSpots) {
                    // Sale al livello superiore
                    $movements[$teamId] = $levelNum - 1;
                } elseif (isset($levelConfig[$levelNum + 1]) && $posizione > $totalTeams - $relegationSpots) {
                    // Scende al livello inferiore (solo se esiste)
                    $movements[$teamId] = $levelNum + 1;
                } else {
                    // Rimane
                    $movements[$teamId] = $levelNum;
                }
            }
        }

        // Inserisce i team nella nuova stagione con il livello aggiornato
        foreach ($movements as $teamId => $newLevel) {
            DB::table('season_teams')->insert([
                'season_id' => $newSeasonId,
                'team_id' => $teamId,
                'level' => $newLevel,
            ]);
        }

        return $newSeasonId;
    }

    public static function renderMenu($page, $urlParams, $mode, $status = null)
    {
        if($status && $status == 2) $mode = 2;
        $menu = self::$menu[$mode];
?>
        <div class="row g-2 mb-4">
            <?php foreach ($menu as $m): ?>
                <?php $urlParams['action'] = $m['action']; ?>
                <div class="col">
                    <?= Link::a(
                        $page,
                        '<i class="bi bi-' . $m['icon'] . '"></i> ' . $m['label'],
                        $urlParams,
                        [
                            'class' => 'btn btn-outline-secondary w-100 p-3 fs-5'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
<?php
    }

    public static function getPreviousSeasonById($seasonId)
    {
        $currentSeason = DB::table('seasons')->where('id', '=', $seasonId)->first();
        return DB::table('seasons')
            ->where('competition_id', '=', $currentSeason['competition_id'])
            ->where('season_year', '=', $currentSeason['season_year'] - 1)
            ->first();
    }

    public static function getNextSeasonById($seasonId)
    {
        $currentSeason = DB::table('seasons')->where('id', '=', $seasonId)->first();
        return DB::table('seasons')
            ->where('competition_id', '=', $currentSeason['competition_id'])
            ->where('season_year', '=', $currentSeason['season_year'] + 1)
            ->first();
    }
}
