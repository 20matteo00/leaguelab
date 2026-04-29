<?php

class Field
{
    private const STATES_PATH = "json/states.json";
    private const POSITION_PATH = "json/positions.json";
    private const MODALITY_PATH = "json/modality.json";
    private const TEAMS_PATH = "json/teams.json";
    private const PLAYERS_PATH = "json/players.json";
    private const COMPETITIONS_PATH = "json/competitions.json";

    public static function getStates(): array
    {
        return self::getFile(self::STATES_PATH);
    }

    public static function getPosition(): array
    {
        return self::getFile(self::POSITION_PATH);
    }

    public static function getModality(): array
    {
        return self::getFile(self::MODALITY_PATH);
    }

    public static function getTeams(): array
    {
        return self::getFile(self::TEAMS_PATH);
    }

    public static function getPlayers(): array
    {
        return self::getFile(self::PLAYERS_PATH);
    }

    private static function getFile($file)
    {
        if (!file_exists($file)) {
            return [];
        }

        $json = file_get_contents($file);

        return json_decode($json, true) ?? [];
    }

    private static function setFile($file, $data)
    {
        // Converte in JSON formattato
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Scrive (sovrascrive) il file
        file_put_contents($file, $json);
    }

    private static function sortArrayByField($array, $field, $ascending = true)
    {
        usort($array, function ($a, $b) use ($field, $ascending) {
            $valA = $a[$field];
            $valB = $b[$field];

            if (is_numeric($valA)) $valA = (float)$valA;
            if (is_numeric($valB)) $valB = (float)$valB;

            if ($valA == $valB) return 0;

            return $ascending
                ? ($valA < $valB ? -1 : 1)
                : ($valA > $valB ? -1 : 1);
        });

        return $array;
    }

    public static function reorderTeamsByName()
    {
        $teams = self::getTeams();
        $teams = self::sortArrayByField($teams, 'name'); // <-- qui passi il campo
        self::setFile(self::TEAMS_PATH, $teams);
    }
}
