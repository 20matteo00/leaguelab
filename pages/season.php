<?php
$id = $_GET['id'] ?? null;
if ($id === null)
    return;

$season = DB::table('seasons')->where('id', '=', $id)->first();
$competition = DB::table('competitions')->where('id', '=', $season['competition_id'])->first();

if (empty($season) || empty($competition)) {
    header("Location: index.php?page=competitions&action=view");
    exit;
}
$logo = null;
$images = !empty($competition['images']) ? json_decode($competition['images'], true) : [];
$logo = $images['logo'] ?? null;

$matches_count = DB::table('matches')->where('season_id', '=', $id)->count();
if ($matches_count == 0) {
    Matches::generateMatches($competition['id'], $season['id']);
}

$maxLevel = Seasons::getMaxLevelBySeason($id);

$level = $_GET['level'] ?? 1;
$action = $_GET['action'] ?? 'calendar';

$baseUrl = 'index.php?page=season&id=' . $id;

$round_trip = $competition['round_trip'];
$mode = $competition['modality'];

$matchesNull = Matches::checkNullMatches($id);
$finalPhase = Matches::checkFinalPhase($id);
$statusSeason = Seasons::getSeasonStatus($id);
$isEndedSeason = $statusSeason == 2 ? true : false;

$prevSeason = Seasons::getPreviousSeasonById($id);
$nextSeason = Seasons::getNextSeasonById($id);

$draws = Matches::getDraws($id);

?>

<div class="container-fluid px-5 my-4" id="season">

    <?php if ($matchesNull === 0 && !$isEndedSeason): ?>
        <?php if ($mode == 1): ?>
            <a href="<?= $baseUrl ?>&action=end" class="btn btn-warning fw-bold p-3 w-100">Chiudi Stagione</a>
        <?php elseif ($mode == 2): ?>
            <?php if ($finalPhase && empty($draws)): ?>
                <a href="<?= $baseUrl ?>&action=end" class="btn btn-warning fw-bold p-3 w-100">Chiudi Stagione</a>
            <?php else: ?>
                <?php if (empty($draws)): ?>
                    <a href="<?= $baseUrl ?>&action=nextphase" class="btn btn-warning fw-bold p-3 w-100">Vai al Livello Dopo</a>
                <?php else: ?>
                    <?php
                    $text = '';
                    foreach ($draws as $draw) {
                        $text .= Teams::getTeamNameById($draw['teamA']) . ' VS ' . Teams::getTeamNameById($draw['teamB'])
                            . ' (' . $draw['scoreA'] . '-' . $draw['scoreB'] . ')<br>';
                    }
                    Alert::generateAlert($text, 'danger', 'Squadre a pari Gol', false) ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <!-- ── HEADER ──────────────────────────────────────────────────────────── -->
    <div class="row my-3 g-3 align-items-center">
        <a class="col" href="index.php?page=competition&id=<?= $competition['id'] ?>">
            <div class="row">
                <?php if ($logo): ?>
                    <div class="col-auto">
                        <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($competition['name']) ?>"
                            style="height:80px" class="rounded">
                    </div>
                <?php endif; ?>
                <div class="col">
                    <?php Competitions::renderCompetitions($competition['id'], 'p-2 rounded-pill h1 fw-bold', true) ?>
                </div>
            </div>
        </a>
        <div class="col-auto">
            <span class="fw-semibold h5">Stagione <?= $season['season_year'] ?></span>
        </div>
    </div>
    <hr>
    <?php if ($maxLevel > 1): ?>
        <div class="row g-2 mb-4">
            <?php for ($i = 1; $i <= $maxLevel; $i++): ?>
                <div class="col">
                    <a href="<?= $baseUrl ?>&level=<?= $i ?>&action=<?= $action ?>#content"
                        class="btn btn-primary w-100 p-2 fs-1">
                        <i class="bi bi-<?= $i ?>-circle me-2"></i> Livello
                    </a>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php Seasons::renderMenu($baseUrl, $level, $mode) ?>
    <hr>
    <div id="content">
        <?php
        switch ($action) {
            case 'calendar':
                Calendar::renderCalendar($id, $level, $mode);
                break;
            case 'standings':
                Standings::renderStandingsMenu($baseUrl, $level, $round_trip);
                $subaction = $_GET['subaction'] ?? 'total';
                Standings::renderStandings($id, $level, $subaction, $round_trip);
                break;
            case 'bracket':
                $mode == 1 ? Calendar::renderBracket($id, $level) : Calendar::renderKnockoutBracket($id, $level, $round_trip);
                break;
            case 'trend':
                $rounds = max(DB::table('matches')->select('round')->where('season_id', '=', $season['id'])->where('level', '=', $level)->get())['round'];
                Standings::renderProgressMenu($baseUrl, $level, $rounds);
                $subaction = $_GET['subaction'] ?? $rounds;
                Standings::renderProgress($id, $level, $subaction);
                break;
            case 'markers':
                Markers::renderMarkerStandings($id, $level, 2);
                break;
            case 'stats':
                Stats::renderMenu($baseUrl, $level, $mode);
                $subaction = $_GET['subaction'] ?? 'overview';
                Stats::renderStats($id, $level, $subaction, $mode);
                break;
            case 'nextphase':
                Matches::generateNextPhase($id, $round_trip);
                break;
            case 'end':
                Seasons::setSeasonStatusEnd($id);
                break;
            default:
                break;
        }
        ?>
    </div>

    <?php if ($prevSeason || $nextSeason): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <?php if ($prevSeason): ?>
                <a href="index.php?page=season&id=<?= $prevSeason['id'] ?>" class="btn btn-outline-primary">
                    ← <?= htmlspecialchars($prevSeason['season_year']) ?>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($nextSeason): ?>
                <a href="index.php?page=season&id=<?= $nextSeason['id'] ?>" class="btn btn-outline-primary ms-auto">
                    <?= htmlspecialchars($nextSeason['season_year']) ?> →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>