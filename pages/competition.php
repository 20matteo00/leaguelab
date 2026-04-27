<?php
$id = $_GET['id'] ?? null;
if ($id === null) return;

$competition = DB::table('competitions')->where('id', '=', $id)->first();
$lastSeasonEnd = Seasons::getMaxSeasonEndByCompetition($id);

$baseUrl = 'index.php?page=competition&id=' . $id;
$action = $_GET['action'] ?? 'overview';

if ($action == 'continue') {
    Seasons::seasonContinue($id);
    header("Location: index.php?page=competition&id=" . $id);
    exit;
}
?>

<div class="container my-4">
    <?php if ($competition): ?>

        <?php
        $logo         = null;
        $images       = !empty($competition['images']) ? json_decode($competition['images'], true) : [];
        $logo         = $images['logo'] ?? null;
        $mode         = (int)$competition['modality'];

        // Livelli (solo campionato)
        $levels = [];
        if ($mode === 1) {
            $levels = DB::table('competition_levels')
                ->where('competition_id', '=', $id)
                ->orderBy('level', 'ASC')
                ->get();
        }

        // Stagioni
        $seasons = DB::table('seasons')
            ->where('competition_id', '=', $id)
            ->orderBy('season_year', 'DESC')
            ->get();

        ?>

        <!-- ── HEADER ──────────────────────────────────────────────────────────── -->
        <div class="row my-3 g-3 align-items-center">
            <?php if ($logo): ?>
                <div class="col-auto">
                    <img src="<?= htmlspecialchars($logo) ?>"
                        alt="<?= htmlspecialchars($competition['name']) ?>"
                        style="height:80px" class="rounded">
                </div>
            <?php endif; ?>
            <div class="col">
                <?= Competitions::renderCompetitions($competition['id'], 'p-2 rounded-pill h1 fw-bold', true) ?>
            </div>
        </div>
        <hr>

        <?= Competitions::renderMenu($baseUrl, $mode) ?>


        <div id="content">
            <?php
            switch ($action) {
                case 'overview':
                    if ($lastSeasonEnd) : ?>
                        <a href="<?= $baseUrl ?>&action=continue" class="btn btn-success fw-bold p-3 w-100">Continua Competizione</a>
            <?php endif;
                    Competitions::rendeOverview($competition, $levels, $seasons, $id);
                    break;

                case 'all_time_standings':
                    Standings::renderAllTimeStandings($id);
                    break;

                case 'hall_of_fame':
                    Standings::renderHallOfFame($id);
                    break;
                default:
                    break;
            }
            ?>
        </div>

    <?php else: ?>
        <?= Alert::generateAlert('Nessuna competizione trovata con id= ' . $id, 'danger', 'Competizione non trovata', false) ?>
    <?php endif; ?>
</div>