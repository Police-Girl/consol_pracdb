<?php


file_put_contents('debug.log', print_r($_POST, true), FILE_APPEND);
file_put_contents('debug.log', print_r($_FILES, true), FILE_APPEND);


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-USER-EMAIL, Content-Type, Authorization");
    header("HTTP/1.1 200 OK");
    exit();
}


header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-USER-EMAIL");

$servername = "localhost"; 
$username   = "root";      
$password   = "";          
$dbname     = "consol_pracdb"; 
$port       = "3306";



$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


    function checkUserAuthorization($conn) {
    
    $userName = trim(isset($_SERVER['HTTP_X_USER_EMAIL']) ? $_SERVER['HTTP_X_USER_EMAIL'] : null);
    
    if (!$userName) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized!"]);
        exit;
    }
    
   
    $stmt = $conn->prepare("SELECT * FROM users WHERE Email = ?");
    $stmt->bind_param("s", $userName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(["message" => "Forbidden: User not authorized."]);
        exit;
    }
    
    $stmt->close();
}


// checkUserAuthorization($conn);

$method = $_SERVER['REQUEST_METHOD'];
$entity = isset($_GET['entity']) ? $_GET['entity'] : '';
$input = json_decode(file_get_contents("php://input"), true);



$method = $_SERVER['REQUEST_METHOD'];
$entity = isset($_GET['entity']) ? $_GET['entity'] : '';
$input = json_decode(file_get_contents("php://input"), true);
switch ($entity) {
    case 'users':
        handleUsers($conn, $method, $input);
        break;
    case 'consultants':
    case 'disciplines':
    case 'consultant_disciplines':
    case 'engagements':
    case 'attachment':
    case 'attachmentTypes':
    case 'counties':
        
        checkUserAuthorization($conn);
    switch ($entity) {
        case 'consultants':
            handleConsultants($conn, $method, $input);
            break;
        case 'disciplines':
            handleDisciplines($conn, $method, $input);
            break;
        case 'consultant_disciplines':
            handleConsultantsDisciplines($conn, $method, $input);
            break;
        case 'engagements':
            handleEngagements($conn, $method, $input);
            break;
        case 'attachment':
            handleAttachments($conn, $method, $input);
            break;
        case 'attachmentTypes':
            handleAttachmentTypes($conn, $method, $input);
            break;
        case 'counties':
            handleCounties($conn, $method, $input);
            break;
        }
        break;
        default:
            http_response_code(400);
            echo json_encode(["message" => "Invalid entity."]);
            break;
}

$conn->close();

function handleUsers($conn, $method, $input) {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if ($method === 'POST' && $action === 'validate') {
        $email = isset($input['email']) ? trim($input['email']) : '';
        $username = isset($input['username']) ? trim($input['username']) : '';
        if (!$email || !$username) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'message' => 'Email and username required.']);
            return;
        }
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = ? AND name = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            echo json_encode(['valid' => true, 'user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['valid' => false, 'message' => 'Invalid credentials.']);
        }
        $stmt->close();
    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid action for users.']);
    }
}

