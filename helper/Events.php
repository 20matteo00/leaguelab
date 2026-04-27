<?php

class Events
{

    public static function generatePlayersStatsForMatches($matchIds)
    {
        foreach ($matchIds as $matchId) {

            $info = DB::table('matches')
                ->where('id', '=', $matchId)
                ->first();

            // reset eventi
            DB::table('match_events')
                ->where('match_id', '=', $matchId)
                ->delete();

            $playersHome = Players::getPlayersByTeam($info['team_home_id']);
            $playersAway = Players::getPlayersByTeam($info['team_away_id']);

            // HOME
            self::processTeamEvents(
                $matchId,
                $playersHome,
                $info['score_home'] ?? 0
            );

            // AWAY
            self::processTeamEvents(
                $matchId,
                $playersAway,
                $info['score_away'] ?? 0
            );
        }
    }

    private static function processTeamEvents($matchId, $players, $goals)
    {
        $minutes = self::generateMinutes($goals);

        foreach ($minutes as $minute) {

            $scorer = self::pickScorer($players);
            if (!$scorer) continue;

            // 🎯 rigore 10%
            $isPenalty = (rand(1, 100) <= 10);

            DB::table('match_events')->insert([
                'match_id'  => $matchId,
                'player_id' => $scorer['id'],
                'type'      => $isPenalty ? 3 : 1,
                'minute'    => $minute
            ]);

            // 🤝 assist 70% (solo se non rigore)
            if (!$isPenalty && rand(1, 100) <= 70) {

                $assist = self::pickAssist($players, $scorer['id']);

                if ($assist) {
                    DB::table('match_events')->insert([
                        'match_id'  => $matchId,
                        'player_id' => $assist['id'],
                        'type'      => 2,
                        'minute'    => $minute
                    ]);
                }
            }
        }
    }

    private static function pickAssist(array $players, int $scorerId): ?array
    {
        $filtered = array_values(array_filter(
            $players,
            fn($p) => $p['id'] !== $scorerId
        ));

        if (empty($filtered)) {
            return null;
        }

        return $filtered[array_rand($filtered)];
    }

    private static function generateMinutes(int $goals): array
    {
        $minutes = [];

        while (count($minutes) < $goals) {
            $m = rand(1, 90);

            // evita troppi duplicati
            if (!in_array($m, $minutes) || rand(0, 100) < 20) {
                $minutes[] = $m;
            }
        }

        sort($minutes);
        return $minutes;
    }

    private static function pickScorer(array $players): ?array
    {
        $rand = mt_rand(1, 1000);

        if ($rand <= 650) $position = 4;        // attaccanti
        elseif ($rand <= 900) $position = 3;    // centrocampisti
        elseif ($rand <= 999) $position = 2;    // difensori
        else $position = 1;                     // portiere

        $filtered = array_values(array_filter(
            $players,
            fn($p) => $p['position'] == $position
        ));

        if (empty($filtered)) {
            $filtered = $players;
        }

        return $filtered[array_rand($filtered)] ?? null;
    }
}
