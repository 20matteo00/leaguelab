<?php
$menu = [
    'Crea'       => 'index.php?page=competitions&action=create',
    'Visualizza' => 'index.php?page=competitions&action=view',
    /* 'Importa'   => 'index.php?page=competitions&action=import', */
];
$action = $_GET['action'] ?? 'view';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$allowedSort = ['id', 'name', 'modality', 'participants'];

// ── MULTI-SORT ────────────────────────────────────────────────────────────────
$sorts = Pagination::parseSorts($allowedSort);

// ── PAGINAZIONE ───────────────────────────────────────────────────────────────
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$limitRaw = (int)($_GET['limit'] ?? 20);
$limit    = in_array($limitRaw, Pagination::$pagination) ? $limitRaw : 20;
$offset   = ($page_num - 1) * $limit;

$total = DB::table('competitions')->count();
$pages = $limit > 0 && $total > 0 ? (int)ceil($total / $limit) : 1;

// ── QUERY CON MULTI-SORT ──────────────────────────────────────────────────────
$query = DB::table('competitions');
foreach ($sorts as $pair) {
    $query->orderBy($pair[0], $pair[1]);
}
$competitions = $query->limit($limit)->offset($offset)->get();

$states     = Field::getStates();
$modality   = Field::getModality();
$sortsParam = json_encode($sorts);

// Competition corrente per edit
$competition = null;
if ($action === 'edit' && $id) {
    $competition = DB::table('competitions')->where('id', '=', $id)->first();
    if (!$competition) {
        header("Location: index.php?page=competitions&action=view");
        exit;
    }
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {

    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/competitions/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }

    if ($_POST['participants'] < 4) return;

    DB::table('competitions')->insert([
        'name'         => $_POST['name'],
        'country'      => $_POST['country']      ?: null,
        'modality'     => $_POST['modality'],
        'participants' => $_POST['participants'],
        'round_trip'   => $_POST['round_trip']   ?? 0,
        'num_groups'   => $_POST['num_groups']   ?? null,
        'qualifiers'   => $_POST['qualifiers']   ?? null,
        'images'       => $image_path ? json_encode(['logo' => $image_path]) : null,
        'created'      => date('Y-m-d H:i:s'),
    ]);
    header("Location: index.php?page=competitions&action=view");
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {

    $existing       = DB::table('competitions')->where('id', '=', $id)->first();
    $existingImages = !empty($existing['images']) ? json_decode($existing['images'], true) : [];
    $image_path     = $existingImages['logo'] ?? null;

    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'images/competitions/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $baseName = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($_POST['name'])));
        $target   = $uploadDir . $baseName . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $image_path = $target;
    }

    DB::table('competitions')->where('id', '=', $id)->update([
        'name'         => $_POST['name'],
        'country'      => $_POST['country']      ?: null,
        'modality'     => $_POST['modality'],
        'participants' => $_POST['participants'],
        'round_trip'   => $_POST['round_trip']   ?? 0,
        'num_groups'   => $_POST['num_groups']   ?? null,
        'qualifiers'   => $_POST['qualifiers']   ?? null,
        'images'       => $image_path ? json_encode(['logo' => $image_path]) : null,
        'created'      => date('Y-m-d H:i:s'),
    ]);
    header("Location: index.php?page=competitions&action=view");
    exit;
}

