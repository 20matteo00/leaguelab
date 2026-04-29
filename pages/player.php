<?php
$id = $_GET['id'] ?? null;
if ($id === null) return;
$player = DB::table('players')->where('id', '=', $id)->first();
?>
<div class="container my-4">
    <?php
    if ($player) {
        $logo = null;
        $positions = Field::getPosition();

        if (!empty($player['images'])) {
            $images = json_decode($player['images'], true);
            $logo = $images['logo'] ?? null;
        }
    ?>
        <div class="row my-3 g-3">
            <?php if ($logo): ?>
                <div class="col-auto">
                    <img src="<?= $logo ?>" alt="<?= $player['name'] ?>">
                </div>
            <?php endif; ?>
            <div class="col">
                <div class="d-flex justify-content-start align-items-center h-100">
                    <?php Players::renderPlayers($player['id'], 'p-2 rounded-pill h1 text-center fw-bold me-2', true) ?>
                </div>
            </div>
            <div class="col-auto">
                <a class="d-flex justify-content-center align-items-center h-100" href="index.php?page=team&id=<?= $player['team_id'] ?>">
                    <?php Teams::renderTeams($player['team_id'], 'p-2 rounded-pill h1 w-100 text-center fw-bold mx-2', true, true) ?>
                </a>
            </div>
        </div>
        <hr>
        <div class="row my-3 g-3">
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Attacco</div>
                    <div class="fw-bold fs-4 text-danger">
                        🔥 <?= number_format($player['attack'], 2) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Difesa</div>
                    <div class="fw-bold fs-4 text-primary">
                        🛡️ <?= number_format($player['defense'], 2) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Maglia</div>
                    <div class="fw-bold fs-4 text-dark">
                        👕 <?= number_format($player['number'], 0) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Età</div>
                    <div class="fw-bold fs-4 text-dark">
                        🎂 <?= Players::getEta($player['birth_date']) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Posizione</div>
                    <div class="fw-bold fs-4 text-dark">
                        ⚽ <?= array_column($positions, 'name', 'code')[$player['position']] ?? '' ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
    } else {
        Alert::generateAlert('Nessun giocatore trovato con id= ' . $id, 'danger', 'Giocatore non trovato', false);
    }
    ?>
</div>