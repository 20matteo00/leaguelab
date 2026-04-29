<?php
$menu = [
    'Crea'      => 'index.php?page=players&action=create',
    'Visualizza' => 'index.php?page=players&action=view',
];
$action = $_GET['action'] ?? 'view';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$allowedSort = ['id', 'team_id', 'name', 'attack', 'defense', 'number', 'position', 'birth_date'];

// ── MULTI-SORT ────────────────────────────────────────────────────────────────
$sorts = Pagination::parseSorts($allowedSort);

// ── PAGINAZIONE ───────────────────────────────────────────────────────────────
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$limitRaw = (int)($_GET['limit'] ?? 20);
$limit    = in_array($limitRaw, Pagination::$pagination) ? $limitRaw : 20;
$offset   = ($page_num - 1) * $limit;

$total = DB::table('players')->count();
$pages = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

// ── QUERY CON MULTI-SORT ──────────────────────────────────────────────────────
$query = DB::table('players');
foreach ($sorts as $pair) {
    $query->orderBy($pair[0], $pair[1]);
}
$players = $query->limit($limit)->offset($offset)->get();

$states    = Field::getStates();
$positions = Field::getPosition();
$teams     = DB::table('teams')->select('id, name')->get();

// Player corrente per edit
$player = null;
if ($action === 'edit' && $id) {
    $player = DB::table('players')->where('id', '=', $id)->first();
    if (!$player) {
        header("Location: index.php?page=players&action=view");
        exit;
    }
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/players/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }
    DB::table('players')->insert([
        'name'       => $_POST['name'],
        'team_id'    => $_POST['team'],
        'country'    => $_POST['country'] ?: null,
        'position'   => $_POST['position'] ?: null,
        'number'     => $_POST['number']   ?: null,
        'attack'     => $_POST['attack'],
        'defense'    => $_POST['defense'],
        'birth_date' => $_POST['birth_date'],
        'images'     => $image_path ? json_encode(['logo' => $image_path]) : null,
        'created'    => date('Y-m-d H:i:s'),
    ]);
    header("Location: index.php?page=players&action=view");
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    $existing      = DB::table('players')->where('id', '=', $id)->first();
    $existingImages = !empty($existing['images']) ? json_decode($existing['images'], true) : [];
    $image_path    = $existingImages['logo'] ?? null;

    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/players/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }
    DB::table('players')->where('id', '=', $id)->update([
        'name'       => $_POST['name'],
        'team_id'    => $_POST['team'],
        'country'    => $_POST['country'] ?: null,
        'position'   => $_POST['position'] ?: null,
        'number'     => $_POST['number']   ?: null,
        'attack'     => $_POST['attack'],
        'defense'    => $_POST['defense'],
        'birth_date' => $_POST['birth_date'],
        'images'     => $image_path ? json_encode(['logo' => $image_path]) : null,
        'created'    => date('Y-m-d H:i:s'),
    ]);
    header("Location: index.php?page=players&action=view");
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $howManyMatches = DB::table('match_events')->where('player_id', '=', $id)->count();
    if ($howManyMatches < 1) {
        DB::table('players')->where('id', '=', $id)->delete();
        header("Location: index.php?page=players&action=view");
        exit;
    }
    DB::table('players')->where('id', '=', $id)->delete();
    header("Location: index.php?page=players&action=view&error=playersInMatches");
    exit;
}

// ── DUPLICATE ─────────────────────────────────────────────────────────────────
if ($action === 'duplicate' && $id) {
    $orig = DB::table('players')->where('id', '=', $id)->first();
    if ($orig) {
        DB::table('players')->insert([
            'name'       => $orig['name'] . ' (copia)',
            'team_id'    => $orig['team_id'],
            'country'    => $orig['country'],
            'position'   => $orig['position'],
            'number'     => $orig['number'],
            'attack'     => $orig['attack'],
            'defense'    => $orig['defense'],
            'birth_date' => $orig['birth_date'],
            'images'     => $orig['images'],
            'created'    => date('Y-m-d H:i:s'),
        ]);
    }
    header("Location: index.php?page=players&action=view");
    exit;
}

Layout::renderSubMenu($menu);

$editImages = [];
if ($player && !empty($player['images'])) {
    $editImages = json_decode($player['images'], true) ?? [];
}

// Parametri sort da propagare nei link interni alla view
$sortsParam   = json_encode($sorts);
?>

