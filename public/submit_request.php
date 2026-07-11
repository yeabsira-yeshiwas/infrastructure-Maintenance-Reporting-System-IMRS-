<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$conn = getDBConnection();
$error = '';
$success = '';
$requestNumber = '';

// Get infrastructure types (guest submits free-text location)
$typesResult = $conn->query("SELECT * FROM infrastructure_types ORDER BY type_name");
$types = $typesResult->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $infrastructureTypeId = intval($_POST['infrastructure_type_id'] ?? 0);
    $locationName = sanitizeInput($_POST['location_name'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $priority = sanitizeInput($_POST['priority'] ?? 'Medium');
    $submitterName = sanitizeInput($_POST['submitter_name'] ?? '');
    $submitterEmail = sanitizeInput($_POST['submitter_email'] ?? '');
    
    if (empty($title) || empty($description) || $infrastructureTypeId === 0 || empty($locationName)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Defaults for guest submissions (name/email are optional on this page).
        if (empty($submitterName)) {
            $submitterName = 'Guest';
        }
        if (!empty($submitterEmail) && !filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
        $requestNumber = generateRequestNumber();

        $locationId = 0;

        // Resolve typed location to an existing location_id, or create a new location record.
        $locLookupStmt = $conn->prepare("SELECT location_id FROM locations WHERE TRIM(location_name) = TRIM(?) LIMIT 1");
        $locLookupStmt->bind_param("s", $locationName);
        $locLookupStmt->execute();
        $locRow = $locLookupStmt->get_result()->fetch_assoc();
        $locLookupStmt->close();

        if ($locRow) {
            $locationId = (int) $locRow['location_id'];
        } else {
            $locInsertStmt = $conn->prepare(
                "INSERT INTO locations (location_name, building, description) VALUES (?, NULL, 'Created from guest maintenance request')"
            );
            $locInsertStmt->bind_param("s", $locationName);
            $locInsertStmt->execute();
            $locationId = (int) $conn->insert_id;
            $locInsertStmt->close();
        }

        if ($locationId === 0) {
            $error = 'Failed to resolve location. Please try again.';
        } else {
        
        // Insert request with NULL user_id for unregistered General User
        // Status is 'Approved' so it goes directly to Maintenance Manager
        $stmt = $conn->prepare("INSERT INTO maintenance_requests (request_number, user_id, submitter_name, submitter_email, infrastructure_type_id, location_id, title, description, priority, status, approved_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 'Approved', NOW())");
        $stmt->bind_param("sssiisss", $requestNumber, $submitterName, $submitterEmail, $infrastructureTypeId, $locationId, $title, $description, $priority);
        
        if ($stmt->execute()) {
            $requestId = $conn->insert_id;
            
            // Handle file uploads
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
            
            // Notify maintenance manager
            $managerStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Maintenance_Manager' LIMIT 1");
            $managerStmt->execute();
            $managerResult = $managerStmt->get_result();
            if ($managerResult->num_rows > 0) {
                $manager = $managerResult->fetch_assoc();
                createNotification($manager['user_id'], $requestId, 'new_approved_request', 'New General User Request', "A new maintenance request has been submitted by a General User: $requestNumber");
            }
            $managerStmt->close();
            
            logSystemAction('Create Request (Unregistered)', 'maintenance_requests', $requestId, "Request created by unregistered General User: $requestNumber");
            
            $success = "Request submitted successfully! Your request number is: <strong>$requestNumber</strong>. Save this number to track your request.";
        } else {
            $error = 'Failed to submit request. Please try again.';
        }
        
        $stmt->close();
        } // end locationId branch
        } // end email validation else
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Maintenance Request - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body style="background-color: #ffffff; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 1.5rem;">
    <div class="auth-container" style="max-width: 800px; width: 100%; margin: 0 auto;">
        <div class="auth-card" style="background-color: #ffffff;">
            <a href="javascript:history.back()" class="btn-back" title="Go Back" style="margin-bottom: 1.5rem;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div class="auth-header" style="background-color: #ffffff;">
                <i class="fas fa-tools"></i>
                <h1>Submit Maintenance Request</h1>
                <p>General User - No registration required</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
                <br><br>
                <!-- <a href="<?php echo BASE_URL; ?>/public/track_request.php" class="btn btn-primary"> 
                    <i class="fas fa-search"></i> Track Your Request
                </a> -->
                <a href="<?php echo BASE_URL; ?>/public/submit_request.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Submit Another Request
                </a>
            </div>
            <?php else: ?>
            
            <div style="margin-bottom: 1rem; padding: 1rem; background-color: #e0f2fe; border-radius: 0.375rem;">
                <p style="margin: 0;"><i class="fas fa-info-circle"></i> <strong>Note:</strong> You can submit requests without registering. To track your requests and access more features, <a href="<?php echo BASE_URL; ?>/auth/register.php">register here</a>.</p>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" class="auth-form">
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
                
                <div class="form-group">
                    <label for="location_name">
                        <i class="fas fa-map-marker-alt"></i> Location *
                    </label>
                    <input
                        type="text"
                        id="location_name"
                        name="location_name"
                        required
                        maxlength="150"
                        placeholder="Type location (e.g. Building A / Room 12)"
                    >
                    <small class="form-note">You can type any location name. The system will auto-create it if needed.</small>
                </div>
                
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
                        <i class="fas fa-paperclip"></i> Attachments (Images/PDF) *
                    </label>
                    <input type="file" id="attachments" name="attachments[]" multiple accept="image/*,.pdf" required>
                    <small>You can upload multiple files. Maximum file size: 5MB per file.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </form>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>



