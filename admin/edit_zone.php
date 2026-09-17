<?php
require_once '../includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$pdo = getDB();

$zoneId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get zone data
$stmt = $pdo->prepare("SELECT * FROM zones WHERE id = ?");
$stmt->execute([$zoneId]);
$zone = $stmt->fetch();

if (!$zone) {
    header('Location: zones.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description'] ?? '');
    $centerLat = $_POST['center_lat'];
    $centerLng = $_POST['center_lng'];
    $boundaryGeojson = $_POST['boundary_geojson'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($centerLat) || empty($centerLng)) {
        $error = 'Name and center coordinates are required';
    } else {
        $stmt = $pdo->prepare("
            UPDATE zones 
            SET name = ?, description = ?, center_lat = ?, center_lng = ?, 
                boundary_geojson = ?, is_active = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $description, $centerLat, $centerLng, $boundaryGeojson, $isActive, $zoneId])) {
            $success = 'Zone updated successfully';
            logAudit($user['id'], 'update_zone', ['zone_id' => $zoneId]);
            
            // Refresh zone data
            $stmt = $pdo->prepare("SELECT * FROM zones WHERE id = ?");
            $stmt->execute([$zoneId]);
            $zone = $stmt->fetch();
        } else {
            $error = 'Failed to update zone';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Zone - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-form {
            max-width: 600px;
            margin: 0 auto;
        }
        .zone-preview {
            background: var(--gray-100);
            padding: 20px;
            border-radius: 8px;
            margin-top: 10px;
            text-align: center;
        }
        .zone-preview .coords {
            font-size: 14px;
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h1>Edit Zone</h1>
                <div class="header-right">
                    <span class="online-status">● Online</span>
                    <span class="user-name"><?= $user['full_name'] ?></span>
                </div>
            </header>
            
            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <div class="section">
                    <div class="edit-form">
                        <form method="POST">
                            <div class="form-group">
                                <label>Zone Name *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= $zone['name'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= $zone['description'] ?? '' ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Center Latitude *</label>
                                <input type="number" step="0.00000001" name="center_lat" class="form-control" 
                                       value="<?= $zone['center_lat'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Center Longitude *</label>
                                <input type="number" step="0.00000001" name="center_lng" class="form-control" 
                                       value="<?= $zone['center_lng'] ?>" required>
                            </div>
                            
                            <div class="zone-preview">
                                <div class="coords">
                                    📍 <?= round($zone['center_lat'], 6) ?>, <?= round($zone['center_lng'], 6) ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Boundary (GeoJSON)</label>
                                <textarea name="boundary_geojson" class="form-control" rows="5"
                                          placeholder='{"type":"Polygon","coordinates":[[[34.9,-1.6],[35.1,-1.6],[35.1,-1.4],[34.9,-1.4],[34.9,-1.6]]]}'><?= $zone['boundary_geojson'] ?? '' ?></textarea>
                                <small style="color:var(--gray-500);font-size:12px;">GeoJSON polygon for zone boundary</small>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?= $zone['is_active'] ? 'checked' : '' ?>>
                                    Active Zone
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Update Zone</button>
                            <a href="zones.php" class="btn btn-secondary btn-block" style="margin-top:10px;">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/app.js"></script>
</body>
</html>