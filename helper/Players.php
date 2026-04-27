<?php

class Players
{
    public static function renderPlayers($playerId, $class = '', $country = false, $logo = false)
    {
        $player = DB::table('players')
            ->select('name, images, country')
            ->where('id', '=', $playerId)
            ->first();

        if (!$player) {
            return;
        }

        // LOGO SAFE
        $image = null;

        if (!empty($player['images'])) {
            $imgData = json_decode($player['images'], true);
            $image = $imgData['logo'] ?? null;
        }

        if (!$image) {
            $image = 'images/empty.png';
        }
?>

        <?php if ($logo): ?>
            <img src="<?= $image ?>"
                class="img-sm">
        <?php endif; ?>

        <span class="<?= $class ?>"><?= htmlspecialchars($player['name']) ?></span>

        <?php if ($country && !empty($player['country'])): ?>
            <img
                src="https://flagcdn.com/16x12/<?= strtolower($player['country']) ?>.png"
                alt="<?= htmlspecialchars($player['country']) ?>">
        <?php endif; ?>

<?php
    }

    public static function getEta($dataNascita)
    {
        $oggi = new DateTime();
        $nascita = new DateTime($dataNascita);
        return $oggi->diff($nascita)->y;
    }

    private static function getRandomName($nomi, $cognomi)
    {
        $nome = $nomi[array_rand($nomi)];
        $cognome = $cognomi[array_rand($cognomi)];
        return $nome . ' ' . $cognome;
    }

    private static function getRandomStats($i)
    {
        $stats = [];
        if ($i == 1) {
            $stats = [
                'position' => 1,
                'attack' => rand(1, 250),
                'defense' => rand(751, 1000),
            ];
        } elseif ($i <= 4) {
            $stats = [
                'position' => 2,
                'attack' => rand(251, 500),
                'defense' => rand(501, 750),
            ];
        } elseif ($i <= 8) {
            $stats = [
                'position' => 3,
                'attack' => rand(501, 750),
                'defense' => rand(251, 500),
            ];
        } else {
            $stats = [
                'position' => 4,
                'attack' => rand(751, 1000),
                'defense' => rand(1, 250),
            ];
        }
        return $stats;
    }

    private static function getRandomBirthDate()
    {
        $oggi = new DateTime();

        $min = (clone $oggi)->modify('-40 years')->getTimestamp();
        $max = (clone $oggi)->modify('-16 years')->getTimestamp();

        $randomTimestamp = random_int($min, $max);

        return date('Y-m-d', $randomTimestamp);
    }

    public static function importPlayers()
    {
        $p = Field::getPlayers();
        $teams = DB::table('teams')->select('id')->get();

        foreach ($teams as $team) {
            $players = DB::table('players')
                ->select('position')
                ->where('team_id', '=', $team['id'])
                ->get();
            // ricostruiamo "slot logici" esistenti (1–11)
            $slots = [];
            foreach ($players as $player) {
                $slots[] = $player['position'];
            }
            // conta quanti per ruolo
            $countByRole = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            foreach ($slots as $pos) {
                $countByRole[$pos]++;
            }
            // mappa ruoli → slot richiesti (coerente con la tua funzione)
            $requiredSlots = [];
            // 1 portiere
            if ($countByRole[1] < 1) $requiredSlots[] = 1;
            // 3 difensori (slot 2-4)
            for ($i = $countByRole[2]; $i < 3; $i++) {
                $requiredSlots[] = 2; // uso 2 ma poi lo trasformo sotto
            }
            // 4 centrocampisti (slot 5-8)
            for ($i = $countByRole[3]; $i < 4; $i++) {
                $requiredSlots[] = 5;
            }
            // 3 attaccanti (slot 9-11)
            for ($i = $countByRole[4]; $i < 3; $i++) {
                $requiredSlots[] = 9;
            }
            // 🔁 genera giocatori mancanti
            foreach ($requiredSlots as $slotBase) {
                // trasformo in uno slot realistico per la tua funzione
                if ($slotBase == 1) {
                    $i = 1;
                } elseif ($slotBase == 2) {
                    $i = rand(2, 4);
                } elseif ($slotBase == 5) {
                    $i = rand(5, 8);
                } else {
                    $i = rand(9, 11);
                }
                // nome unico
                do {
                    $name = self::getRandomName($p['nomi'], $p['cognomi']);
                    $exists = DB::table('players')
                        ->where('name', '=', $name)
                        ->first();
                } while ($exists);
                // numero maglia unico
                do {
                    $number = rand(1, 99);
                    $exists = DB::table('players')
                        ->where('team_id', '=', $team['id'])
                        ->where('number', '=', $number)
                        ->first();
                } while ($exists);
                // 👉 QUI usi la TUA funzione
                $stats = self::getRandomStats($i);
                DB::table('players')->insert([
                    'name'        => $name,
                    'team_id'     => $team['id'],
                    'country'     => 'IT',
                    'position'    => $stats['position'],
                    'number'      => $number,
                    'attack'      => $stats['attack'],
                    'defense'     => $stats['defense'],
                    'birth_date'  => self::getRandomBirthDate(),
                    'created'     => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public static function getPlayersByTeam($teamId)
    {
        $result = DB::table('players')
            ->where('team_id', '=', $teamId)
            ->get();

        return $result;
    }

    public static function getPlayersStatsByTeam($teamId)
    {
        $result = DB::table('players')
            ->select('AVG(attack) as attack, AVG(defense) as defense')
            ->where('team_id', '=', $teamId)
            ->first();

        return [
            'attack' => (float) ($result['attack'] ?? 0),
            'defense' => (float) ($result['defense'] ?? 0),
        ];
    }
}
