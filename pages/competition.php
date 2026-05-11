<?php
$id = $_GET['id'] ?? null;
if ($id === null)
    return;

$competition = DB::table('competitions')->where('id', '=', $id)->first();
$lastSeasonEnd = Seasons::getMaxSeasonEndByCompetition($id);

$action = $_GET['action'] ?? 'overview';
$level = $_GET['level'] ?? 1;

$baseUrl = 'index.php?page=competition&id=' . $id . '&level=' . $level;

$urlParams = [
    'id' => $id,
    'level' => $level
];

if ($action == 'continue') {
    Seasons::seasonContinue($id);
    header("Location: index.php?page=competition&id=" . $id);
    exit;
}

$actionHaveLevels = in_array($action, ['all_time_standings', 'hall_of_fame', 'all_time_markers']);

?>

<div class="container-fluid px-5 my-4">
    <?php if ($competition): ?>

        <?php
        $logo = null;
        $images = !empty($competition['images']) ? json_decode($competition['images'], true) : [];
        $logo = $images['logo'] ?? null;
        $mode = (int) $competition['modality'];

        // Livelli (solo campionato)
        $levels = DB::table('competition_levels')
            ->where('competition_id', '=', $id)
            ->orderBy('level', 'ASC')
            ->get() ?? [];


        // Stagioni
        $seasons = DB::table('seasons')
            ->where('competition_id', '=', $id)
            ->orderBy('season_year', 'DESC')
            ->get();

        $num_seasons = count($seasons);
        $minGoalMarkersVisible = 0;
        if ($mode == 1) $minGoalMarkersVisible = $num_seasons * 10;
        elseif ($mode == 2) $minGoalMarkersVisible = $num_seasons * 3;
        ?>

        <!-- ── HEADER ──────────────────────────────────────────────────────────── -->
        <div class="row my-3 g-3 align-items-center">
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
        <hr>
        <?php $maxLevel = (count($levels)) ?>
        <?php if ($maxLevel > 1 && $actionHaveLevels): ?>
            <div class="row g-2 mb-4">
                <?php for ($i = 1; $i <= $maxLevel; $i++): ?>
                    <div class="col">
                        <?= Link::a(
                            'competition',
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
        <?php Competitions::renderMenu('competition', $urlParams, $mode) ?>


        <div id="content">
            <?php
            switch ($action) {
                case 'overview':
                    if ($lastSeasonEnd): ?>
                        <?= Link::a(
                            'competition',
                            'Continua Competizione',
                            [
                                'id' => $id,
                                'action' => 'continue'
                            ],
                            [
                                'class' => 'btn btn-success fw-bold p-3 w-100'
                            ],
                            'content'
                        ) ?>
            <?php endif;
                    Competitions::rendeOverview($competition, $levels, $seasons, $id);
                    break;

                case 'all_time_standings':
                    Standings::renderAllTimeStandings($id, $level);
                    break;

                case 'hall_of_fame':
                    $mode == 1 ? Standings::renderHallOfFame($id, $level) : Standings::renderHallOfFameKnockout($id, $level);
                    break;

                case 'all_time_markers':
                    Markers::renderAllTimeMarkers($id, $level, $minGoalMarkersVisible);
                    break;

                case 'head_to_head':
                    Matches::renderMatchesByTeamsAndComp($id, $mode);
                    break;

                case 'head_to_head_advanced':
                    Matches::renderMatchesByTeamsAndCompAdvanced($id, $mode);
                    break;

                case 'stats':
                    $urlParams['action'] = 'stats';
                    Stats::renderGlobalMenu('competition', $urlParams);
                    $subaction = $_GET['subaction'] ?? 'overview';
                    Stats::renderGlobalStats($id, $subaction, $mode);
                default:
                    break;
            }
            ?>
        </div>

    <?php else: ?>
        <?php Alert::generateAlert('Nessuna competizione trovata con id= ' . $id, 'danger', 'Competizione non trovata', false) ?>
    <?php endif; ?>
</div>