// ── CONFIGURE (POST) ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'configure' && $id) {

    $comp = DB::table('competitions')->where('id', '=', $id)->first();
    if (!$comp) {
        header("Location: index.php?page=competitions&action=view");
        exit;
    }

    $mode         = (int)$comp['modality'];
    $participants = (int)$comp['participants'];
    $part = 0;

    if ($mode === 1) {
        $num_levels = (int)($_POST['num_levels'] ?? 0);
        for ($lvl = 1; $lvl <= $num_levels; $lvl++) {
            $part        += (int)($_POST["level_{$lvl}_teams"]      ?? 0);
        }
    } elseif ($mode === 2) {
        $team_ids = $_POST['teams'] ?? [];
        $part = count($team_ids);
    } elseif ($mode === 3) {
        $num_groups = (int)$comp['num_groups'];
        for ($g = 1; $g <= $num_groups; $g++) {
            $part += count($_POST["group_{$g}_teams"] ?? []);
        }
    }

    if ($part !== $participants) {
        header("Location: index.php?page=competitions&action=configure&id=" . $id . "&error=participants");
        exit;
    }

    // Crea la season
    $season_id = DB::table('seasons')->insert([
        'competition_id' => $id,
        'season_year'    => 1,
        'status'         => 0,
        'created'        => date('Y-m-d H:i:s'),
    ]);

    // ── MODE 1: CAMPIONATO ────────────────────────────────────────────────────
    if ($mode === 1) {
        for ($lvl = 1; $lvl <= $num_levels; $lvl++) {
            $num_teams        = (int)($_POST["level_{$lvl}_teams"]      ?? 0);
            $promotion_spots  = (int)($_POST["level_{$lvl}_promotion"]  ?? 0);
            $relegation_spots = (int)($_POST["level_{$lvl}_relegation"] ?? 0);

            // Upsert competition_levels
            $existing_cl = DB::table('competition_levels')
                ->where('competition_id', '=', $id)
                ->where('level', '=', $lvl)
                ->first();

            if ($existing_cl) {
                DB::table('competition_levels')
                    ->where('competition_id', '=', $id)
                    ->where('level', '=', $lvl)
                    ->update([
                        'num_teams'        => $num_teams,
                        'promotion_spots'  => $promotion_spots,
                        'relegation_spots' => $relegation_spots,
                    ]);
            } else {
                DB::table('competition_levels')->insert([
                    'competition_id'   => $id,
                    'level'            => $lvl,
                    'num_teams'        => $num_teams,
                    'promotion_spots'  => $promotion_spots,
                    'relegation_spots' => $relegation_spots,
                ]);
            }

            // season_teams per questo livello
            $teams_for_level = $_POST["level_{$lvl}_team_ids"] ?? [];
            foreach ($teams_for_level as $team_id) {
                $team_id = (int)$team_id;
                if ($team_id > 0) {
                    DB::table('season_teams')->insert([
                        'season_id' => $season_id,
                        'team_id'   => $team_id,
                        'level'     => $lvl,
                        'group_id'  => null,
                    ]);
                }
            }
        }
    }

    // ── MODE 2: ELIMINAZIONE DIRETTA ─────────────────────────────────────────
    elseif ($mode === 2) {
        $team_ids = array_slice(array_unique(array_map('intval', $team_ids)), 0, $participants);

        foreach ($team_ids as $team_id) {
            if ($team_id > 0) {
                DB::table('season_teams')->insert([
                    'season_id' => $season_id,
                    'team_id'   => $team_id,
                    'level'     => 1,
                    'group_id'  => null,
                ]);
            }
        }
    }

    // ── MODE 3: GIRONI ────────────────────────────────────────────────────────
    elseif ($mode === 3) {
        for ($g = 1; $g <= $num_groups; $g++) {
            $teams_in_group = $_POST["group_{$g}_teams"] ?? [];
            foreach ($teams_in_group as $team_id) {
                $team_id = (int)$team_id;
                if ($team_id > 0) {
                    DB::table('season_teams')->insert([
                        'season_id' => $season_id,
                        'team_id'   => $team_id,
                        'level'     => 1,
                        'group_id'  => $g,
                    ]);
                }
            }
        }
    }

    header("Location: index.php?page=competitions&action=view");
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    DB::table('competitions')->where('id', '=', $id)->delete();
    header("Location: index.php?page=competitions&action=view");
    exit;
}

// ── DUPLICATE ─────────────────────────────────────────────────────────────────
if ($action === 'duplicate' && $id) {
    $orig = DB::table('competitions')->where('id', '=', $id)->first();
    if ($orig) {
        DB::table('competitions')->insert([
            'name'         => $orig['name'] . ' (copia)',
            'country'      => $orig['country'],
            'modality'     => $orig['modality'],
            'participants' => $orig['participants'],
            'round_trip'   => $orig['round_trip'],
            'num_groups'   => $orig['num_groups'],
            'qualifiers'   => $orig['qualifiers'],
            'images'       => $orig['images'],
            'created'      => date('Y-m-d H:i:s'),
        ]);
    }
    header("Location: index.php?page=competitions&action=view");
    exit;
}

Layout::renderSubMenu($menu);

$editImages = [];
if ($competition && !empty($competition['images'])) {
    $editImages = json_decode($competition['images'], true) ?? [];
}

$linkExtra = [
    'page'     => 'competitions',
    'action'   => 'view',
    'limit'    => $limit,
    'page_num' => 1,
];
?>

