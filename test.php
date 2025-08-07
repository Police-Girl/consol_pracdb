<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_conn.php';

$sql = "SELECT * FROM counties";
$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>

