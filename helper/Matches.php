<?php

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
                self::insertMatches($matches, $seasonId);
                break;
            default:
                break;
        }
    }


    private static function generateLeague($teamsByLevel, $ar = 0)
    {
        $schedule = [];

        foreach ($teamsByLevel as $level => $teams) {
            $teams    = array_values($teams);
            shuffle($teams);
            $numTeams = count($teams);

            // Squadre dispari → aggiungo BYE (null)
            if ($numTeams % 2 !== 0) {
                $teams[] = null;
                $numTeams++;
            }

            $rounds = $numTeams - 1;
            $half   = $numTeams / 2;

            // home[0] è il perno fisso del circle method
            $circle = $teams; // tutti i team nell'array circolare

            $levelSchedule = [];

            for ($round = 0; $round < $rounds; $round++) {
                $matches = [];

                for ($i = 0; $i < $half; $i++) {
                    $t1 = $circle[$i];
                    $t2 = $circle[$numTeams - 1 - $i];

                    if ($t1 === null || $t2 === null) continue;

                    // Giornate con indice dispari (1,3,5...) → inverti per bilanciare casa/trasferta
                    if ($round % 2 === 1) {
                        $matches[] = ['home' => $t2, 'away' => $t1];
                    } else {
                        $matches[] = ['home' => $t1, 'away' => $t2];
                    }
                }

                $levelSchedule[] = $matches;

                // Circle method: tieni fisso circle[0], ruota gli altri
                $fixed  = array_shift($circle);           // estrai il perno
                $last   = array_pop($circle);             // prendi l'ultimo
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

    private static function insertMatches($matches, $seasonId, $type = 'levels')
    {
        if (empty($matches)) return;

        foreach ($matches as $levelOrGroup => $rounds) {
            foreach ($rounds as $roundIndex => $roundMatches) {
                foreach ($roundMatches as $match) {
                    DB::table('matches')->insert([
                        'season_id'    => $seasonId,
                        'level'        => $type === 'levels' ? $levelOrGroup : 1,
                        'group_id'     => $type === 'groups' ? $levelOrGroup : null,
                        'phase'        => null,
                        'team_home_id' => $match['home'],
                        'team_away_id' => $match['away'],
                        'score_home'   => null,
                        'score_away'   => null,
                        'match_date'   => null,
                        'round'        => $roundIndex + 1,
                        'status'       => 0,
                    ]);
                }
            }
        }

        DB::table('seasons')->where('id', '=', $seasonId)->update([
            'status' => 1,
        ]);
    }

    public static function checkNullMatches($seasonId){
        $matches = DB::table('matches')->where('season_id', '=', $seasonId)->whereNull('score_home')->whereNull('score_away')->count();
        return $matches;
    }
}
