<?php
$pageTitle = 'Create Maintenance Request';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Student', 'Staff', 'General_User']);

$conn = getDBConnection();
$error = '';
$currentRole = getCurrentUserRole();
$studentLocations = getCanonicalStudentLocations();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $infrastructureTypeId = intval($_POST['infrastructure_type_id'] ?? 0);
    $locationId = intval($_POST['location_id'] ?? 0);
    $manualLocation = sanitizeInput($_POST['manual_location'] ?? '');
    $studentLocationRaw = trim($_POST['student_location'] ?? '');
    $officeHeadId = intval($_POST['office_head_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $priority = sanitizeInput($_POST['priority'] ?? 'Medium');
    $userRole = getCurrentUserRole();

    if (in_array($userRole, ['General_User', 'Staff'], true) && $manualLocation !== '') {
        $lookupStmt = $conn->prepare("SELECT location_id FROM locations WHERE location_name = ? LIMIT 1");
        $lookupStmt->bind_param("s", $manualLocation);
        $lookupStmt->execute();
        $existingLocation = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();

        if ($existingLocation) {
            $locationId = (int) $existingLocation['location_id'];
        } else {
            $buildingLabel = $userRole === 'Staff' ? 'Staff Reported' : 'General User Reported';
            $insertLocationStmt = $conn->prepare("INSERT INTO locations (location_name, building, description) VALUES (?, ?, 'Created from manual user input')");
            $insertLocationStmt->bind_param("ss", $manualLocation, $buildingLabel);
            $insertLocationStmt->execute();
            $locationId = (int) $conn->insert_id;
            $insertLocationStmt->close();
        }
    } elseif ($userRole === 'Student' && in_array($studentLocationRaw, $studentLocations, true)) {
        $lookupStmt = $conn->prepare("SELECT location_id FROM locations WHERE location_name = ? LIMIT 1");
        $lookupStmt->bind_param("s", $studentLocationRaw);
        $lookupStmt->execute();
        $existingLocation = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();

        if ($existingLocation) {
            $locationId = (int) $existingLocation['location_id'];
        } else {
            $insertLocationStmt = $conn->prepare("INSERT INTO locations (location_name, building, description) VALUES (?, 'Student Area', 'Created from student location list')");
            $insertLocationStmt->bind_param("s", $studentLocationRaw);
            $insertLocationStmt->execute();
            $locationId = (int) $conn->insert_id;
            $insertLocationStmt->close();
        }
    }

    $locationIsValid = $locationId > 0;
    if ($userRole === 'Student') {
        $locationIsValid = in_array($studentLocationRaw, $studentLocations, true) && $locationId > 0;
    } elseif (in_array($userRole, ['General_User', 'Staff'], true)) {
        $locationIsValid = $manualLocation !== '' && $locationId > 0;
    }

    $officeHeadOk = true;
    if ($userRole === 'Staff') {
        $officeHeadOk = false;
        if ($officeHeadId > 0) {
            $ohStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'Office_Head' AND status = 'Active'");
            $ohStmt->bind_param("i", $officeHeadId);
            $ohStmt->execute();
            $officeHeadOk = $ohStmt->get_result()->num_rows > 0;
            $ohStmt->close();
        }
    }

    $hasProctorForStudent = true;
    if ($userRole === 'Student' && $locationIsValid) {
        $pcStmt = $conn->prepare("SELECT COUNT(*) AS c FROM proctor_locations pl INNER JOIN users u ON u.user_id = pl.proctor_user_id WHERE pl.location_name = ? AND u.role = 'Proctor' AND u.status = 'Active'");
        $pcStmt->bind_param("s", $studentLocationRaw);
        $pcStmt->execute();
        $hasProctorForStudent = ((int) ($pcStmt->get_result()->fetch_assoc()['c'] ?? 0)) > 0;
        $pcStmt->close();
    }

    if (empty($title) || empty($description) || $infrastructureTypeId === 0 || !$locationIsValid) {
        $error = 'Please fill in all required fields.';
    } elseif ($userRole === 'Staff' && !$officeHeadOk) {
        $error = 'Please select a valid office head.';
    } elseif ($userRole === 'Student' && !$hasProctorForStudent) {
        $error = 'No proctor is assigned to this location yet. Please contact the maintenance manager.';
    } else {
        $requestNumber = generateRequestNumber();
        $userId = getCurrentUserId();

        if ($userRole === 'General_User') {
            $status = 'Pending';
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (request_number, user_id, infrastructure_type_id, location_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiissss", $requestNumber, $userId, $infrastructureTypeId, $locationId, $title, $description, $priority, $status);
        } elseif ($userRole === 'Staff') {
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (request_number, user_id, infrastructure_type_id, location_id, assigned_office_head_id, title, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiiisss", $requestNumber, $userId, $infrastructureTypeId, $locationId, $officeHeadId, $title, $description, $priority);
        } else {
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (request_number, user_id, infrastructure_type_id, location_id, title, description, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiisss", $requestNumber, $userId, $infrastructureTypeId, $locationId, $title, $description, $priority);
        }

        if ($stmt->execute()) {
            $requestId = $conn->insert_id;

            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $key => $name) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$key],
                            'type' => $_FILES['attachments']['type'][$key],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                            'error' => $_FILES['attachments']['error'][$key],
                            'size' => $_FILES['attachments']['size'][$key]
                        ];
                        uploadFile($file, $requestId);
                    }
                }
            }

            if ($userRole === 'General_User') {
                $managerStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Maintenance_Manager' LIMIT 1");
                $managerStmt->execute();
                $managerResult = $managerStmt->get_result();
                if ($managerResult->num_rows > 0) {
                    $manager = $managerResult->fetch_assoc();
                    createNotification($manager['user_id'], $requestId, 'new_request', 'General User Request Pending Review', "A new general user maintenance request requires approval: $requestNumber");
                }
                $managerStmt->close();
            } elseif ($userRole === 'Student') {
                $notifyStmt = $conn->prepare("SELECT DISTINCT pl.proctor_user_id FROM proctor_locations pl INNER JOIN users u ON u.user_id = pl.proctor_user_id WHERE pl.location_name = ? AND u.role = 'Proctor' AND u.status = 'Active'");
                $notifyStmt->bind_param("s", $studentLocationRaw);
                $notifyStmt->execute();
                $proctors = $notifyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $notifyStmt->close();
                foreach ($proctors as $row) {
                    createNotification((int) $row['proctor_user_id'], $requestId, 'new_request', 'New Student Maintenance Request', "A student maintenance request was submitted for location: $studentLocationRaw — $requestNumber");
                }
            } else {
                createNotification($officeHeadId, $requestId, 'new_request', 'New Staff Maintenance Request', "A staff maintenance request was submitted and requires your approval: $requestNumber");
            }

            logSystemAction('Create Request', 'maintenance_requests', $requestId, "Request created: $requestNumber");

            header('Location: ' . BASE_URL . '/requests/view.php?id=' . $requestId . '&success=request_created');
            exit();
        }
        $error = 'Failed to create request. Please try again.';
        $stmt->close();
    }
}

