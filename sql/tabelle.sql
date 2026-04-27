/* =========================
 COMPETITIONS
 ========================= */
CREATE TABLE IF NOT EXISTS competitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL DEFAULT '',
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    modality TINYINT NOT NULL,
    -- 1=campionato, 2=eliminazione diretta, 3=gironi+eliminazione
    -- 🏆 comuni a tutte le modalità
    participants SMALLINT NOT NULL DEFAULT 0,
    -- tot squadre che entrano
    round_trip TINYINT NOT NULL DEFAULT 1,
    -- andata e ritorno (0/1)
    -- ⚽ solo modality=1 (campionato) → tutto già coperto sopra
    -- 🔀 solo modality=3 (gironi+eliminazione)
    num_groups TINYINT DEFAULT NULL,
    -- quanti gironi
    qualifiers TINYINT DEFAULT NULL,
    -- quante passano per girone
    -- 🌍 info
    country VARCHAR(2) DEFAULT NULL,
    images JSON DEFAULT NULL,
    params JSON DEFAULT NULL,
    -- extra opzionali
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 TEAMS
 ========================= */
CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL DEFAULT '',
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attack DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    defense DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    home_factor DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    country VARCHAR(2) DEFAULT NULL,
    city VARCHAR(255) DEFAULT NULL,
    stadium VARCHAR(255) DEFAULT NULL,
    images JSON DEFAULT NULL,
    colors JSON DEFAULT NULL,
    params JSON DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 COMPETITION LEVELS
 ========================= */
CREATE TABLE IF NOT EXISTS competition_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NOT NULL,
    level TINYINT NOT NULL,
    -- 1=top, 2, 3...
    num_teams TINYINT NOT NULL,
    relegation_spots TINYINT NOT NULL DEFAULT 0,
    promotion_spots TINYINT NOT NULL DEFAULT 0,
    params JSON DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comp_level (competition_id, level),
    CONSTRAINT fk_cl_comp FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 SEASONS
 ========================= */
CREATE TABLE IF NOT EXISTS seasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    competition_id INT UNSIGNED NOT NULL,
    season_year INT NOT NULL,
    created DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT NOT NULL DEFAULT 0,
    params JSON DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_competition_season (competition_id, season_year),
    KEY idx_comp_year (competition_id, season_year),

    CONSTRAINT fk_seasons_comp 
        FOREIGN KEY (competition_id) 
        REFERENCES competitions(id) 
        ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 SEASON TEAMS
 ========================= */
CREATE TABLE IF NOT EXISTS season_teams (
    season_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    level TINYINT NOT NULL,
    group_id TINYINT UNSIGNED DEFAULT NULL,
    -- NULL se campionato puro
    PRIMARY KEY (season_id, team_id),
    KEY idx_st_level (season_id, level),
    CONSTRAINT fk_st_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    CONSTRAINT fk_st_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 PLAYERS
 ========================= */
CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    position TINYINT NOT NULL,
    number INT DEFAULT NULL,
    attack DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    defense DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
    country VARCHAR(2) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    images JSON DEFAULT NULL,
    params JSON DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_team (team_id),
    CONSTRAINT fk_players_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 MATCHES
 ========================= */
CREATE TABLE IF NOT EXISTS matches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id INT UNSIGNED NOT NULL,
    level TINYINT NOT NULL,
    group_id INT UNSIGNED DEFAULT NULL,
    -- NULL per eliminazione diretta
    phase TINYINT DEFAULT NULL,
    -- 1=gironi, 2=quarti, 3=semi, 4=finale
    team_home_id INT UNSIGNED NOT NULL,
    team_away_id INT UNSIGNED NOT NULL,
    score_home SMALLINT DEFAULT NULL,
    score_away SMALLINT DEFAULT NULL,
    match_date DATETIME DEFAULT NULL,
    round INT DEFAULT 1,
    status TINYINT DEFAULT 0,
    -- 0=pianificata, 1=giocata
    PRIMARY KEY (id),
    KEY idx_season_level (season_id, level),
    KEY idx_season_phase (season_id, phase),
    KEY idx_season_round (season_id, round),
    KEY idx_group (group_id),
    KEY idx_home (team_home_id),
    KEY idx_away (team_away_id),
    CONSTRAINT fk_matches_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_home FOREIGN KEY (team_home_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_away FOREIGN KEY (team_away_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

/* =========================
 MATCH EVENTS
 ========================= */
CREATE TABLE IF NOT EXISTS match_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    match_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    type TINYINT NOT NULL,
    -- 1=gol, 2=autorete, 3=assist, 4=giallo, 5=rosso
    minute SMALLINT DEFAULT NULL,
    params JSON DEFAULT NULL,
    -- es. {"penalty": true}
    PRIMARY KEY (id),
    KEY idx_match (match_id),
    KEY idx_player (player_id),
    CONSTRAINT fk_me_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    CONSTRAINT fk_me_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;