function handleConsultants($conn, $method, $input) {
    if ($method == 'GET') {
        $sql = "SELECT * FROM consultants WHERE isDeleted = 0";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($method == 'POST') {
        $stmt = $conn->prepare("INSERT INTO consultants 
            (ConsultantName, Email, phone, consultantType, education_level,
            physical_street_name, physical_building_name, physical_house_number, physical_landmark, 
            physical_town_city, physical_county_id, postal_po_box, postal_postal_code, 
            postal_post_office_location, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param(
            "ssssssssssisiss",
            $input['ConsultantName'],
            $input['Email'],
            $input['phone'],
            $input['consultantType'],
            $input['education_level'],
            $input['physical_street_name'],
            $input['physical_building_name'],
            $input['physical_house_number'],
            $input['physical_landmark'],
            $input['physical_town_city'],
            $input['physical_county_id'],
            $input['postal_po_box'],
            $input['postal_postal_code'],
            $input['postal_post_office_location'],
            $input['created_by']
        );

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            echo json_encode(["insertId" => $newId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error creating consultant: " . $stmt->error]);
        }
        $stmt->close();
    } elseif ($method == 'PUT') {
        $stmt = $conn->prepare("UPDATE consultants SET 
            ConsultantName = ?, Email = ?, phone = ?, consultantType = ?, education_level = ?, 
            physical_street_name = ?, physical_building_name = ?, physical_house_number = ?, physical_landmark = ?, 
            physical_town_city = ?, physical_county_id = ?, postal_po_box = ?, postal_postal_code = ?, 
            postal_post_office_location = ?, updated_by = ?, updated_at = NOW() 
            WHERE ConsultantID = ? AND deleted_at IS NULL");
        $stmt->bind_param(
            "ssssssssssisissi",
            $input['ConsultantName'],
            $input['Email'],
            $input['phone'],
            $input['consultantType'],
            $input['education_level'],
            $input['physical_street_name'],
            $input['physical_building_name'],
            $input['physical_house_number'],
            $input['physical_landmark'],
            $input['physical_town_city'],
            $input['physical_county_id'],
            $input['postal_po_box'],
            $input['postal_postal_code'],
            $input['postal_post_office_location'],
            $input['updated_by'],
            $input['ConsultantID']
        );

        if ($stmt->execute()) {
            echo json_encode(["message" => "Consultant updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error updating consultant: " . $stmt->error]);
        }
        $stmt->close();
    } elseif ($method == 'DELETE') {
        if (!isset($input['ConsultantID']) || !isset($input['deleted_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "ConsultantID and deleted_by are required."]);
            return;
        }
        $stmt = $conn->prepare("UPDATE consultants SET 
            deleted_at = NOW(), deleted_by = ?, isDeleted = 1 
            WHERE ConsultantID = ?");
        $stmt->bind_param("si", $input['deleted_by'], $input['ConsultantID']);
        executeStatement($stmt);
    }
}


function handleDisciplines($conn, $method, $input) {
    if ($method == 'GET') {
        $sql = "SELECT * FROM disciplines WHERE isDeleted = 0 ";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($method == 'POST') {
        $stmt = $conn->prepare("INSERT INTO disciplines 
            (DisciplineName, created_by, created_at) 
            VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $input['DisciplineName'], $input['created_by']);
        executeStatement($stmt);
    } elseif ($method == 'PUT') {
        if (!isset($input['DisciplineID']) || !isset($input['DisciplineName']) || !isset($input['updated_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "DisciplineID, DisciplineName and updated_by are required for update."]);
            return;
        }
        $stmt = $conn->prepare("UPDATE disciplines SET 
            DisciplineName = ?, 
            updated_by = ?, 
            updated_at = NOW() 
            WHERE DisciplineID = ? AND isDeleted = 0");
        $stmt->bind_param("ssi", 
            $input['DisciplineName'], 
            $input['updated_by'], 
            $input['DisciplineID']
        );
        executeStatement($stmt);
    } elseif ($method == 'DELETE') {
        if (!isset($input['DisciplineID']) || !isset($input['deleted_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "DisciplineID and deleted_by are required."]);
            return;
        }
        $stmt = $conn->prepare("UPDATE disciplines SET 
            deleted_at = NOW(), deleted_by = ?, isDeleted = 1
            WHERE DisciplineID = ?");
        $stmt->bind_param("si", $input['deleted_by'], $input['DisciplineID']);
        executeStatement($stmt);
    }
}


function handleConsultantsDisciplines($conn, $method, $input) {
    if ($method == 'GET') {
        $sql = "SELECT * FROM consultant_disciplines WHERE isDeleted = 0";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($method == 'POST') {
        if (!isset($input['ConsultantID']) || !isset($input['DisciplineID']) || !isset($input['created_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "ConsultantID, DisciplineID, and created_by are required."]);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO consultant_disciplines 
            (ConsultantID, DisciplineID, created_by, created_at) 
            VALUES (?, ?, ?, NOW())");
        $stmt->bind_param(
            "iis",
            $input['ConsultantID'],
            $input['DisciplineID'],
            $input['created_by']
        );

        if ($stmt->execute()) {
            echo json_encode(["message" => "Discipline added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to add discipline: " . $stmt->error]);
        }
        $stmt->close();
    }elseif ($method == 'PUT') {
        // Update an existing discipline 
        if (!isset($input['ConsultantID']) || !isset($input['DisciplineID']) || !isset($input['updated_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "ConsultantID, DisciplineID, and updated_by are required for update."]);
            return;
        }

        $stmt = $conn->prepare("UPDATE consultant_disciplines SET 
            DisciplineID = ?, 
            updated_by = ?, 
            updated_at = NOW() 
            WHERE ConsultantID = ? AND isDeleted = 0");
        $stmt->bind_param(
            "isi",
            $input['DisciplineID'],
            $input['updated_by'],
            $input['ConsultantID']
        );

        if ($stmt->execute()) {
            echo json_encode(["message" => "Discipline updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update discipline: " . $stmt->error]);
        }
        $stmt->close();
    } elseif ($method == 'DELETE') {
        // Soft delete a discipline 
        if (!isset($input['ConsultantID']) || !isset($input['DisciplineID']) || !isset($input['deleted_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "ConsultantID, DisciplineID, and deleted_by are required for deletion."]);
            return;
        }

        $stmt = $conn->prepare("UPDATE consultant_disciplines SET 
            deleted_at = NOW(), 
            deleted_by = ?, 
            isDeleted = 1 
            WHERE ConsultantID = ? AND DisciplineID = ?");
        $stmt->bind_param(
            "sii",
            $input['deleted_by'],
            $input['ConsultantID'],
            $input['DisciplineID']
        );

        if ($stmt->execute()) {
            echo json_encode(["message" => "Discipline deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete discipline: " . $stmt->error]);
        }
        $stmt->close();
    }
}

function handleEngagements($conn, $method, $input) {
    if ($method == 'GET') {
        $sql = "SELECT * FROM consultant_engagement WHERE isDeleted = 0";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($method == 'POST') {
        // Updated to include contractCurrency
        $stmt = $conn->prepare("INSERT INTO consultant_engagement 
            (ConsultantID, DisciplineID, isEngaged, engagementDescription, startDate, endDate, 
            remarks, status, contractValue, contractCurrency, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param(
            "iiisssssdss", // types: i,i,i,s,s,s,s,s,d,s,s
            $input['ConsultantID'],
            $input['DisciplineID'],
            $input['isEngaged'],
            $input['engagementDescription'],
            $input['startDate'],
            $input['endDate'],
            $input['remarks'],
            $input['status'],
            $input['contractValue'],
            $input['contractCurrency'],  // new field
            $input['created_by']
        );
        executeStatement($stmt);
    } elseif ($method == 'PUT') {
        if (!isset($input['EngagementID'])) {
            http_response_code(400);
            echo json_encode(["message" => "EngagementID is required for update."]);
            return;
        }
        // Updated to include contractCurrency
        $stmt = $conn->prepare("UPDATE consultant_engagement SET 
            ConsultantID = ?, 
            DisciplineID = ?,
            isEngaged = ?, 
            engagementDescription = ?, 
            startDate = ?, 
            endDate = ?, 
            remarks = ?, 
            status = ?,
            contractValue = ?, 
            contractCurrency = ?, 
            updated_by = ?, 
            updated_at = NOW() 
            WHERE EngagementID = ? AND deleted_at IS NULL");
        $stmt->bind_param(
            "iiisssssdssi", // types: i,i,i,s,s,s,s,s,d,s,s,i
            $input['ConsultantID'],
            $input['DisciplineID'],
            $input['isEngaged'],
            $input['engagementDescription'],
            $input['startDate'],
            $input['endDate'],
            $input['remarks'],
            $input['status'],
            $input['contractValue'],
            $input['contractCurrency'],  // new field
            $input['updated_by'],
            $input['EngagementID']
        );
        executeStatement($stmt);
    } elseif ($method == 'DELETE') {
        if (!isset($input['EngagementID']) || !isset($input['deleted_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "EngagementID and deleted_by required."]);
            return;
        }
        $stmt = $conn->prepare("UPDATE consultant_engagement SET 
            deleted_at = NOW(), deleted_by = ?, isDeleted = 1
            WHERE EngagementID = ?");
        $stmt->bind_param("si", $input['deleted_by'], $input['EngagementID']);
        executeStatement($stmt);
    }
}


function handleAttachments($conn, $method, $input) {

    if ($method == 'GET') {
        $consultantId = $_GET['consultantID'] ?? null;
        if (!$consultantId) {
            http_response_code(400);
            echo json_encode(["message" => "consultantId parameter is required"]);
            return;
        }
        
        $stmt = $conn->prepare("SELECT * FROM attachments WHERE ConsultantID = ? AND isDeleted = 0");
        $stmt->bind_param("i", $consultantId);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        
    } elseif ($method == 'POST') {

        $home = getenv('HOME') ?: '/home';
        $baseDir = $home . '/site/wwwwroot/consol_pracdb/uploads/';
        $consultantId    = (int) ($_POST['ConsultantID'] ?? 0);

        if (!$consultantId) {
            http_response_code(400);
            exit('consultantId is required');
        }
    
        $consultantDir = $baseDir . 'consultant_' . $consultantId . '/';
    
        if (!file_exists($consultantDir)) {
            mkdir($consultantDir, 0755, true); // create folder if not exists
        }

        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(["message" => "No file uploaded"]);
            return;
        }

        $consultantId = (int)$_POST['ConsultantID'];
        $attachmentTypeId = (int)$_POST['AttachmentTypeID'];
        $validityStart = $_POST['validity_start'] ?? null;
        $validityEnd = $_POST['validity_end'] ?? null;
        $createdBy = $_POST['created_by'];
        
        // Updated allowed file types
        $allowedTypes = [
            'application/pdf', 
            'image/jpeg', 
            'image/png', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
        ];
        $maxSize = 15 * 1024 * 1024; // 15MB
        
        if (!in_array($_FILES['file']['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(["message" => "Only PDF, Word (DOCX), Excel (XLSX), JPG, and PNG files are allowed"]);
            return;
        }
        
        if ($_FILES['file']['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(["message" => "File size exceeds 15MB limit"]);
            return;
        }

        // $uploadDir = __DIR__ . '/uploads/consultant_' . $consultantId . '/';
        // if (!file_exists($uploadDir)) {
        //     mkdir($uploadDir, 0755, true);
        // }

        $fileExt = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExt;
        $filePath = $consultantDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . '/' . $filePath)) {
            $stmt = $conn->prepare("INSERT INTO attachments 
                (ConsultantID, AttachmentTypeID, FilePath, validity_start, validity_end, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "iissss",
                $consultantId,
                $attachmentTypeId,
                $filePath,
                $validityStart,
                $validityEnd,
                $createdBy
            );

            if ($stmt->execute()) {
                echo json_encode(["message" => "Attachment uploaded successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Database error: " . $stmt->error]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to upload file"]);
        }
    } elseif ($method == 'DELETE') {
        $stmt = $conn->prepare("SELECT FilePath FROM attachments WHERE AttachmentID = ?");
        $stmt->bind_param("i", $input['AttachmentID']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $filePath = $result['FilePath'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $stmt = $conn->prepare("DELETE FROM attachments WHERE AttachmentID = ?");
            $stmt->bind_param("i", $input['AttachmentID']);
            if ($stmt->execute()) {
                echo json_encode(["message" => "File deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Database error: " . $stmt->error]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "File not found"]);
        }
    }
}

function handleAttachmentTypes($conn, $method, $input) {
    if ($method == 'GET') {
        // Fetch all attachment types that are not deleted
        $sql = "SELECT * FROM attachmenttypes WHERE isDeleted = 0";
        $result = $conn->query($sql);

        if ($result) {
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to fetch attachment types."]);
        }
    } elseif ($method == 'POST') {
        // Add a new attachment type
        if (!isset($input['AttachmentName']) || !isset($input['created_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "AttachmentName and created_by are required."]);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO attachmenttypes (AttachmentName, created_by, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $input['AttachmentName'], $input['created_by']);
        executeStatement($stmt);
    } elseif ($method == 'DELETE') {
        // Soft delete an attachment type
        if (!isset($input['AttachmentTypeID']) || !isset($input['deleted_by'])) {
            http_response_code(400);
            echo json_encode(["message" => "AttachmentTypeID and deleted_by are required."]);
            return;
        }

        $stmt = $conn->prepare("UPDATE attachmenttypes SET deleted_at = NOW(), deleted_by = ?, isDeleted = 1 WHERE AttachmentTypeID = ?");
        $stmt->bind_param("si", $input['deleted_by'], $input['AttachmentTypeID']);
        executeStatement($stmt);
    }
}

function handleCounties($conn, $method, $input) {
    if ($method == 'GET') {
        $sql = "SELECT * FROM counties";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } elseif ($method == 'POST') {
        if (!isset($input['county_name'])) {
            http_response_code(400);
            echo json_encode(["message" => "County name is required"]);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO counties (county_name) VALUES (?)");
        $stmt->bind_param("s", $input['county_name']);

        if ($stmt->execute()) {
            echo json_encode(["message" => "County added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to add county: " . $stmt->error]);
        }
        $stmt->close();
    }
}

// Update executeStatement function to prevent double response
function executeStatement($stmt) {
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}
?>
