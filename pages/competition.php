<?php
$id = $_GET['id'] ?? null;
if ($id === null) return;

$competition = DB::table('competitions')->where('id', '=', $id)->first();
$modality    = Field::getModality();
$states      = Field::getStates();
$lastSeasonEnd = Seasons::getMaxSeasonEndByCompetition($id);

$baseUrl = 'index.php?page=competition&id=' . $id;
$action = $_GET['action'] ?? '';

if ($action == 'continue'){
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
        $mode_label   = array_column($modality, 'name', 'code')[$mode] ?? '—';
        $country_name = array_column(Field::getStates(), 'name', 'code')[$competition['country'] ?? ''] ?? null;

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

        <?php if ($lastSeasonEnd) : ?>
            <a href="<?= $baseUrl ?>&action=continue" class="btn btn-success fw-bold p-3 w-100">Continua Competizione</a>
        <?php endif; ?>

        <!-- ── INFO GENERALI ───────────────────────────────────────────────────── -->
        <div class="row my-3 g-3">

            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header fw-semibold bg-light">📋 Informazioni generali</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal w-50">Modalità</th>
                                    <td class="fw-semibold"><?= htmlspecialchars($mode_label) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Partecipanti</th>
                                    <td class="fw-semibold"><?= $competition['participants'] ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Gare</th>
                                    <td>
                                        <?= $competition['round_trip'] ? '⇄ Andata &amp; Ritorno' : '→ Solo Andata' ?>
                                    </td>
                                </tr>
                                <?php if ($country_name): ?>
                                    <tr>
                                        <th class="text-muted fw-normal">Nazione</th>
                                        <td><?= htmlspecialchars($country_name) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($mode === 3): ?>
                                    <tr>
                                        <th class="text-muted fw-normal">Gironi</th>
                                        <td><?= $competition['num_groups'] ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-normal">Qualificati/girone</th>
                                        <td><?= $competition['qualifiers'] ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($mode === 1 && count($levels) > 0): ?>
                <!-- ── LIVELLI CAMPIONATO ─────────────────────────────────────────── -->
                <div class="col-12 col-md-6 col-lg-8">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header fw-semibold bg-light">🏆 Struttura Campionato</div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Livello</th>
                                        <th class="text-center">Squadre</th>
                                        <th class="text-center">Promozioni ↑</th>
                                        <th class="text-center">Retrocessioni ↓</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($levels as $i => $lvl): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary me-1"><?= $lvl['level'] ?></span>
                                                <?php
                                                echo htmlspecialchars('Livello ' . $lvl['level']);
                                                ?>
                                            </td>
                                            <td class="text-center fw-semibold"><?= $lvl['num_teams'] ?></td>
                                            <td class="text-center">
                                                <?php if ($lvl['promotion_spots'] == 0): ?>
                                                    <span class="text-muted small">—</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?= $lvl['promotion_spots'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($lvl['relegation_spots'] == 0): ?>
                                                    <span class="text-muted small">—</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><?= $lvl['relegation_spots'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($mode === 2): ?>
                <!-- ── INFO ELIMINAZIONE ─────────────────────────────────────────── -->
                <div class="col-12 col-md-6 col-lg-8">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header fw-semibold bg-light">🔀 Tabellone</div>
                        <div class="card-body">
                            <?php $rounds = (int)log($competition['participants'], 2) ?>
                            <p class="text-muted small mb-2">Struttura del tabellone:</p>
                            <?php for ($r = $rounds; $r >= 1; $r--): ?>
                                <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                                    <span class="badge bg-secondary"><?= (int)pow(2, $r - 1) ?> partite</span>
                                    <span><?= Competitions::$round_names[$r - 1] ?? 'Turno ' . $r ?></span>
                                </li>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($mode === 3): ?>
                <!-- ── INFO GIRONI ────────────────────────────────────────────────── -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header fw-semibold bg-light">⚽ Struttura Gironi</div>
                        <div class="card-body">
                            <?php
                            $ng  = (int)$competition['num_groups'];
                            $tpg = $ng > 0 ? (int)floor((int)$competition['participants'] / $ng) : 0;
                            $q   = (int)$competition['qualifiers'];
                            $tot_qualifiers = $ng * $q;
                            $bracket_size   = 1;
                            while ($bracket_size < $tot_qualifiers) $bracket_size *= 2;
                            ?>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted fw-normal w-50">Gironi</th>
                                        <td class="fw-semibold"><?= $ng ?> da <?= $tpg ?> Squadre</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-normal">Qualificati</th>
                                        <td class="fw-semibold"><?= $q ?> per Girone</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-normal">Tot. qualificati</th>
                                        <td class="fw-semibold"><?= $tot_qualifiers ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-normal">Fase finale</th>
                                        <td class="fw-semibold">Tabellone da <?= $bracket_size ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header fw-semibold bg-light">🔀 Tabellone</div>
                        <div class="card-body">
                            <?php $rounds = (int)log($bracket_size, 2) ?>
                            <p class="text-muted small mb-2">Struttura del tabellone:</p>
                            <?php for ($r = $rounds; $r >= 1; $r--): ?>
                                <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                                    <span class="badge bg-secondary"><?= (int)pow(2, $r - 1) ?> partite</span>
                                    <span><?= Competitions::$round_names[$r - 1] ?? 'Turno ' . $r ?></span>
                                </li>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- ── STAGIONI ────────────────────────────────────────────────────────── -->
        <div class="row my-3 g-3">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-light">
                        <span class="fw-semibold">📅 Stagioni</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($seasons) > 0): ?>
                            <table class="table table-hover align-middle mb-0 text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Anno</th>
                                        <th>Stato</th>
                                        <th>Squadre</th>
                                        <th>Creata il</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seasons as $season): ?>
                                        <?php
                                        $team_count = DB::table('season_teams')
                                            ->where('season_id', '=', $season['id'])
                                            ->count();
                                        $st  = (int)$season['status'];
                                        ?>
                                        <tr>
                                            <td class="text-muted small"><?= $season['id'] ?></td>
                                            <td class="fw-semibold"><?= $season['season_year'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= Seasons::$status[$st]['badge'] ?>">
                                                    <?= Seasons::$status[$st]['label'] ?>
                                                </span>
                                            </td>
                                            <td><?= $team_count ?></td>
                                            <td class="text-muted small">
                                                <?= date('d/m/Y', strtotime($season['created'])) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <a href="index.php?page=season&id=<?= $season['id'] ?>"
                                                        class="btn btn-sm btn-outline-success" title="Visualizza">👁️</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                Nessuna stagione ancora creata.
                                <a href="index.php?page=competitions&action=configure&id=<?= $id ?>">Creane una</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <?= Alert::generateAlert('Nessuna competizione trovata con id= ' . $id, 'danger', 'Competizione non trovata', false) ?>
    <?php endif; ?>
</div>