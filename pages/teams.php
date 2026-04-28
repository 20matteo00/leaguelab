<?php
$menu = [
    'Crea'       => 'index.php?page=teams&action=create',
    'Visualizza' => 'index.php?page=teams&action=view',
];
$action = $_GET['action'] ?? 'view';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$allowedSort = ['id', 'name', 'attack', 'defense', 'home_factor'];

// ── MULTI-SORT ────────────────────────────────────────────────────────────────
$sorts = Pagination::parseSorts($allowedSort);

// ── PAGINAZIONE ───────────────────────────────────────────────────────────────
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$limitRaw = (int)($_GET['limit'] ?? 20);
$limit    = in_array($limitRaw, Pagination::$pagination) ? $limitRaw : 20;
$offset   = ($page_num - 1) * $limit;

$total = DB::table('teams')->count();
$pages = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

// ── QUERY CON MULTI-SORT ──────────────────────────────────────────────────────
$query = DB::table('teams');
foreach ($sorts as $pair) {
    $query->orderBy($pair[0], $pair[1]);
}
$teams = $query->limit($limit)->offset($offset)->get();

$states     = Field::getStates();
$sortsParam = json_encode($sorts);

// Team corrente per edit
$team = null;
if ($action === 'edit' && $id) {
    $team = DB::table('teams')->where('id', '=', $id)->first();
    if (!$team) {
        header("Location: index.php?page=teams&action=view");
        exit;
    }
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $colors = [
        'background' => $_POST['background'] ?? '#ffffff',
        'text'       => $_POST['text']       ?? '#000000',
        'border'     => $_POST['border']     ?? '#ffffff',
    ];
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/teams/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }
    DB::table('teams')->insert([
        'name'        => $_POST['name'],
        'country'     => $_POST['country']     ?: null,
        'city'        => $_POST['city']        ?: null,
        'stadium'     => $_POST['stadium']     ?: null,
        'attack'      => $_POST['attack'],
        'defense'     => $_POST['defense'],
        'home_factor' => $_POST['home_factor'],
        'colors'      => json_encode($colors),
        'images'      => $image_path ? json_encode(['logo' => $image_path]) : null,
        'created'     => date('Y-m-d H:i:s'),
    ]);
    header("Location: index.php?page=teams&action=view");
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    $colors = [
        'background' => $_POST['background'] ?? '#ffffff',
        'text'       => $_POST['text']       ?? '#000000',
        'border'     => $_POST['border']     ?? '#ffffff',
    ];
    $existing       = DB::table('teams')->where('id', '=', $id)->first();
    $existingImages = !empty($existing['images']) ? json_decode($existing['images'], true) : [];
    $image_path     = $existingImages['logo'] ?? null;

    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/teams/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }
    DB::table('teams')->where('id', '=', $id)->update([
        'name'        => $_POST['name'],
        'country'     => $_POST['country']     ?: null,
        'city'        => $_POST['city']        ?: null,
        'stadium'     => $_POST['stadium']     ?: null,
        'attack'      => $_POST['attack'],
        'defense'     => $_POST['defense'],
        'home_factor' => $_POST['home_factor'],
        'colors'      => json_encode($colors),
        'images'      => $image_path ? json_encode(['logo' => $image_path]) : null,
    ]);
    header("Location: index.php?page=teams&action=view");
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    $howManySeasons = DB::table('season_teams')->where('team_id', '=', $id)->count();
    if ($howManySeasons < 1) {
        DB::table('teams')->where('id', '=', $id)->delete();
        header("Location: index.php?page=teams&action=view");
        exit;
    }
    header("Location: index.php?page=teams&action=view&error=teamInSeasons");
    exit;
}

// ── DUPLICATE ─────────────────────────────────────────────────────────────────
if ($action === 'duplicate' && $id) {
    $orig = DB::table('teams')->where('id', '=', $id)->first();
    if ($orig) {
        DB::table('teams')->insert([
            'name'        => $orig['name'] . ' (copia)',
            'country'     => $orig['country'],
            'city'        => $orig['city'],
            'stadium'     => $orig['stadium'],
            'attack'      => $orig['attack'],
            'defense'     => $orig['defense'],
            'home_factor' => $orig['home_factor'],
            'colors'      => $orig['colors'],
            'images'      => $orig['images'],
            'created'     => date('Y-m-d H:i:s'),
        ]);
    }
    header("Location: index.php?page=teams&action=view");
    exit;
}

Layout::renderSubMenu($menu);

$editColors = ['background' => '#ffffff', 'text' => '#000000', 'border' => '#ffffff'];
if ($team && !empty($team['colors'])) {
    $decoded = json_decode($team['colors'], true);
    if ($decoded) $editColors = array_merge($editColors, $decoded);
}
$editImages = [];
if ($team && !empty($team['images'])) {
    $editImages = json_decode($team['images'], true) ?? [];
}

$linkExtra = [
    'page'     => 'teams',
    'action'   => 'view',
    'limit'    => $limit,
    'page_num' => 1,
];
?>