$typesResult = $conn->query("SELECT * FROM infrastructure_types ORDER BY type_name");
$types = $typesResult->fetch_all(MYSQLI_ASSOC);

$officeHeads = [];
if ($currentRole === 'Staff') {
    $ohResult = $conn->query("SELECT user_id, full_name, email, department FROM users WHERE role = 'Office_Head' AND status = 'Active' ORDER BY full_name");
    if ($ohResult) {
        $officeHeads = $ohResult->fetch_all(MYSQLI_ASSOC);
    }
}

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Create Maintenance Request</h1>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($currentRole === 'Staff' && empty($officeHeads)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        No office heads are registered yet. Ask the maintenance manager to create Office Head accounts first.
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="<?php echo BASE_URL; ?>/requests/create.php" enctype="multipart/form-data" class="form">
            <div class="form-group">
                <label for="infrastructure_type_id">
                    <i class="fas fa-building"></i> Infrastructure Type *
                </label>
                <select id="infrastructure_type_id" name="infrastructure_type_id" required>
                    <option value="">Select Type</option>
                    <?php foreach ($types as $type): ?>
                    <option value="<?php echo $type['type_id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($currentRole === 'Staff'): ?>
            <div class="form-group">
                <label for="office_head_id">
                    <i class="fas fa-user-tie"></i> Office Head (approver) *
                </label>
                <select id="office_head_id" name="office_head_id" required <?php echo empty($officeHeads) ? 'disabled' : ''; ?>>
                    <option value="">Select Office Head</option>
                    <?php foreach ($officeHeads as $oh): ?>
                    <option value="<?php echo (int) $oh['user_id']; ?>">
                        <?php echo htmlspecialchars($oh['full_name']); ?>
                        <?php if (!empty($oh['department'])): ?> — <?php echo htmlspecialchars($oh['department']); ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small>Your request is sent to this office head for approval, then to the maintenance manager.</small>
            </div>
            <?php endif; ?>

            <?php if ($currentRole === 'Student'): ?>
            <div class="form-group">
                <label for="student_location">
                    <i class="fas fa-map-marker-alt"></i> Location *
                </label>
                <select id="student_location" name="student_location" required>
                    <option value="">Select Location</option>
                    <?php foreach ($studentLocations as $studentLoc): ?>
                    <option value="<?php echo htmlspecialchars($studentLoc); ?>"><?php echo htmlspecialchars($studentLoc); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Your request is sent to the proctor assigned for this location.</small>
            </div>
            <?php elseif ($currentRole === 'General_User' || $currentRole === 'Staff'): ?>
            <div class="form-group">
                <label for="manual_location">
                    <i class="fas fa-map-marker-alt"></i> Location *
                </label>
                <input type="text" id="manual_location" name="manual_location" required maxlength="150" placeholder="Type your location (e.g., Main Gate, Block B Room 12)">
                <small><?php echo $currentRole === 'Staff' ? 'Staff can type location manually.' : 'General users can type their exact location.'; ?></small>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="title">
                    <i class="fas fa-heading"></i> Title *
                </label>
                <input type="text" id="title" name="title" required maxlength="200" placeholder="Brief description of the issue">
            </div>

            <div class="form-group">
                <label for="description">
                    <i class="fas fa-align-left"></i> Description *
                </label>
                <textarea id="description" name="description" rows="5" required placeholder="Detailed description of the maintenance issue"></textarea>
            </div>

            <div class="form-group">
                <label for="priority">
                    <i class="fas fa-exclamation-triangle"></i> Priority *
                </label>
                <select id="priority" name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>

            <div class="form-group">
                <label for="attachments">
                    <i class="fas fa-paperclip"></i> Attachments (Images/PDF)
                </label>
<input type="file" id="attachments" name="attachments[]" multiple accept="image/*,.pdf"
<?php echo ($currentRole === 'General_User') ? 'required' : ''; ?>>
                <small>You can upload multiple files. Maximum file size: 5MB per file.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?php echo ($currentRole === 'Staff' && empty($officeHeads)) ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
