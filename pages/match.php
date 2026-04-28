<?php
$id = $_GET['id'] ?? null;
if ($id === null) return;

$match = DB::table('matches')->where('id', '=', $id)->first();
$season = DB::table('seasons')->where('id', '=', $match['season_id'])->first();
$competition = DB::table('competitions')->where('id', '=', $season['competition_id'])->first();

if (empty($match) || empty($season) || empty($competition)) {
    header("Location: index.php?page=competitions&action=view");
    exit;
}
$images = !empty($competition['images']) ? json_decode($competition['images'], true) : [];
$logo   = $images['logo'] ?? null;
?>

<div class="container my-4" id="match">

    <!-- MATCH INFO -->
    <div class="row g-3 mb-4">
        <a class="col-12 col-md-4" href="index.php?page=competition&id=<?= $competition['id'] ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <div class="fw-bold mb-1">Competizione</div>
                    <?= Competitions::renderCompetitions($competition['id'], 'fw-semibold') ?>
                </div>
            </div>
        </a>
        <a class="col-12 col-md-4" href="index.php?page=season&id=<?= $season['id'] ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <div class="fw-bold mb-1">Stagione</div>
                    <span class="text-muted"><?= $season['season_year'] ?></span>
                </div>
            </div>
        </a>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <div class="fw-bold mb-1">Stato</div>
                    <span class="badge bg-<?= Seasons::$status[$season['status']]['badge'] ?>">
                        <?= Seasons::$status[$season['status']]['label'] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <!-- SCOREBOARD -->
    <div class="row align-items-center justify-content-center my-4 g-2">
        <div class="col-5 text-end">
            <?= Teams::renderTeams($match['team_home_id'], 'h5 fw-semibold px-3 py-2 rounded-pill d-inline-block') ?>
        </div>
        <div class="col-2 text-center">
            <div class="display-6 fw-bold">
                <?= $match['score_home'] ?? '-' ?>
                <span class="text-muted">:</span>
                <?= $match['score_away'] ?? '-' ?>
            </div>
        </div>
        <div class="col-5 text-start">
            <?= Teams::renderTeams($match['team_away_id'], 'h5 fw-semibold px-3 py-2 rounded-pill d-inline-block') ?>
        </div>
        <div class="text-center">Incontro <span class="badge bg-<?= Matches::$status[$match['status']]['badge'] ?>"><?= Matches::$status[$match['status']]['label'] ?></span></div>
    </div>

    <hr>

    <!-- EVENTI MATCH -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">
            Cronaca match
        </div>
        <div class="card-body p-0">
            <?php
            $events = DB::table('match_events')
                ->where('match_id', '=', $match['id'])
                ->whereIn('type', [1, 3])
                ->orderBy('minute')
                ->get();
            $homeScore = 0;
            $awayScore = 0;
            ?>

            <?php if (empty($events)): ?>
                <div class="text-muted text-center py-3">Nessun evento registrato</div>
            <?php else: ?>

                <?php foreach ($events as $event): ?>
                    <?php
                    $player = DB::table('players')->where('id', '=', $event['player_id'])->first();
                    $teamId = $player['team_id'];
                    $isHome = $teamId == $match['team_home_id'];
                    if ($isHome) $homeScore++;
                    else $awayScore++;

                    $assist = DB::table('match_events')
                        ->where('match_id', '=', $match['id'])
                        ->where('minute', '=', $event['minute'])
                        ->where('type', '=', 2)
                        ->first();
                    $assistPlayer = $assist
                        ? DB::table('players')->where('id', '=', $assist['player_id'])->first()
                        : null;
                    ?>

                    <div class="row align-items-center g-0 py-2 px-2 border-bottom">

                        <?php if ($isHome): ?>
                            <!-- GOL CASA -->
                            <div class="col-5 d-flex align-items-center gap-2">
                                <span class="text-muted small text-nowrap"><?= $event['minute'] ?>'</span>
                                <span>⚽</span>
                                <div>
                                    <?= Players::renderPlayers($event['player_id']) ?>
                                    <?php if ($event['type'] == 3): ?>
                                        <span class="text-muted small">(R.)</span>
                                    <?php endif; ?>
                                    <?= Teams::renderTeams($teamId, 'px-2 rounded-pill d-inline-block small') ?>
                                    <?php if ($assistPlayer): ?>
                                        <div class="text-muted small">(assist <?= htmlspecialchars($assistPlayer['name']) ?>)</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-2 text-center fw-bold">
                                <span class="badge bg-success"><?= $homeScore ?></span>
                                <span class="text-muted">-</span>
                                <span class="badge bg-secondary"><?= $awayScore ?></span>
                            </div>
                            <div class="col-5"></div>

                        <?php else: ?>
                            <!-- GOL TRASFERTA -->
                            <div class="col-5"></div>
                            <div class="col-2 text-center fw-bold">
                                <span class="badge bg-secondary"><?= $homeScore ?></span>
                                <span class="text-muted">-</span>
                                <span class="badge bg-success"><?= $awayScore ?></span>
                            </div>
                            <div class="col-5 d-flex align-items-center justify-content-end gap-2">
                                <div class="text-end">
                                    <?= Teams::renderTeams($teamId, 'px-2 rounded-pill d-inline-block small') ?>
                                    <?= Players::renderPlayers($event['player_id']) ?>
                                    <?php if ($event['type'] == 3): ?>
                                        <span class="text-muted small">(R.)</span>
                                    <?php endif; ?>
                                    <?php if ($assistPlayer): ?>
                                        <div class="text-muted small">(assist <?= htmlspecialchars($assistPlayer['name']) ?>)</div>
                                    <?php endif; ?>
                                </div>
                                <span>⚽</span>
                                <span class="text-muted small text-nowrap"><?= $event['minute'] ?>'</span>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>