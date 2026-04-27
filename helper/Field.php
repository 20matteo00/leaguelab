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
}
