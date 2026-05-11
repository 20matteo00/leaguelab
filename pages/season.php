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

$urlParams = [
    'id' => $id,
    'level' => $level,
];

$round_trip = $competition['round_trip'];
$mode = $competition['modality'];

$status = Seasons::getSeasonStatus($id);

$effectiveMode = $mode;
if ($mode == 3) {
    $effectiveMode = ($status == 2) ? 2 : 1;
}

if ($mode == 3 && $status == 2) {
    $maxLevel = 1;
}

$matchesNull = Matches::checkNullMatches($id);
$finalPhase = Matches::checkFinalPhase($id);
$isEndedSeason = Seasons::checkSeasonEnd($id);

$prevSeason = Seasons::getPreviousSeasonById($id);
$nextSeason = Seasons::getNextSeasonById($id);

$draws = Matches::getDraws($id);

?>

<div class="container-fluid px-5 my-4" id="season">

    <?php if ($matchesNull === 0 && !$isEndedSeason): ?>
        <?php if ($mode == 1): ?>
            <?= Link::a(
                'season',
                'Chiudi Stagione',
                [
                    'id' => $id,
                    'action' => 'end'
                ],
                [
                    'class' => 'btn btn-warning fw-bold p-3 w-100'
                ],
                'content'
            ) ?>
        <?php elseif ($mode == 2): ?>
            <?php if ($finalPhase && empty($draws)): ?>
                <?= Link::a(
                    'season',
                    'Chiudi Stagione',
                    [
                        'id' => $id,
                        'action' => 'end'
                    ],
                    [
                        'class' => 'btn btn-warning fw-bold p-3 w-100'
                    ],
                    'content'
                ) ?> <?php else: ?>
                <?php if (empty($draws)): ?>
                    <?= Link::a(
                                'season',
                                'Vai alla Fase Successiva',
                                [
                                    'id' => $id,
                                    'action' => 'nextphase'
                                ],
                                [
                                    'class' => 'btn btn-warning fw-bold p-3 w-100'
                                ],
                                'content'
                            ) ?>
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
        <?php elseif ($mode == 3): ?>
            <?php
            if ($status == 1) : ?>
                <?= Link::a(
                    'season',
                    'Vai alla Fase Successiva',
                    [
                        'id' => $id,
                        'action' => 'finalphase'
                    ],
                    [
                        'class' => 'btn btn-warning fw-bold p-3 w-100'
                    ],
                    'content'
                ) ?>
            <?php elseif ($status == 2):  ?>
                <?php if ($finalPhase && empty($draws)): ?>
                    <?= Link::a(
                        'season',
                        'Chiudi Stagione',
                        [
                            'id' => $id,
                            'action' => 'end'
                        ],
                        [
                            'class' => 'btn btn-warning fw-bold p-3 w-100'
                        ],
                        'content'
                    ) ?> <?php else: ?>
                    <?php if (empty($draws)): ?>
                        <?= Link::a(
                                'season',
                                'Vai alla Fase Successiva',
                                [
                                    'id' => $id,
                                    'action' => 'nextphase'
                                ],
                                [
                                    'class' => 'btn btn-warning fw-bold p-3 w-100'
                                ],
                                'content'
                            ) ?>
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
    <?php endif; ?>
    <!-- ── HEADER ──────────────────────────────────────────────────────────── -->
    <div class="row my-3 g-3 align-items-center">
        <a class="col" href="<?= Link::url('competition', ['id' => $competition['id']], '') ?>">
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
                    <?= Link::a(
                        'season',
                        '<i class="bi bi-' . $i . '-circle me-2"></i> Livello',
                        [
                            'id' => $id,
                            'level' => $i,
                            'action' => $action
                        ],
                        [
                            'class' => 'btn btn-outline-primary w-100 p-2 fs-1'
                        ],
                        'content'
                    ) ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php Seasons::renderMenu('season', $urlParams, $effectiveMode) ?>
    <hr>
    <div id="content">
        <?php
        switch ($action) {
            case 'calendar':
                Calendar::renderCalendar($id, $level, $effectiveMode);
                break;
            case 'standings':
                $urlParams['action'] = 'standings';
                Standings::renderStandingsMenu('season', $urlParams, $round_trip);
                $subaction = $_GET['subaction'] ?? 'total';
                Standings::renderStandings($id, $level, $subaction, $round_trip, $effectiveMode);
                break;
            case 'bracket':
                $effectiveMode !== 2 ? Calendar::renderBracket($id, $level, $effectiveMode) : Calendar::renderKnockoutBracket($id, $level, $round_trip);
                break;
            case 'trend':
                $urlParams['action'] = 'trend';
                $rounds = max(Matches::getMatchesByLevelOrGroup($id, $level, $effectiveMode))['round'];
                Standings::renderProgressMenu('season', $urlParams, $rounds);
                $subaction = $_GET['subaction'] ?? $rounds;
                Standings::renderProgress($id, $level, $subaction, $effectiveMode);
                break;
            case 'markers':
                Markers::renderMarkerStandings($id, $level, $effectiveMode, 2);
                break;
            case 'stats':
                $urlParams['action'] = 'stats';
                Stats::renderMenu('season', $urlParams, $effectiveMode);
                $subaction = $_GET['subaction'] ?? 'overview';
                Stats::renderStats($id, $level, $subaction, $effectiveMode);
                break;
            case 'nextphase':
                Matches::generateNextPhase($id, $round_trip);
                break;
            case 'finalphase':
                Matches::generateFinalPhase($id, $round_trip, $effectiveMode);
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
                <?= Link::a(
                    'season',
                    '← ' . htmlspecialchars($prevSeason['season_year']),
                    ['id' => $prevSeason['id']],
                    ['class' => 'btn btn-outline-primary']
                ) ?>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($nextSeason): ?>
                <?= Link::a(
                    'season',
                    htmlspecialchars($nextSeason['season_year']) . ' →',
                    ['id' => $nextSeason['id']],
                    ['class' => 'btn btn-outline-primary ms-auto']
                ) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>