<?php
// Database configuration
$host = 'localhost'; // Your database host
$db   = 'rfid_db'; // Your database name
$user = 'root'; // Your database username
$pass = ''; // Your database password
$charset = 'utf8mb4';

// Set up the PDO connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Check if RFID data is received via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rfid'])) {
    // Get the RFID value from the POST request
    $rfid = htmlspecialchars($_POST['rfid']);  // Sanitize the input for security

    // Save the RFID value to a text file
    file_put_contents('rfid_data.txt', $rfid . PHP_EOL, FILE_APPEND);

    // Check if the RFID already exists in the database
    $stmt = $pdo->prepare("SELECT status FROM rfid_data WHERE rfid_value = ?");
    $stmt->execute([$rfid]);
    $row = $stmt->fetch();

    if ($row) {
        // If RFID exists, toggle its status
        $new_status = $row['status'] == 1 ? 0 : 1; // Toggle between 0 and 1
        $update_stmt = $pdo->prepare("UPDATE rfid_data SET status = ? WHERE rfid_value = ?");
        $update_stmt->execute([$new_status, $rfid]);
    } else {
    }

    // Prepare a response to send back to the ESP32
    $response = array('status' => 'success', 'rfid' => $rfid);
    echo json_encode($response);
    
    // Exit to prevent further execution of the script
    exit;
}

// Read the latest RFID data from the text file
$latest_rfid = '';
$status_message = 'No RFID data available.';
if (file_exists('rfid_data.txt')) {
    // Get the last line of the text file
    $lines = file('rfid_data.txt', FILE_IGNORE_NEW_LINES);
    $latest_rfid = end($lines);  // Get the last line

    // Check the status of the latest RFID in the database
    $stmt = $pdo->prepare("SELECT status FROM rfid_data WHERE rfid_value = ?");
    $stmt->execute([$latest_rfid]);
    $status_row = $stmt->fetch();

    if ($status_row) {
        // Set the status message based on the retrieved status
        $status_message = ($status_row['status'] == 1) ? 'Status: 1' : 'Status: 0';
    } else {
        // If not found in the database
        $status_message = 'RFID is not in the database.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Data Display</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        // Function to fetch the latest RFID data
        function fetchLatestRFID() {
            fetch('query.php') // Fetch from the same script
                .then(response => response.text())
                .then(data => {
                    // Create a temporary element to parse the HTML response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data;
                    // Extract the latest RFID value and status message
                    const latestRFID = tempDiv.querySelector('div#latest-rfid').innerText;
                    const statusMessage = tempDiv.querySelector('div#status-message').innerText;

                    // Update the displayed values
                    document.getElementById('latest-rfid').innerText = latestRFID;
                    document.getElementById('status-message').innerText = statusMessage;
                })
                .catch(error => console.error('Error fetching RFID data:', error));
        }

        // Set interval to fetch latest RFID data every 5 seconds
        setInterval(fetchLatestRFID, 5000); // Adjust the interval as needed
    </script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-4">RFID Data Display</h1>
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-2">Latest RFID Value:</h2>
            <div class="text-gray-700 text-center" id="latest-rfid">
                <?= htmlspecialchars($latest_rfid) ?: 'No RFID data available.' ?>
            </div>
        </div>
        <div>
            <h2 class="text-xl font-semibold mb-2">Status:</h2>
            <div class="text-gray-700 text-center" id="status-message">
                <?= $status_message ?: 'No status available.' ?>
            </div>
        </div>
    </div>
</body>
</html>
