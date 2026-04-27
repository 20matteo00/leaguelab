<?php
$id = $_GET['id'] ?? null;
if ($id === null) return;
$team = DB::table('teams')->where('id', '=', $id)->first();
?>
<div class="container my-4">
    <?php
    if ($team) {
        $logo = null;

        if (!empty($team['images'])) {
            $images = json_decode($team['images'], true);
            $logo = $images['logo'] ?? null;
        }
    ?>
        <div class="row my-3 g-3">
            <div class="col-auto">
                <img src="<?= $logo ?>" alt="<?= $team['name'] ?>">
            </div>
            <div class="col">
                <div class="d-flex justify-content-center align-items-center h-100">
                    <?= Teams::renderTeams($team['id'], 'p-2 rounded-pill h1 w-100 text-center fw-bold me-2', true) ?>
                </div>
            </div>
        </div>
        <hr>
        <div class="row my-3 g-3">
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Attacco</div>
                    <div class="fw-bold fs-4 text-danger">
                        🔥 <?= number_format($team['attack'], 2) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Difesa</div>
                    <div class="fw-bold fs-4 text-primary">
                        🛡️ <?= number_format($team['defense'], 2) ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Fattore casa</div>
                    <div class="fw-bold fs-4 text-success">
                        🏠 <?= number_format($team['home_factor'], 2) ?> 
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Città</div>
                    <div class="fw-bold fs-5">
                        📍 <?= $team['city'] ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded shadow-sm text-center h-100">
                    <div class="text-muted small">Stadio</div>
                    <div class="fw-bold fs-5">
                        🏟️ <?= $team['stadium'] ?>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <?php
        $positions = Field::getPosition();

        $allowedSort = ['id', 'team_id', 'name', 'attack', 'defense', 'number', 'position', 'birth_date'];

        // ── MULTI-SORT ────────────────────────────────────────────────────────────────
        $sorts = Pagination::parseSorts($allowedSort);

        $query = DB::table('players')->where('team_id', '=', $team['id']);
        foreach ($sorts as $pair) {
            $query->orderBy($pair[0], $pair[1]);
        }
        $players = $query->get();
        // Parametri sort da propagare nei link interni alla view
        $sortsParam   = json_encode($sorts);
        // Parametri da propagare in ogni link di intestazione colonna
        $linkExtra = [
            'page'     => 'team',
            'id'   => $team['id'],
            'page_num' => 1,
        ];
        ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle shadow-sm text-center">
                <thead class="table-dark">
                    <tr>
                        <th><?= Pagination::sortHeader('id',         '#',             $sorts, $linkExtra) ?></th>
                        <th>Logo</th>
                        <th><?= Pagination::sortHeader('name',       'Nome',          $sorts, $linkExtra) ?></th>
                        <th><?= Pagination::sortHeader('position',   'Posizione',     $sorts, $linkExtra) ?></th>
                        <th><?= Pagination::sortHeader('number',     'Numero Maglia', $sorts, $linkExtra) ?></th>
                        <th><?= Pagination::sortHeader('birth_date', 'Età',           $sorts, $linkExtra) ?></th>
                        <th><?= Pagination::sortHeader('attack',     'Attacco',       $sorts, $linkExtra) ?></th>
                        <th><?= Pagination::sortHeader('defense',    'Difesa',        $sorts, $linkExtra) ?></th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                        <?php
                        $data  = !empty($player['images']) ? json_decode($player['images'], true) : [];
                        $image = $data['logo'] ?? 'images/empty.png';
                        ?>
                        <tr>
                            <td><?= $player['id'] ?></td>
                            <td>
                                <img src="<?= htmlspecialchars($image) ?>"
                                    alt="<?= htmlspecialchars($player['name']) ?>"
                                    class="img-fluid rounded img-sm">
                            </td>
                            <td class="fw-semibold">
                                <?= Players::renderPlayers($player['id'], 'p-2 rounded-pill', true) ?>
                            </td>
                            <td><?= array_column($positions, 'name', 'code')[$player['position']] ?? '' ?></td>
                            <td><?= $player['number'] ?></td>
                            <td><?= Players::getEta($player['birth_date']) ?></td>
                            <td><span class="badge bg-danger"><?= $player['attack'] ?></span></td>
                            <td><span class="badge bg-primary"><?= $player['defense'] ?></span></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="index.php?page=player&id=<?= $player['id'] ?>"
                                            class="btn btn-sm btn-outline-success" title="Visualizza">👁️</a>
                                    <a href="index.php?page=players&action=edit&id=<?= $player['id'] ?>"
                                        class="btn btn-sm btn-outline-primary" title="Modifica">✏️</a>
                                    <a href="index.php?page=players&action=duplicate&id=<?= $player['id'] ?>"
                                        class="btn btn-sm btn-outline-secondary" title="Duplica">📋</a>
                                    <a href="index.php?page=players&action=delete&id=<?= $player['id'] ?>"
                                        class="btn btn-sm btn-outline-danger" title="Elimina"
                                        onclick="return confirm('Eliminare <?= htmlspecialchars(addslashes($player['name'])) ?>?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
    } else {
        Alert::generateAlert('Nessuna squadra trovata con id= ' . $id, 'danger', 'Squadra non trovata', false);
    }
    ?>
</div>