<div class="players">
    <div class="container py-4">
        <?php
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            if ($error == 'playersInMatches') Alert::generateAlert('Il giocatore partecipa già a degli incontri e quindi non può essere eliminato', 'danger', 'Giocatore già in incontri');
        }
        ?>
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <!-- ── FORM (create / edit) ──────────────────────────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="text-center fw-bold m-0">
                        <?= $action === 'edit' ? 'Modifica Giocatore' : 'Crea Giocatore' ?>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="index.php?page=players&action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>"
                        enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?= htmlspecialchars($player['name'] ?? '') ?>">
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Squadra *</label>
                                <select name="team" class="form-select" required>
                                    <option value="">-- Seleziona Squadra --</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>"
                                            <?= ($player['team_id'] ?? '') == $team['id'] ? 'selected' : '' ?>>
                                            <?= $team['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Nazione</label>
                                <select name="country" class="form-select">
                                    <option value="">-- Seleziona Stato --</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state['code'] ?>"
                                            <?= ($player['country'] ?? '') == $state['code'] ? 'selected' : '' ?>>
                                            <?= $state['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Data di Nascita</label>
                                <input type="date" name="birth_date" class="form-control"
                                    value="<?= htmlspecialchars($player['birth_date'] ?? '') ?>">
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Numero Maglia</label>
                                <input type="number" name="number" min="1" max="99" class="form-control"
                                    value="<?= htmlspecialchars($player['number'] ?? '') ?>">
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label">Posizione</label>
                                <select name="position" class="form-select">
                                    <option value="">-- Seleziona Posizione --</option>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?= $position['code'] ?>"
                                            <?= ($player['position'] ?? '') == $position['code'] ? 'selected' : '' ?>>
                                            <?= $position['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Attacco</label>
                                <input type="number" step="0.01" name="attack" class="form-control"
                                    value="<?= $player['attack'] ?? 1 ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Difesa</label>
                                <input type="number" step="0.01" name="defense" class="form-control"
                                    value="<?= $player['defense'] ?? 1 ?>">
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Immagine</label>
                            <?php if (!empty($editImages['logo'])): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($editImages['logo']) ?>"
                                        alt="logo attuale" style="height:60px" class="rounded">
                                    <small class="text-muted ms-2">Logo attuale</small>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Salva</button>
                            <a href="index.php?page=players&action=view" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'view'): ?>

            <!-- SELECT quanti per pagina -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <small class="text-muted">
                    <?= $total ?> giocatori totali — pagina <?= $page_num ?> di <?= $pages ?>
                    <?php if (count($sorts) > 0): ?>
                        &nbsp;|&nbsp;
                        Ordine:
                        <?php foreach ($sorts as $i => $pair): ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($pair[0]) ?>
                                <?= $pair[1] === 'ASC' ? '↑' : '↓' ?>
                            </span>
                        <?php endforeach; ?>
                        &nbsp;
                        <a href="index.php?page=players&action=view&limit=<?= $limit ?>"
                            class="text-danger text-decoration-none small" title="Rimuovi tutti gli ordinamenti">✕ reset</a>
                    <?php endif; ?>
                </small>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="page" value="players">
                    <input type="hidden" name="action" value="view">
                    <input type="hidden" name="sorts" value="<?= htmlspecialchars($sortsParam) ?>">
                    <input type="hidden" name="page_num" value="1">
                    <label class="form-label mb-0 small">Mostra</label>
                    <select name="limit" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <?php foreach (Pagination::$pagination as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>>
                                <?= $opt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- ── TABLE ──────────────────────────────────────────────────────────── -->
            <?php
            // Parametri da propagare in ogni link di intestazione colonna
            $linkExtra = [
                'page'     => 'players',
                'action'   => 'view',
                'limit'    => $limit,
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
                            <th><?= Pagination::sortHeader('team_id',    'Squadra',       $sorts, $linkExtra) ?></th>
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
                                    <?php Players::renderPlayers($player['id'], 'p-2 rounded-pill', true) ?>
                                </td>
                                <td><?php Teams::renderTeams($player['team_id'], 'p-2 rounded-pill', true) ?></td>
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

            <!-- PAGINAZIONE — passa anche sorts e limit -->
            <?php Pagination::renderPagination($pages, $page_num, 'players', ['sorts' => $sortsParam, 'limit' => $limit]) ?>
        <?php endif; ?>

    </div>
</div>