<div class="competitions">
    <div class="container py-4">

        <?php
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            if ($error == 'participants') Alert::generateAlert('Il numero di partecipanti non combacia con quello inserito', 'danger', 'Partecipanti Sbagliati');
        }
        ?>

        <?php if ($action === 'create' || $action === 'edit'): ?>
            <?php
            if ($action === 'edit') {
                $stagione_esistente = DB::table('seasons')->where('competition_id', '=', $id)->count();
                if ($stagione_esistente > 0) {
                    header("Location: index.php?page=competitions&action=view");
                    exit;
                }
            }
            ?>
            <!-- ── FORM (create / edit) ──────────────────────────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="text-center fw-bold m-0">
                        <?= $action === 'edit' ? 'Modifica Competizione' : 'Crea Competizione' ?>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="index.php?page=competitions&action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>"
                        enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" required
                                    value="<?= htmlspecialchars($competition['name'] ?? '') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Nazione</label>
                                <select name="country" class="form-select">
                                    <option value="">-- Seleziona Stato --</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?= $state['code'] ?>"
                                            <?= ($competition['country'] ?? '') == $state['code'] ? 'selected' : '' ?>>
                                            <?= $state['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col mb-3">
                                <label class="form-label">Modalità</label>
                                <select name="modality" class="form-select">
                                    <option value="">-- Seleziona Modalità --</option>
                                    <?php foreach ($modality as $mod): ?>
                                        <option value="<?= $mod['code'] ?>"
                                            <?= ($competition['modality'] ?? '') == $mod['code'] ? 'selected' : '' ?>>
                                            <?= $mod['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="round_trip"
                                        id="round_trip" value="1"
                                        <?= (!empty($competition['round_trip'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="round_trip">
                                        Andata/Ritorno
                                    </label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <?php $participantOptions = ['4', '8', '16', '32', '64', '128']; ?>

                        <div class="row g-3 mt-2">

                            <!-- CAMPIONATO -->
                            <div id="mod-campionato" class="d-none col-12">
                                <label class="form-label">Partecipanti</label>
                                <input type="number" name="participants" class="form-control" min="4"
                                    value="<?= htmlspecialchars($competition['participants'] ?? '') ?>">
                            </div>

                            <!-- ELIMINAZIONE -->
                            <div id="mod-eliminazione" class="d-none col-12">
                                <label class="form-label">Partecipanti</label>
                                <select name="participants" class="form-select">
                                    <option value="">-- seleziona --</option>
                                    <?php foreach ($participantOptions as $v): ?>
                                        <option value="<?= $v ?>"
                                            <?= ($competition['participants'] ?? '') == $v ? 'selected' : '' ?>>
                                            <?= $v ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- GRUPPI -->
                            <div id="mod-gruppi" class="d-none col-12">
                                <div class="row g-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Partecipanti</label>
                                        <select id="participants_groups" name="participants" class="form-select"
                                            data-saved="<?= htmlspecialchars($competition['participants'] ?? '') ?>">
                                            <option value="">-- seleziona --</option>
                                            <?php foreach ($participantOptions as $v): ?>
                                                <option value="<?= $v ?>"><?= $v ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Gruppi</label>
                                        <select id="num_groups" name="num_groups" class="form-select"
                                            data-saved="<?= htmlspecialchars($competition['num_groups'] ?? '') ?>">
                                            <option value="">-- seleziona --</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Qualificati</label>
                                        <select id="qualifiers" name="qualifiers" class="form-select"
                                            data-saved="<?= htmlspecialchars($competition['qualifiers'] ?? '') ?>">
                                            <option value="">-- seleziona --</option>
                                        </select>
                                    </div>
                                </div>
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
                            <a href="index.php?page=competitions&action=view"
                                class="btn btn-secondary">Annulla</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'configure'): ?>
            <?php
            if (!$id) {
                header("Location: index.php?page=competitions&action=view");
                exit;
            }
            $comp = DB::table('competitions')->where('id', '=', $id)->first();
            if (!$comp) {
                header("Location: index.php?page=competitions&action=view");
                exit;
            }
            $stagione_esistente = DB::table('seasons')->where('competition_id', '=', $id)->count();
            if ($stagione_esistente > 0) {
                header("Location: index.php?page=competitions&action=view");
                exit;
            }

            $mode            = (int)$comp['modality'];
            $participants    = (int)$comp['participants'];
            $num_groups      = (int)($comp['num_groups'] ?? 0);
            $qualifiers      = (int)($comp['qualifiers'] ?? 0);
            $mode_label      = array_column($modality, 'name', 'code')[$mode] ?? '';
            $all_teams       = DB::table('teams')->select('id, name')->orderBy('name', 'ASC')->get();
            $existing_levels = [];

            if ($mode === 1) {
                $rows = DB::table('competition_levels')
                    ->where('competition_id', '=', $id)
                    ->orderBy('level', 'ASC')
                    ->get();
                foreach ($rows as $row) {
                    $existing_levels[$row['level']] = $row;
                }
            }

            $teams_per_group = $num_groups > 0 ? (int)floor($participants / $num_groups) : 0;
            ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h1 class="text-center fw-bold m-0">
                        Configurazione — <?= htmlspecialchars($comp['name']) ?>
                        <small class="text-muted fs-6 ms-2"><?= htmlspecialchars($mode_label) ?></small>
                    </h1>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="index.php?page=competitions&action=configure&id=<?= $id ?>">
                        <hr>

                        <?php if ($mode === 1): ?>
                            <!-- ── MODE 1: CAMPIONATO ─────────────────────────────────────── -->
                            <div class="mb-3 d-flex align-items-center gap-3">
                                <label class="form-label fw-semibold mb-0">Numero Livelli</label>
                                <input type="number" id="num_levels" name="num_levels"
                                    class="form-control w-auto" min="1" max="10"
                                    value="<?= max(1, count($existing_levels)) ?>">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="renderLevels()">Aggiorna</button>
                            </div>

                            <div id="levels-container"></div>

                            <script>
                                window.ALL_TEAMS = <?= json_encode(array_values($all_teams)) ?>;
                                window.EXISTING_LEVELS = <?= json_encode($existing_levels) ?>;
                                document.addEventListener('DOMContentLoaded', window.renderLevels);
                            </script>

                        <?php elseif ($mode === 2): ?>
                            <!-- ── MODE 2: ELIMINAZIONE DIRETTA ──────────────────────────── -->
                            <div class="mb-2">
                                <span class="badge bg-info text-dark fs-6">
                                    Seleziona esattamente <?= $participants ?> squadre
                                </span>
                            </div>

                            <select name="teams[]" id="elim-teams" class="form-select mb-1"
                                size="15" multiple required>
                                <?php foreach ($all_teams as $team): ?>
                                    <option value="<?= $team['id'] ?>">
                                        <?= htmlspecialchars($team['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Tieni premuto Ctrl/Cmd per selezionarne più di uno</small>
                            <div id="elim-counter" class="mt-2 fw-semibold"></div>

                            <script>
                                window.ELIM_REQUIRED = <?= $participants ?>;
                            </script>

                        <?php elseif ($mode === 3): ?>
                            <!-- ── MODE 3: GIRONI ─────────────────────────────────────────── -->
                            <div class="mb-3">
                                <span class="badge bg-secondary fs-6 me-2"><?= $num_groups ?> gironi</span>
                                <span class="badge bg-secondary fs-6 me-2"><?= $teams_per_group ?> squadre per girone</span>
                                <span class="badge bg-secondary fs-6"><?= $qualifiers ?> qualificati per girone</span>
                            </div>

                            <div class="row g-3">
                                <?php for ($g = 1; $g <= $num_groups; $g++): ?>
                                    <div class="col-12 col-md-6 col-lg-3">
                                        <div class="card h-100">
                                            <div class="card-header fw-semibold bg-light">
                                                Girone <?= $g ?>
                                                <small class="text-muted">(<?= $teams_per_group ?> squadre)</small>
                                            </div>
                                            <div class="card-body">
                                                <select name="group_<?= $g ?>_teams[]"
                                                    class="form-select group-select"
                                                    data-required="<?= $teams_per_group ?>"
                                                    data-group="<?= $g ?>"
                                                    size="<?= min(15, count($all_teams)) ?>"
                                                    multiple>
                                                    <?php foreach ($all_teams as $team): ?>
                                                        <option value="<?= $team['id'] ?>">
                                                            <?= htmlspecialchars($team['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted d-block mt-1">
                                                    Ctrl/Cmd per selezione multipla
                                                </small>
                                                <div class="group-counter mt-1 fw-semibold text-danger small"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                        <?php endif; ?>

                        <hr class="mt-4">
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                💾 Salva &amp; Crea Stagione
                            </button>
                            <a href="index.php?page=competitions&action=view"
                                class="btn btn-secondary">Annulla</a>
                        </div>

                    </form>
                </div>
            </div>

        <?php elseif ($action === 'view'): ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <small class="text-muted">
                    <?= $total ?> competizioni totali — pagina <?= $page_num ?> di <?= $pages ?>
                    <?php if (count($sorts) > 0): ?>
                        &nbsp;|&nbsp; Ordine:
                        <?php foreach ($sorts as $pair): ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($pair[0]) ?>
                                <?= $pair[1] === 'ASC' ? '↑' : '↓' ?>
                            </span>
                        <?php endforeach; ?>
                        &nbsp;
                        <a href="index.php?page=competitions&action=view&limit=<?= $limit ?>"
                            class="text-danger text-decoration-none small"
                            title="Rimuovi tutti gli ordinamenti">✕ reset</a>
                    <?php endif; ?>
                </small>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="page" value="competitions">
                    <input type="hidden" name="action" value="view">
                    <input type="hidden" name="sorts" value="<?= htmlspecialchars($sortsParam) ?>">
                    <input type="hidden" name="page_num" value="1">
                    <label class="form-label mb-0 small">Mostra</label>
                    <select name="limit" class="form-select form-select-sm w-auto"
                        onchange="this.form.submit()">
                        <?php foreach (Pagination::$pagination as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>>
                                <?= $opt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle shadow-sm text-center">
                    <thead class="table-dark">
                        <tr>
                            <th><?= Pagination::sortHeader('id',           '#',             $sorts, $linkExtra) ?></th>
                            <th>Logo</th>
                            <th><?= Pagination::sortHeader('name',         'Nome',          $sorts, $linkExtra) ?></th>
                            <th><?= Pagination::sortHeader('modality',     'Modalità',      $sorts, $linkExtra) ?></th>
                            <th><?= Pagination::sortHeader('participants', 'Partecipanti',  $sorts, $linkExtra) ?></th>
                            <th>Ultima Stagione</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitions as $competition): ?>
                            <?php
                            $data  = !empty($competition['images']) ? json_decode($competition['images'], true) : [];
                            $image = $data['logo'] ?? 'images/empty.png';
                            $stagione_esistente = DB::table('seasons')->where('competition_id', '=', $competition['id'])->count();
                            ?>
                            <tr>
                                <td><?= $competition['id'] ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars($image) ?>"
                                        alt="<?= htmlspecialchars($competition['name']) ?>"
                                        class="img-fluid rounded img-sm">
                                </td>
                                <td class="fw-semibold">
                                    <?= Competitions::renderCompetitions($competition['id'], 'p-2 rounded-pill', true) ?>
                                </td>
                                <td>
                                    <?= array_column($modality, 'name', 'code')[$competition['modality']] ?? '' ?>
                                    <?= ($competition['round_trip'] != 0) ? '(A/R)' : '(Solo Andata)' ?>
                                </td>
                                <td class="">
                                    <div><strong><?= $competition['participants'] ?></strong></div>
                                    <?php if (!empty($competition['num_groups'])): ?>
                                        <div>Gruppi: <?= $competition['num_groups'] ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($competition['qualifiers'])): ?>
                                        <div>Qualificati FF: <?= $competition['qualifiers'] ?> per gruppo</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= Seasons::getLastSeason($competition['id'])['season_year'] ?></td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="index.php?page=competition&id=<?= $competition['id'] ?>"
                                            class="btn btn-sm btn-outline-success" title="Visualizza">👁️</a>
                                        <?php if ($stagione_esistente == 0): ?>
                                            <a href="index.php?page=competitions&action=configure&id=<?= $competition['id'] ?>"
                                                class="btn btn-sm btn-outline-success" title="Configura">⚙️</a>
                                            <a href="index.php?page=competitions&action=edit&id=<?= $competition['id'] ?>"
                                                class="btn btn-sm btn-outline-primary" title="Modifica">✏️</a>
                                        <?php endif; ?>
                                        <a href="index.php?page=competitions&action=duplicate&id=<?= $competition['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary" title="Duplica">📋</a>
                                        <a href="index.php?page=competitions&action=delete&id=<?= $competition['id'] ?>"
                                            class="btn btn-sm btn-outline-danger" title="Elimina"
                                            onclick="return confirm('Eliminare <?= htmlspecialchars(addslashes($competition['name'])) ?>?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= Pagination::renderPagination($pages, $page_num, 'competitions', ['sorts' => $sortsParam, 'limit' => $limit]) ?>

        <?php endif; ?>

    </div>
</div>