<div class="teams">
    <div class="container py-4">
        <?php
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            if ($error == 'teamInSeasons') Alert::generateAlert('La squadra partecipa già a delle stagioni e quindi non può essere eliminata', 'danger', 'Squadra già in stagioni');
        }
        ?>
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <!-- ── FORM (create / edit) ──────────────────────────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="text-center fw-bold m-0">
                        <?= $action === 'edit' ? 'Modifica Squadra' : 'Crea Squadra' ?>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="index.php?page=teams&action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>"
                        enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?= htmlspecialchars($team['name'] ?? '') ?>">
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label">Nazione</label>
                                <select name="country" class="form-select">
                                    <option value="">-- Seleziona Stato --</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state['code'] ?>"
                                            <?= ($team['country'] ?? '') === $state['code'] ? 'selected' : '' ?>>
                                            <?= $state['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label">Città</label>
                                <input type="text" name="city" class="form-control"
                                    value="<?= htmlspecialchars($team['city'] ?? '') ?>">
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label">Stadio</label>
                                <input type="text" name="stadium" class="form-control"
                                    value="<?= htmlspecialchars($team['stadium'] ?? '') ?>">
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Attacco</label>
                                <input type="number" step="0.01" name="attack" class="form-control"
                                    value="<?= $team['attack'] ?? 1 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Difesa</label>
                                <input type="number" step="0.01" name="defense" class="form-control"
                                    value="<?= $team['defense'] ?? 1 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Fattore casa</label>
                                <input type="number" step="0.01" name="home_factor" class="form-control"
                                    value="<?= $team['home_factor'] ?? 1 ?>">
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Colore Sfondo</label>
                                <input type="color" name="background" class="form-control"
                                    value="<?= $editColors['background'] ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Colore Testo</label>
                                <input type="color" name="text" class="form-control"
                                    value="<?= $editColors['text'] ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Colore Bordo</label>
                                <input type="color" name="border" class="form-control"
                                    value="<?= $editColors['border'] ?>">
                            </div>
                        </div>
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
                            <a href="index.php?page=teams&action=view" class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'view'): ?>

            <!-- SELECT quanti per pagina -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <small class="text-muted">
                    <?= $total ?> squadre totali — pagina <?= $page_num ?> di <?= $pages ?>
                    <?php if (count($sorts) > 0): ?>
                        &nbsp;|&nbsp;
                        Ordine:
                        <?php foreach ($sorts as $pair): ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($pair[0]) ?>
                                <?= $pair[1] === 'ASC' ? '↑' : '↓' ?>
                            </span>
                        <?php endforeach; ?>
                        &nbsp;
                        <a href="index.php?page=teams&action=view&limit=<?= $limit ?>"
                            class="text-danger text-decoration-none small" title="Rimuovi tutti gli ordinamenti">✕ reset</a>
                    <?php endif; ?>
                </small>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="page" value="teams">
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
            <div class="table-responsive">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <th><?= Pagination::sortHeader('id',          '#',            $sorts, $linkExtra) ?></th>
                            <th>Logo</th>
                            <th><?= Pagination::sortHeader('name',        'Nome',         $sorts, $linkExtra) ?></th>
                            <th><?= Pagination::sortHeader('attack',      'Attacco',      $sorts, $linkExtra) ?></th>
                            <th><?= Pagination::sortHeader('defense',     'Difesa',       $sorts, $linkExtra) ?></th>
                            <th><?= Pagination::sortHeader('home_factor', 'Fattore Casa', $sorts, $linkExtra) ?></th>
                            <th>Informazioni</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                            <?php
                            $data  = !empty($team['images']) ? json_decode($team['images'], true) : [];
                            $image = $data['logo'] ?? 'images/empty.png';
                            ?>
                            <tr>
                                <td><?= $team['id'] ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars($image) ?>"
                                        alt="<?= htmlspecialchars($team['name']) ?>"
                                        class="img-fluid rounded img-sm">
                                </td>
                                <td class="fw-semibold">
                                    <?= Teams::renderTeams($team['id'], 'p-2 rounded-pill', true) ?>
                                </td>
                                <td><span class="badge bg-danger"><?= $team['attack'] ?></span></td>
                                <td><span class="badge bg-primary"><?= $team['defense'] ?></span></td>
                                <td><span class="badge bg-warning text-dark"><?= $team['home_factor'] ?></span></td>
                                <td class="text-start">
                                    <?php if (!empty($team['city'])): ?>
                                        <div><small class="text-muted">Città:</small> <?= htmlspecialchars($team['city']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($team['stadium'])): ?>
                                        <div><small class="text-muted">Stadio:</small> <?= htmlspecialchars($team['stadium']) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($team['city']) && empty($team['stadium'])): ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="index.php?page=team&id=<?= $team['id'] ?>"
                                            class="btn btn-sm btn-outline-success" title="Visualizza">👁️</a>
                                        <a href="index.php?page=teams&action=edit&id=<?= $team['id'] ?>"
                                            class="btn btn-sm btn-outline-primary" title="Modifica">✏️</a>
                                        <a href="index.php?page=teams&action=duplicate&id=<?= $team['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary" title="Duplica">📋</a>
                                        <a href="index.php?page=teams&action=delete&id=<?= $team['id'] ?>"
                                            class="btn btn-sm btn-outline-danger" title="Elimina"
                                            onclick="return confirm('Eliminare <?= htmlspecialchars(addslashes($team['name'])) ?>?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINAZIONE -->
            <?= Pagination::renderPagination($pages, $page_num, 'teams', ['sorts' => $sortsParam, 'limit' => $limit]) ?>
        <?php endif; ?>

    </div>
</div>